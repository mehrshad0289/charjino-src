<?php

namespace App\Modules\Catalog;

use Illuminate\Support\Facades\Cache;
use Throwable;

final class StorefrontHomeCache
{
    public const KEY = 'storefront:home:v2';

    public const FACET_VERSION_KEY = 'storefront:facets:ver';

    public const TTL_SECONDS = 3600;

    private const LOCK = 'storefront:home:build';

    /** @var list<string> */
    private const HOME_SETTING_KEYS = [
        'flash_sale_title',
        'flash_sale_subtitle',
        'flash_sale_ends_at',
        'popular_title',
        'popular_subtitle',
        'home_intro_html',
        'home_sections',
        'usd_toman_rate',
        'margin_percent',
    ];

    private static int $deferDepth = 0;

    /**
     * @param  callable(): array<string, mixed>  $builder
     * @return array<string, mixed>
     */
    public static function remember(callable $builder): array
    {
        $cached = Cache::get(self::KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $store = function () use ($builder): array {
            $cached = Cache::get(self::KEY);
            if (is_array($cached)) {
                return $cached;
            }
            $payload = $builder();
            Cache::put(self::KEY, $payload, self::TTL_SECONDS);

            return $payload;
        };

        try {
            return Cache::lock(self::LOCK, 20)->block(12, $store);
        } catch (Throwable) {
            return $store();
        }
    }

    public static function forget(): void
    {
        if (self::$deferDepth > 0) {
            return;
        }

        Cache::forget(self::KEY);
        self::bumpFacetVersion();
    }

    public static function forgetIfHomeSetting(string $key): void
    {
        if (in_array($key, self::HOME_SETTING_KEYS, true)) {
            self::forget();
        }
    }

    /**
     * Hold invalidation until a bulk write (catalog sync / reprice) finishes, then drop once.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public static function defer(callable $fn): mixed
    {
        self::$deferDepth++;
        try {
            return $fn();
        } finally {
            self::$deferDepth--;
            if (self::$deferDepth === 0) {
                Cache::forget(self::KEY);
                self::bumpFacetVersion();
            }
        }
    }

    public static function reset(): void
    {
        self::$deferDepth = 0;
        Cache::forget(self::KEY);
        self::bumpFacetVersion();
    }

    public static function facetVersion(): int
    {
        return max(1, (int) Cache::get(self::FACET_VERSION_KEY, 1));
    }

    private static function bumpFacetVersion(): void
    {
        Cache::forever(self::FACET_VERSION_KEY, self::facetVersion() + 1);
    }
}

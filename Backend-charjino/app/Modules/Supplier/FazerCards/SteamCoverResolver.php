<?php

namespace App\Modules\Supplier\FazerCards;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class SteamCoverResolver
{
    public const CACHE_PREFIX = 'steam-cover-v2:';

    public static function cacheKey(int $appId): string
    {
        return self::CACHE_PREFIX.$appId;
    }

    public function url(int|string $appId, bool $rememberMiss = true): string
    {
        $id = (int) preg_replace('/\D+/', '', (string) $appId);
        if ($id <= 0) {
            return '';
        }

        $key = self::cacheKey($id);
        if (Cache::has($key)) {
            $cached = Cache::get($key);
            if (is_string($cached) && $cached !== '' && FazerCardsMedia::isHashedSteamHeader($cached)) {
                return $cached;
            }
            if ($cached === '' && $rememberMiss) {
                return '';
            }
        }

        $hashed = $this->fromStore($id);
        if ($hashed !== '' && FazerCardsMedia::isHashedSteamHeader($hashed)) {
            Cache::put($key, $hashed, now()->addDays(14));

            return $hashed;
        }

        if ($rememberMiss) {
            Cache::put($key, '', now()->addMinutes(30));
        }

        return '';
    }

    public function remember(int $appId, string $url): string
    {
        $safe = FazerCardsMedia::iranSafeSteamUrl($url);
        if ($appId <= 0 || ! FazerCardsMedia::isHashedSteamHeader($safe)) {
            return '';
        }
        Cache::put(self::cacheKey($appId), $safe, now()->addDays(14));

        return $safe;
    }

    private function fromStore(int $appId): string
    {
        try {
            $response = Http::timeout(8)
                ->connectTimeout(4)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Charjino/1.0)'])
                ->get('https://store.steampowered.com/api/appdetails', [
                    'appids' => $appId,
                    'filters' => 'basic',
                ]);
        } catch (\Throwable) {
            return '';
        }
        if (! $response->successful()) {
            return '';
        }
        $header = $response->json((string) $appId.'.data.header_image');
        if (! is_string($header) || ! str_starts_with($header, 'https://')) {
            return '';
        }

        return FazerCardsMedia::iranSafeSteamUrl($header);
    }
}

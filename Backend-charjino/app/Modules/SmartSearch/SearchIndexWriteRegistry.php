<?php

namespace App\Modules\SmartSearch;

use Illuminate\Support\Facades\Storage;

final class SearchIndexWriteRegistry
{
    private const FILE = 'smart-search-indexes.json';

    /**
     * @return array{
     *     products: string,
     *     brands: string,
     *     taxonomies: string,
     *     mirrors: array{products?: string, brands?: string, taxonomies?: string},
     *     barrier: bool
     * }
     */
    public static function state(): array
    {
        $prefix = (string) config('smart-search.index_prefix');
        $defaults = [
            'products' => $prefix.'products',
            'brands' => $prefix.'brands',
            'taxonomies' => $prefix.'taxonomies',
            'mirrors' => [],
            'barrier' => false,
        ];
        if (! Storage::disk('local')->exists(self::FILE)) {
            return $defaults;
        }
        $raw = json_decode(Storage::disk('local')->get(self::FILE) ?: '', true);
        if (! is_array($raw)) {
            return $defaults;
        }

        return array_merge($defaults, $raw);
    }

    /**
     * @return array{products: string, brands: string, taxonomies: string}
     */
    public static function active(): array
    {
        $state = self::state();

        return [
            'products' => (string) $state['products'],
            'brands' => (string) $state['brands'],
            'taxonomies' => (string) $state['taxonomies'],
        ];
    }

    /**
     * @return array{products: list<string>, brands: list<string>, taxonomies: list<string>}
     */
    public static function writeTargets(): array
    {
        $state = self::state();
        $mirrors = is_array($state['mirrors'] ?? null) ? $state['mirrors'] : [];
        $out = [];
        foreach (['products', 'brands', 'taxonomies'] as $logical) {
            $uids = [(string) $state[$logical]];
            $mirror = (string) ($mirrors[$logical] ?? '');
            if ($mirror !== '' && $mirror !== $uids[0]) {
                $uids[] = $mirror;
            }
            $out[$logical] = array_values(array_unique($uids));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function store(array $patch): void
    {
        Storage::disk('local')->put(
            self::FILE,
            json_encode(array_merge(self::state(), $patch), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @param  array{products?: string, brands?: string, taxonomies?: string}  $mirrors
     */
    public static function setMirrors(array $mirrors): void
    {
        self::store(['mirrors' => $mirrors]);
    }

    public static function clearMirrors(): void
    {
        self::store(['mirrors' => []]);
    }

    public static function setBarrier(bool $on): void
    {
        self::store(['barrier' => $on]);
    }

    public static function barrier(): bool
    {
        return (bool) (self::state()['barrier'] ?? false);
    }

    public static function prefix(): string
    {
        return (string) config('smart-search.index_prefix');
    }
}

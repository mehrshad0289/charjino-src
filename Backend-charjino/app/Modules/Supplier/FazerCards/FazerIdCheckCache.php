<?php

namespace App\Modules\Supplier\FazerCards;

use Illuminate\Support\Facades\Cache;

final class FazerIdCheckCache
{
    public const TTL = 180;

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function key(string $kind, string $categoryId, array $fields): string
    {
        ksort($fields);

        return 'fazer:id-check:'.$kind.':'.hash('sha256', $categoryId.'|'.json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        $hit = Cache::get($key);

        return is_array($hit) ? $hit : null;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function put(string $key, array $value): void
    {
        Cache::put($key, $value, self::TTL);
    }
}

<?php

namespace App\Support;

final class ShopOrigin
{
    public static function allowed(?string $origin): ?string
    {
        $value = rtrim(trim((string) $origin), '/');
        if ($value === '' || ! preg_match('#^https?://#i', $value)) {
            return null;
        }

        foreach (config('charjino.cors_origins', []) as $item) {
            if (! is_string($item)) {
                continue;
            }
            if (rtrim($item, '/') === $value) {
                return $value;
            }
        }

        $shop = rtrim((string) config('charjino.shop_url'), '/');
        if ($shop !== '' && $shop === $value) {
            return $value;
        }

        return null;
    }
}

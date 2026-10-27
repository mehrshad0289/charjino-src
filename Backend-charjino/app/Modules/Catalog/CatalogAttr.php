<?php

namespace App\Modules\Catalog;

final class CatalogAttr
{
    public const SHOP_QTY_CAP = 100;

    /**
     * @var list<string>
     */
    private const IGNORED_REGIONS = [
        'ir',
        'iran',
        'irn',
        'ایران',
        'islamic republic of iran',
    ];

    public static function normalize(?string $value): string
    {
        return trim((string) $value);
    }

    public static function multiline(?string $value): ?string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));

        return $text !== '' ? $text : null;
    }

    public static function isIgnoredRegion(?string $value): bool
    {
        $needle = mb_strtolower(self::normalize($value));

        return $needle !== '' && in_array($needle, self::IGNORED_REGIONS, true);
    }

    public static function publicPlatform(?string $value): ?string
    {
        $value = self::normalize($value);
        if ($value === '' || strcasecmp($value, 'all') === 0) {
            return null;
        }

        return GameKeyPlatform::canonical($value) ?? $value;
    }

    public static function publicRegion(?string $value): ?string
    {
        $value = self::normalize($value);
        if ($value === '' || self::isIgnoredRegion($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function uniqueSorted(array $values, bool $regions = false): array
    {
        $seen = [];
        foreach ($values as $raw) {
            if (! is_scalar($raw)) {
                continue;
            }
            $value = self::normalize((string) $raw);
            if ($value === '') {
                continue;
            }
            if ($regions && self::isIgnoredRegion($value)) {
                continue;
            }
            $key = mb_strtolower($value);
            if (! isset($seen[$key])) {
                $seen[$key] = $value;
            }
        }
        $out = array_values($seen);
        usort($out, 'strcasecmp');

        return $out;
    }

    public static function minOrderQty(?int $min): int
    {
        return max(1, $min ?: 1);
    }

    public static function maxOrderQty(?int $max, int $min = 1): int
    {
        $raw = $max !== null && $max > 0 ? $max : self::SHOP_QTY_CAP;
        $capped = min(self::SHOP_QTY_CAP, $raw);

        return max($min, $capped);
    }
}

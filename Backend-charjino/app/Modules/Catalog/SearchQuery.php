<?php

namespace App\Modules\Catalog;

use App\Modules\SmartSearch\PersianTextNormalizer;

final class SearchQuery
{
    public static function normalize(string $raw): string
    {
        return PersianTextNormalizer::normalize($raw);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $normalized): array
    {
        $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2) {
                continue;
            }
            $out[mb_strtolower($part)] = mb_strtolower($part);
        }

        return array_values($out);
    }

    public static function requiredHits(int $tokenCount): int
    {
        if ($tokenCount <= 0) {
            return 0;
        }
        if ($tokenCount <= 2) {
            return $tokenCount;
        }

        return 2;
    }

    public static function likeContains(string $needle): string
    {
        return '%'.addcslashes($needle, '%_\\').'%';
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return mb_stripos($haystack, $needle) === 0;
    }

    public static function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return mb_stripos($haystack, $needle) !== false;
    }
}

<?php

namespace App\Modules\Catalog;

use App\Models\CatalogGroup;
use Illuminate\Support\Str;

final class CatalogSlug
{
    public static function base(string $source): string
    {
        $trimmed = trim($source);
        if ($trimmed === '') {
            return 'category';
        }

        if (preg_match('/گیفت/u', $trimmed) || preg_match('/gift/i', $trimmed)) {
            return 'gift-card';
        }
        if (preg_match('/کلید/u', $trimmed) || preg_match('/game\s*key/i', $trimmed)) {
            return 'game-keys';
        }
        if (preg_match('/تلگرام/u', $trimmed) || preg_match('/telegram/i', $trimmed)) {
            return 'telegram';
        }
        if (preg_match('/استیم/u', $trimmed) || preg_match('/steam/i', $trimmed)) {
            return 'steam-wallet';
        }
        if (preg_match('/شارژ/u', $trimmed)) {
            return 'game-topup';
        }

        $slug = Str::slug($trimmed);

        return $slug !== '' ? $slug : 'category';
    }

    public static function unique(string $source, ?int $hint = null, ?int $ignoreId = null): string
    {
        $base = self::base($source);
        $candidate = $hint ? $base.'-'.$hint : $base;
        $slug = $candidate;
        $n = 2;

        while (
            CatalogGroup::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
                ->exists()
        ) {
            $slug = $candidate.'-'.$n;
            $n++;
        }

        return $slug;
    }
}

<?php

namespace App\Modules\Catalog;

use App\Modules\Supplier\FazerCards\FazerCardsMedia;

final class CatalogMedia
{
    public static function publicSrc(?string $raw, string $empty = '/image/catalog/pubg-mobile.svg'): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return $empty;
        }
        if (str_starts_with($value, '/')) {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/storage/')) {
            return $path;
        }

        return $value;
    }

    public static function catalogSrc(?string $raw): string
    {
        $value = trim((string) $raw);
        if ($value === '' || str_contains($value, 'pubg-mobile.svg')) {
            return '';
        }
        if (str_starts_with($value, '/image/') || str_starts_with($value, '/storage/')) {
            return $value;
        }
        if (str_starts_with($value, '/')) {
            return FazerCardsMedia::absolute($value);
        }

        return self::publicSrc($value, '');
    }
}

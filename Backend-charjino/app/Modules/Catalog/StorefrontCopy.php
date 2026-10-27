<?php

namespace App\Modules\Catalog;

final class StorefrontCopy
{
    public static function groupTitle(?string $title, ?string $name = null): string
    {
        $title = trim((string) $title);
        $name = trim((string) $name);
        if (self::isGenericGroupTitle($title) && $name !== '' && ! self::isGenericGroupTitle($name)) {
            return $name;
        }

        return $title !== '' ? $title : $name;
    }

    public static function productTitle(?string $title, ?string $groupTitle = null): string
    {
        $title = trim((string) $title);
        $groupTitle = trim((string) $groupTitle);
        if (self::isGenericProductTitle($title) && $groupTitle !== '') {
            return $groupTitle;
        }

        return $title !== '' ? $title : $groupTitle;
    }

    public static function publisher(?string $publisher): string
    {
        $publisher = trim((string) $publisher);
        if ($publisher === '' || preg_match('/shop2|fazercards|fazer\s*cards/i', $publisher) === 1) {
            return '';
        }

        return $publisher;
    }

    public static function isGenericGroupTitle(?string $title): bool
    {
        $title = trim((string) $title);

        return $title === ''
            || $title === 'شارژ بازی'
            || (bool) preg_match('/^(game\s*top-?ups?|top-?ups?)$/i', $title);
    }

    public static function isGenericProductTitle(?string $title): bool
    {
        $title = trim((string) $title);

        return $title === '' || (bool) preg_match('/^direct[\s\-]*top[\s\-]*up(\s+(sg|my))?$/i', $title);
    }
}

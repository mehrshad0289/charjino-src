<?php

namespace App\Modules\SmartSearch;

final class MeiliFilter
{
    public static function isSafeSlug(?string $value): bool
    {
        return is_string($value) && $value !== '' && preg_match('/^[a-z0-9][a-z0-9_\-]*$/i', $value) === 1;
    }

    public static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /**
     * @param  list<string>  $parts
     */
    public static function and(array $parts): ?string
    {
        $parts = array_values(array_filter($parts, fn ($part) => is_string($part) && $part !== ''));

        return $parts === [] ? null : implode(' AND ', $parts);
    }

    public static function equals(string $attribute, string $value): ?string
    {
        if (! self::isSafeSlug($attribute) || ! self::isSafeSlug($value)) {
            return null;
        }

        return $attribute.' = '.self::quote($value);
    }

    /**
     * @param  list<string>  $values
     */
    public static function notIn(string $attribute, array $values): ?string
    {
        if (! self::isSafeSlug($attribute)) {
            return null;
        }
        $quoted = [];
        foreach ($values as $value) {
            if (self::isSafeSlug($value)) {
                $quoted[] = self::quote($value);
            }
        }
        if ($quoted === []) {
            return null;
        }

        return $attribute.' NOT IN ['.implode(', ', $quoted).']';
    }
}

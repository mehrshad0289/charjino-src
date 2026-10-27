<?php

namespace App\Support;

final class Utf8
{
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    public static function string(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return is_string($value) ? $value : '';
        }
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        $converted = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return is_string($converted) ? $converted : '';
    }

    public static function deep(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::string($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $safeKey = is_string($key) ? self::string($key) : $key;
                $out[$safeKey] = self::deep($item);
            }

            return $out;
        }

        return $value;
    }
}

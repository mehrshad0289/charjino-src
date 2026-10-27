<?php

namespace App\Support;

final class Phone
{
    public static function onlyDigits(string $value): string
    {
        $mapped = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return preg_replace('/\D+/', '', $mapped) ?? '';
    }

    /** خروجی قرارداد فرانت: `09XXXXXXXXX` */
    public static function normalizeIranianMobile(string $value): string
    {
        $digits = self::onlyDigits($value);

        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        $national = substr($digits, 0, 10);

        return preg_match('/^9\d{9}$/', $national) ? '0'.$national : $national;
    }

    public static function isIranianMobile(string $value): bool
    {
        return (bool) preg_match('/^09\d{9}$/', self::normalizeIranianMobile($value));
    }

    public static function mask(string $phone): string
    {
        $normalized = self::normalizeIranianMobile($phone);
        if (strlen($normalized) < 7) {
            return '****';
        }

        return substr($normalized, 0, 4).'***'.substr($normalized, -2);
    }
}

<?php

namespace App\Support;

final class DigitNormalizer
{
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function normalize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        $value = str_replace(self::PERSIAN_DIGITS, self::LATIN_DIGITS, $value);
        $value = str_replace(self::ARABIC_DIGITS, self::LATIN_DIGITS, $value);
        $value = preg_replace('/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{00A0}\x{202F}]/u', '', $value) ?? '';

        return preg_replace_callback('/\p{Nd}/u', static function (array $matches): string {
            $char = $matches[0];
            $code = mb_ord($char, 'UTF-8');
            if ($code >= 0x30 && $code <= 0x39) {
                return $char;
            }
            foreach ([0x0660, 0x06F0, 0xFF10, 0x0966, 0x09E6] as $base) {
                if ($code >= $base && $code <= $base + 9) {
                    return (string) ($code - $base);
                }
            }

            return $char;
        }, $value) ?? $value;
    }

    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', self::normalize($value)) ?? '';
    }

    public static function iranMobile(?string $value): string
    {
        $digits = self::digits($value);
        if (str_starts_with($digits, '98') && strlen($digits) >= 12) {
            $digits = '0'.substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }
}

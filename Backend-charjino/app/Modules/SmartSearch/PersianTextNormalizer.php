<?php

namespace App\Modules\SmartSearch;

final class PersianTextNormalizer
{
    public static function normalize(string $raw): string
    {
        $text = trim($raw);
        $text = preg_replace('/[\x{200C}\x{200E}\x{200F}\x{00A0}\x{202F}\s]+/u', ' ', $text) ?? '';
        $text = str_replace(['ي', 'ى'], 'ی', $text);
        $text = str_replace('ك', 'ک', $text);
        $text = str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $text,
        );
        $text = preg_replace('/\p{M}+/u', '', $text) ?? '';
        $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }
}

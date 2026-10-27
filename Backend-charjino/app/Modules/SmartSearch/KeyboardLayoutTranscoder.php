<?php

namespace App\Modules\SmartSearch;

final class KeyboardLayoutTranscoder
{
    private const MAP = [
        'q' => 'ض', 'w' => 'ص', 'e' => 'ث', 'r' => 'ق', 't' => 'ف', 'y' => 'غ', 'u' => 'ع',
        'i' => 'ه', 'o' => 'خ', 'p' => 'ح', '[' => 'ج', ']' => 'چ', 'a' => 'ش', 's' => 'س',
        'd' => 'ی', 'f' => 'ب', 'g' => 'ل', 'h' => 'ا', 'j' => 'ت', 'k' => 'ن', 'l' => 'م',
        ';' => 'ک', "'" => 'گ', 'z' => 'ظ', 'x' => 'ط', 'c' => 'ز', 'v' => 'ر', 'b' => 'ذ',
        'n' => 'د', 'm' => 'پ', ',' => 'و',
    ];

    public static function toPersian(string $latin): string
    {
        $out = '';
        $chars = preg_split('//u', mb_strtolower($latin), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $out .= self::MAP[$ch] ?? $ch;
        }

        return PersianTextNormalizer::normalize($out);
    }

    public static function shouldRetry(string $normalized): bool
    {
        if (mb_strlen($normalized) < 2) {
            return false;
        }

        return (bool) preg_match('/[A-Za-z]{2,}/u', $normalized);
    }

    /**
     * وقتی کاربر فارسی را با کیبورد انگلیسی زده: فاصله یا کلیدهای مخصوص چیدمان فارسی.
     * acronymهایی مثل pubg/GTA را دست نمی‌زنیم.
     */
    public static function shouldPreferMapped(string $raw): bool
    {
        $raw = trim($raw);
        if ($raw === '' || ! self::shouldRetry(PersianTextNormalizer::normalize($raw))) {
            return false;
        }
        $mapped = self::toPersian($raw);
        if ($mapped === '' || mb_strlen($mapped) < 2 || ! preg_match('/\p{Arabic}/u', $mapped)) {
            return false;
        }
        if (preg_match('/\s/u', $raw) === 1) {
            return true;
        }

        return preg_match('/[;\'\[\],]/u', $raw) === 1;
    }
}

<?php

namespace App\Support;

final class Money
{
    public const TOMAN_STEP = 1000;

    /** Sale must be at least 30% of list (max 70% off). */
    public const MIN_SALE_NUMERATOR = 3;

    public const MIN_SALE_DENOMINATOR = 10;

    public static function string(mixed $value): string
    {
        if ($value === null) {
            return '0';
        }
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed !== '' ? $trimmed : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $asString = trim((string) $value);

        return $asString !== '' ? $asString : '0';
    }

    public static function roundToman(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        $rounded = (int) (round($value / self::TOMAN_STEP) * self::TOMAN_STEP);

        return $rounded > 0 ? $rounded : self::TOMAN_STEP;
    }

    public static function isCredibleSale(int $sale, int $list): bool
    {
        $sale = self::roundToman($sale);
        $list = self::roundToman($list);
        if ($sale < self::TOMAN_STEP || $list < self::TOMAN_STEP || $sale >= $list) {
            return false;
        }

        return $sale * self::MIN_SALE_DENOMINATOR >= $list * self::MIN_SALE_NUMERATOR;
    }

    public static function compare(mixed $left, mixed $right): int
    {
        $a = self::parts($left);
        $b = self::parts($right);

        if ($a['neg'] !== $b['neg']) {
            return $a['neg'] ? -1 : 1;
        }
        if (strlen($a['int']) !== strlen($b['int'])) {
            $cmp = strlen($a['int']) > strlen($b['int']) ? 1 : -1;

            return $a['neg'] ? -$cmp : $cmp;
        }
        if ($a['int'] !== $b['int']) {
            $cmp = $a['int'] > $b['int'] ? 1 : -1;

            return $a['neg'] ? -$cmp : $cmp;
        }

        $width = max(strlen($a['frac']), strlen($b['frac']));
        $lf = str_pad($a['frac'], $width, '0');
        $rf = str_pad($b['frac'], $width, '0');
        if ($lf === $rf) {
            return 0;
        }
        $cmp = $lf > $rf ? 1 : -1;

        return $a['neg'] ? -$cmp : $cmp;
    }

    public static function isPositive(mixed $value): bool
    {
        return self::compare($value, '0') > 0;
    }

    /**
     * @return array{neg: bool, int: string, frac: string}
     */
    private static function parts(mixed $value): array
    {
        $raw = self::string($value);
        $neg = false;
        if (str_starts_with($raw, '+')) {
            $raw = substr($raw, 1);
        }
        if (str_starts_with($raw, '-')) {
            $neg = true;
            $raw = substr($raw, 1);
        }

        [$intRaw, $fracRaw] = array_pad(explode('.', $raw, 2), 2, '');
        $int = preg_replace('/\D+/', '', ltrim($intRaw, '0')) ?: '0';
        $frac = preg_replace('/\D+/', '', $fracRaw) ?? '';
        if ($int === '0' && ! preg_match('/[1-9]/', $frac)) {
            $neg = false;
        }

        return ['neg' => $neg, 'int' => $int, 'frac' => $frac];
    }
}

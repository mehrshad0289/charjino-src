<?php

namespace App\Modules\Supplier\FazerCards;

final class FazerCardsRedactor
{
    /**
     * Mask secrets in supplier payloads before they leave the admin API.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function scrub(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            $name = strtolower((string) $key);
            if (self::isSecretKey($name)) {
                $out[$key] = self::mask($value);

                continue;
            }
            if (is_array($value)) {
                $out[$key] = self::scrub($value);

                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private static function isSecretKey(string $name): bool
    {
        return (bool) preg_match('/(api_?key|secret|password|passwd|token|authorization|cards|keys|invite_url|steamlogin|telegram_username)/i', $name)
            || str_starts_with($name, 'fields.');
    }

    public static function maskTail(string $value, int $keep = 4): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $keep = min($keep, 4);
        $tail = substr($value, -$keep);

        return '****'.$tail;
    }

    private static function mask(mixed $value): mixed
    {
        if (! is_scalar($value) || $value === null) {
            return '[redacted]';
        }
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        $tail = substr($text, -4);

        return '****'.$tail;
    }
}

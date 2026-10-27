<?php

namespace App\Modules\Fulfillment;

final class FazerCardsWebhookSignature
{
    public static function expected(string $rawBody, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $rawBody, $secret);
    }

    public static function matches(string $rawBody, string $header, string $secret): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }
        $header = trim($header);
        $expected = self::expected($rawBody, $secret);
        if (hash_equals($expected, $header)) {
            return true;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $header);
    }
}

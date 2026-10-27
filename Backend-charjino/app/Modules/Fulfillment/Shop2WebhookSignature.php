<?php

namespace App\Modules\Fulfillment;

final class Shop2WebhookSignature
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

        return hash_equals(self::expected($rawBody, $secret), $header);
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class SafeLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info($message, self::scrub($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::warning($message, self::scrub($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = []): void
    {
        Log::error($message, self::scrub($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function scrub(array $context): array
    {
        $blocked = [
            's2t_key', 's2t_webhook_secret', 'webhook_secret', 'api_key', 'apikey',
            'authorization', 'password', 'passwd', 'otp', 'pin', 'secret', 'token',
            'refresh_token', 'access_token', 'requirements', 'raw', 'payload',
            'merchant_id', 'merchant', 'zarinpal_merchant_id',
        ];

        $clean = [];
        foreach ($context as $key => $value) {
            $name = strtolower((string) $key);
            if (in_array($name, $blocked, true) || preg_match('/pass|otp|pin|secret|token|key$/i', (string) $key)) {
                $clean[$key] = '[redacted]';

                continue;
            }
            if (is_array($value)) {
                $clean[$key] = self::scrub($value);

                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}

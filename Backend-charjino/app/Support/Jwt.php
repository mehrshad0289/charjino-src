<?php

namespace App\Support;

use App\Exceptions\ApiException;
use JsonException;

final class Jwt
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function encode(array $payload, string $secret, int $ttlSeconds): string
    {
        $now = time();
        $claims = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ]);

        try {
            $header = self::b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $body = self::b64url(json_encode($claims, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw new ApiException('صدور توکن انجام نشد.', 500);
        }

        $signature = self::b64url(hash_hmac('sha256', $header.'.'.$body, $secret, true));

        return $header.'.'.$body.'.'.$signature;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }

        [$header, $body, $signature] = $parts;
        $expected = self::b64url(hash_hmac('sha256', $header.'.'.$body, $secret, true));
        if (! hash_equals($expected, $signature)) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }

        try {
            $payload = json_decode(self::b64urlDecode($body), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }

        if (! is_array($payload) || (int) ($payload['exp'] ?? 0) < time()) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }

        return $payload;
    }

    private static function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }

        return $decoded;
    }
}

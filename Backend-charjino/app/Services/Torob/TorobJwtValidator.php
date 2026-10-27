<?php

namespace App\Services\Torob;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

class TorobJwtValidator
{
    public function validate(?string $token, ?string $tokenVersion): void
    {
        if (config('torob.jwt_skip')) {
            return;
        }

        if ($token === null || $token === '') {
            throw new UnexpectedValueException('missing X-Torob-Token');
        }

        if ($tokenVersion !== null && $tokenVersion !== '' && $tokenVersion !== '1') {
            throw new UnexpectedValueException('unsupported X-Torob-Token-Version');
        }

        $decoded = JWT::decode($token, new Key($this->verifyKey(), 'EdDSA'));
        $audience = (string) config('torob.jwt_audience');
        $tokenAud = $decoded->aud ?? null;
        $audList = is_array($tokenAud) ? $tokenAud : [$tokenAud];

        if (! in_array($audience, $audList, true)) {
            throw new UnexpectedValueException('invalid audience');
        }
    }

    private function verifyKey(): string
    {
        $override = trim((string) config('torob.jwt_verify_key'));
        if ($override !== '') {
            return $override;
        }

        $publicKey = (string) config('torob.public_key');
        $decoded = base64_decode($publicKey, true);
        if (! is_string($decoded) || strlen($decoded) < 32) {
            throw new UnexpectedValueException('invalid token');
        }

        return base64_encode(substr($decoded, -32));
    }
}

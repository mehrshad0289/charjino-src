<?php

namespace App\Modules\Catalog;

use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsSeeds;

final class CatalogExclusion
{
    public const MANUAL = 'manual';

    public const LOGIN_REQUIRED = 'login_required';

    public const CREDENTIAL_FIELD = 'credential_field';

    public const FUNPAY = 'funpay';

    public const ADULT_CONTENT = 'adult_content';

    /**
     * @param  list<string|array<string, mixed>>  $fields
     * @param  array<string, mixed>  $row
     */
    public static function reason(string $family, string $name, array $fields = [], array $row = []): ?string
    {
        if ($family === FazerCardsIds::MANUAL) {
            return self::MANUAL;
        }
        if ($family === FazerCardsIds::FUNPAY) {
            return self::FUNPAY;
        }
        if (self::isLoginProduct($name, $row)) {
            return self::LOGIN_REQUIRED;
        }
        if (! empty($row['credential_fields'])) {
            return self::CREDENTIAL_FIELD;
        }
        foreach ($fields as $field) {
            if (self::isCredentialKey(self::fieldKey($field))) {
                return self::CREDENTIAL_FIELD;
            }
        }

        return null;
    }

    /**
     * VPN / پروکسی / کش‌اوت: اصلاً وارد کاتالوگ نشوند.
     *
     * @param  array<string, mixed>  $row
     */
    public static function shouldSkipIngest(string $name, array $row = []): bool
    {
        $hay = $name.' '.self::rowHaystack($row);

        return FazerCardsSeeds::isForbiddenStorefront($hay) || SteamGiftBlocklist::matches($hay);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function isLoginProduct(string $name, array $row = []): bool
    {
        if (self::supplierSaysLogin($row)) {
            return true;
        }

        foreach (preg_split('/\s+/', strtolower(trim(self::identityHaystack($row)))) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            if (
                $token === 'login'
                || str_ends_with($token, '_login')
                || str_ends_with($token, '-login')
                || str_contains($token, 'via_login')
                || str_contains($token, 'via-login')
                || str_contains($token, 'login_required')
            ) {
                return true;
            }
        }

        $title = trim($name);
        if ($title === '') {
            return false;
        }
        if (preg_match('/(?:^|[^a-z0-9])[a-z0-9]+(?:-[a-z0-9]+)*-login(?:$|[^a-z0-9])/i', $title) === 1) {
            return true;
        }
        if (preg_match('/(?:^|[^a-z0-9])[a-z0-9]+(?:_[a-z0-9]+)*_login(?:$|[^a-z0-9])/i', $title) === 1) {
            return true;
        }

        return (bool) preg_match(
            '/via\s+login|login\s+required|\(\s*login\s*\)|auto\s+via\s+login/i',
            $title,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function supplierSaysLogin(array $row): bool
    {
        foreach (['login_required', 'via_login', 'requires_login'] as $flag) {
            $value = $row[$flag] ?? null;
            if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                return true;
            }
        }
        foreach (['delivery_type', 'delivery', 'fulfillment', 'auth_type', 'type'] as $key) {
            $value = strtolower(trim((string) ($row[$key] ?? '')));
            if (in_array($value, ['login', 'via_login', 'via-login', 'login_required'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function identityHaystack(array $row): string
    {
        $parts = [];
        foreach (['category_id', 'id', 'slug', 'handle'] as $key) {
            $value = $row[$key] ?? null;
            $text = is_array($value) ? trim((string) ($value['id'] ?? $value['name'] ?? '')) : trim((string) $value);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    public static function isCredentialKey(string $key): bool
    {
        $key = strtolower(trim($key));
        if ($key === '' || in_array($key, ['steamlogin', 'steam_login'], true)) {
            return false;
        }
        if ($key === 'email') {
            return true;
        }

        return (bool) preg_match('/pass|otp|pin|login/i', $key);
    }

    public static function fieldKey(mixed $field): string
    {
        if (is_string($field) || is_int($field)) {
            return trim((string) $field);
        }
        if (! is_array($field)) {
            return '';
        }

        return trim((string) ($field['key'] ?? $field['name'] ?? $field['code'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowHaystack(array $row): string
    {
        $parts = [];
        foreach (['category_id', 'id', 'slug', 'name', 'GameName', 'title'] as $key) {
            $value = $row[$key] ?? null;
            if (is_array($value)) {
                $value = $value['name'] ?? '';
            }
            $text = trim((string) $value);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }
}

<?php

namespace App\Modules\Orders;

use App\Exceptions\ApiException;
use App\Models\Product;
use App\Modules\Catalog\CatalogExclusion;
use App\Modules\Catalog\CodmCatalog;
use App\Modules\Catalog\PurchaseSchema;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftQuotes;
use App\Support\CustomerError;

final class PurchaseInputValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function validate(Product $product, array $input): array
    {
        $family = FazerCardsIds::familyOf($product->fulfillment_type, $product->fazercards_category_id);
        $schema = is_array($product->purchase_schema) ? $product->purchase_schema : PurchaseSchema::build($family, is_array($product->inputs) ? $product->inputs : []);
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        $allowed = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = trim((string) ($field['key'] ?? $field['name'] ?? ''));
            if ($key === '') {
                continue;
            }
            $allowed[$key] = $field;
        }
        if ($family === FazerCardsIds::STEAM_TOPUP) {
            $allowed['currency'] = ['key' => 'currency', 'required' => false];
            $allowed['amount'] = ['key' => 'amount', 'required' => false];
        }

        $input = self::aliasLegacyKeys($input, $allowed);

        $out = [];
        foreach ($input as $rawKey => $value) {
            $key = trim((string) $rawKey);
            if ($key === '') {
                continue;
            }
            if (CatalogExclusion::isCredentialKey($key)) {
                throw new ApiException(CustomerError::message('FORBIDDEN_BUYER_FIELD'), 400, 'FORBIDDEN_BUYER_FIELD');
            }
            if (! isset($allowed[$key])) {
                throw new ApiException(CustomerError::message('FORBIDDEN_BUYER_FIELD'), 400, 'FORBIDDEN_BUYER_FIELD');
            }
            if (! is_scalar($value)) {
                throw new ApiException(CustomerError::message('INVALID_FORMAT'), 400, 'INVALID_FORMAT');
            }
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $out[$key] = self::normalizeValue($key, $text);
            if (! in_array($key, ['invite_url', 'currency', 'amount', 'character_name', 'riot_id'], true)) {
                $out[$key] = preg_replace('/\s+/u', '', $out[$key]) ?? $out[$key];
            }
        }

        foreach ($allowed as $key => $field) {
            $required = (bool) ($field['required'] ?? true);
            if ($required && (! isset($out[$key]) || $out[$key] === '')) {
                throw new ApiException(self::missingMessage($key), 400, self::missingCode($key));
            }
            if (! isset($out[$key])) {
                continue;
            }
            if (CodmCatalog::isProduct($product) && ! in_array($key, ['currency', 'amount'], true)) {
                $out[$key] = CodmCatalog::digitsOnly($out[$key]);
                if (! CodmCatalog::looksLikeId($out[$key])) {
                    throw new ApiException(CustomerError::message('INVALID_FORMAT'), 400, 'INVALID_FORMAT');
                }

                continue;
            }
            self::assertFormat($key, $out[$key], is_array($field) ? $field : []);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, array<string, mixed>>  $allowed
     * @return array<string, mixed>
     */
    private static function aliasLegacyKeys(array $input, array $allowed): array
    {
        $aliases = [
            'playerId' => 'player_id',
            'playerid' => 'player_id',
            'username' => 'telegram_username',
            'steam_login' => 'steamLogin',
            'inviteUrl' => 'invite_url',
        ];
        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $input) && ! array_key_exists($to, $input) && isset($allowed[$to])) {
                $input[$to] = $input[$from];
                unset($input[$from]);
            }
        }

        return $input;
    }

    public static function normalizeValue(string $key, string $value): string
    {
        if ($key === 'invite_url') {
            return FazerCardsSteamGiftQuotes::normalizeInviteUrl($value);
        }
        if ($key === 'telegram_username') {
            return ltrim(trim($value), '@');
        }
        if (in_array($key, ['user_id', 'uid', 'player_id', 'open_id'], true)) {
            $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            $lat = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $value = str_replace([...$fa, ...$ar], [...$lat, ...$lat], $value);

            return preg_replace('/\s+/u', '', $value) ?? $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public static function assertFormat(string $key, string $value, array $field): void
    {
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        if ($options !== []) {
            $ok = false;
            foreach ($options as $option) {
                $want = is_array($option) ? (string) ($option['value'] ?? '') : (string) $option;
                if ($want === $value) {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                throw new ApiException(CustomerError::message('INVALID_FORMAT'), 400, 'INVALID_FORMAT');
            }

            return;
        }
        $pattern = trim((string) ($field['pattern'] ?? ''));
        if ($pattern === '') {
            if (strlen($value) > 64) {
                throw new ApiException(CustomerError::message('INVALID_FORMAT'), 400, 'INVALID_FORMAT');
            }

            return;
        }
        $ok = @preg_match('~'.$pattern.'~u', $value);
        if ($ok !== 1) {
            throw new ApiException(self::formatMessage($key), 400, self::formatCode($key));
        }
        if ($key === 'invite_url' && ! FazerCardsSteamGiftQuotes::inviteAllowed($value)) {
            throw new ApiException(CustomerError::message('INVALID_INVITE_LINK'), 400, 'INVALID_INVITE_LINK');
        }
    }

    private static function missingMessage(string $key): string
    {
        return match ($key) {
            'telegram_username' => CustomerError::message('TELEGRAM_USERNAME_INVALID', 'نام کاربری تلگرام را وارد کن.'),
            'invite_url' => CustomerError::message('INVALID_INVITE_LINK'),
            'steamLogin' => 'نام کاربری ورود استیم را وارد کن.',
            'currency', 'amount' => CustomerError::message('AMOUNT_OUT_OF_RANGE'),
            default => CustomerError::message('MISSING_REQUIRED_FIELD'),
        };
    }

    private static function missingCode(string $key): string
    {
        return match ($key) {
            'telegram_username' => 'TELEGRAM_USERNAME_INVALID',
            'invite_url' => 'INVALID_INVITE_LINK',
            'currency', 'amount' => 'AMOUNT_OUT_OF_RANGE',
            default => 'MISSING_REQUIRED_FIELD',
        };
    }

    private static function formatMessage(string $key): string
    {
        return match ($key) {
            'telegram_username' => CustomerError::message('TELEGRAM_USERNAME_INVALID'),
            'invite_url' => CustomerError::message('INVALID_INVITE_LINK'),
            'user_id', 'uid' => CustomerError::message('PLAYER_ID_INVALID'),
            default => CustomerError::message('INVALID_FORMAT'),
        };
    }

    private static function formatCode(string $key): string
    {
        return match ($key) {
            'telegram_username' => 'TELEGRAM_USERNAME_INVALID',
            'invite_url' => 'INVALID_INVITE_LINK',
            'user_id', 'uid' => 'PLAYER_ID_INVALID',
            default => 'INVALID_FORMAT',
        };
    }
}

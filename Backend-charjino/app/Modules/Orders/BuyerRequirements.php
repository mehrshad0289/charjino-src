<?php

namespace App\Modules\Orders;

use App\Exceptions\ApiException;
use App\Models\Product;
use App\Modules\Catalog\CatalogExclusion;
use App\Support\CustomerError;

final class BuyerRequirements
{
    /**
     * @var array<string, string>
     */
    private const LEGACY_ALLOWED = [
        'player_id' => 'player_id',
        'playerid' => 'player_id',
        'playerId' => 'player_id',
        'username' => 'player_id',
        'user' => 'player_id',
        'uid' => 'player_id',
        'user_id' => 'user_id',
        'server' => 'server',
        'server_id' => 'server_id',
        'zone_id' => 'zone_id',
        'zoneid' => 'zone_id',
        'zoneId' => 'zone_id',
        'riot_id' => 'riot_id',
        'steamLogin' => 'steamLogin',
        'steam_login' => 'steamLogin',
        'telegram_username' => 'telegram_username',
        'invite_url' => 'invite_url',
        'inviteUrl' => 'invite_url',
        'currency' => 'currency',
        'amount' => 'amount',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function sanitize(array $input, bool $requirePlayerId = true, ?Product $product = null): array
    {
        if ($product !== null) {
            return PurchaseInputValidator::validate($product, $input);
        }

        $out = [];
        foreach ($input as $key => $value) {
            $keyStr = trim((string) $key);
            if ($keyStr === '') {
                continue;
            }
            if (CatalogExclusion::isCredentialKey($keyStr)) {
                throw new ApiException(CustomerError::message('FORBIDDEN_BUYER_FIELD'), 400, 'FORBIDDEN_BUYER_FIELD');
            }
            $normalized = self::LEGACY_ALLOWED[$keyStr] ?? self::LEGACY_ALLOWED[strtolower($keyStr)] ?? null;
            if ($normalized === null) {
                throw new ApiException(CustomerError::message('FORBIDDEN_BUYER_FIELD'), 400, 'FORBIDDEN_BUYER_FIELD');
            }
            if (! is_scalar($value)) {
                throw new ApiException(CustomerError::message('MISSING_REQUIRED_FIELD'), 400, 'MISSING_REQUIRED_FIELD');
            }
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $out[$normalized] = PurchaseInputValidator::normalizeValue($normalized, $text);
        }

        if ($requirePlayerId && self::playerId($out) === '') {
            throw new ApiException(CustomerError::message('MISSING_REQUIRED_FIELD'), 400, 'MISSING_REQUIRED_FIELD');
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $requirements
     */
    public static function playerId(array $requirements): string
    {
        foreach ([
            'player_id',
            'playerId',
            'steamLogin',
            'telegram_username',
            'username',
            'invite_url',
            'riot_id',
            'user_id',
            'uid',
            'open_id',
            'bigo_id',
        ] as $key) {
            $value = trim((string) ($requirements[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

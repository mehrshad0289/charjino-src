<?php

namespace App\Modules\Supplier\FazerCards;

final class FazerCardsIds
{
    public const TOPUP = 'topup';

    public const GIFTCARD = 'giftcard';

    public const GAMEKEY = 'gamekey';

    public const STEAM_TOPUP = 'steam-topup';

    public const STEAM_GIFT = 'steam-gift';

    public const TELEGRAM_STARS = 'telegram-stars';

    public const TELEGRAM_PREMIUM = 'telegram-premium';

    public const MANUAL = 'manual';

    public const FUNPAY = 'funpay';

    /** @var list<string> */
    public const FAMILIES = [
        self::TOPUP,
        self::GIFTCARD,
        self::GAMEKEY,
        self::STEAM_TOPUP,
        self::STEAM_GIFT,
        self::TELEGRAM_STARS,
        self::TELEGRAM_PREMIUM,
        self::MANUAL,
        self::FUNPAY,
    ];

    public static function productKey(string $family, string $remoteId): string
    {
        $remoteId = trim($remoteId);
        if ($family === self::TOPUP) {
            return mb_substr($remoteId, 0, 120);
        }

        return mb_substr($family.':'.$remoteId, 0, 120);
    }

    public static function skuCategory(string $family, string $remoteId): string
    {
        return self::productKey($family, $remoteId);
    }

    public static function rawCategory(?string $stored): string
    {
        $id = trim((string) $stored);
        foreach (self::FAMILIES as $family) {
            $prefix = $family.':';
            if (str_starts_with($id, $prefix)) {
                return substr($id, strlen($prefix));
            }
        }

        return $id;
    }

    public static function familyOf(?string $fulfillmentType, ?string $storedCategory = null): string
    {
        $type = trim((string) $fulfillmentType);
        if (in_array($type, self::FAMILIES, true)) {
            return $type;
        }
        $id = trim((string) $storedCategory);
        foreach (self::FAMILIES as $family) {
            if ($family !== self::TOPUP && str_starts_with($id, $family.':')) {
                return $family;
            }
        }

        return self::TOPUP;
    }

    public static function seenKey(string $family, string $categoryKey, string $offerId): string
    {
        return $family.'|'.$categoryKey.'|'.$offerId;
    }
}

<?php

namespace App\Modules\Catalog;

use App\Exceptions\ApiException;
use App\Models\Product;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsValidateId;

final class BuyerIdentity
{
    public const PLAYER = 'player';

    public const USER = 'user';

    public const ACCOUNT = 'account';

    /**
     * @return self::PLAYER|self::USER|self::ACCOUNT
     */
    public static function kind(?Product $product): string
    {
        $haystack = mb_strtolower(self::haystack($product));
        if ($haystack === '') {
            return self::PLAYER;
        }
        if (preg_match('/telegram|discord|whatsapp/', $haystack)) {
            return self::USER;
        }
        if (preg_match('/apple|steam|playstation|xbox|nintendo|roblox|blizzard|gift|starbucks|uber|airbnb|nexon|spotify|minecraft/', $haystack)) {
            return self::ACCOUNT;
        }

        return self::PLAYER;
    }

    public static function validateMode(Product $product): string
    {
        $type = trim((string) $product->fulfillment_type);
        if ($type === 'steam-topup') {
            return 'api';
        }
        if (in_array($type, ['giftcard', 'gamekey', 'steam-gift', 'telegram-stars', 'telegram-premium', 'manual'], true)) {
            return 'manual';
        }
        $fazercards = trim((string) $product->fazercards_category_id);
        if ($fazercards !== '') {
            return FazerCardsValidateId::mode(FazerCardsIds::rawCategory($fazercards));
        }

        return 'api';
    }

    public static function fieldLabel(string $kind): string
    {
        return match ($kind) {
            self::USER => 'نام کاربری',
            self::ACCOUNT => 'شناسه حساب',
            default => 'شناسه بازیکن',
        };
    }

    public static function checkLabel(string $kind): string
    {
        return match ($kind) {
            self::USER => 'بررسی کاربر',
            self::ACCOUNT => 'بررسی حساب',
            default => 'بررسی بازیکن',
        };
    }

    public static function resultTitle(string $kind): string
    {
        return match ($kind) {
            self::USER => 'کاربر پیدا شد',
            self::ACCOUNT => 'حساب پیدا شد',
            default => 'بازیکن پیدا شد',
        };
    }

    public static function resultHint(string $kind): string
    {
        return match ($kind) {
            self::USER => 'نام کاربری معتبر است. می‌توانی پرداخت را ادامه دهی.',
            self::ACCOUNT => 'شناسه حساب معتبر است. می‌توانی پرداخت را ادامه دهی.',
            default => 'شناسه بازیکن معتبر است. می‌توانی پرداخت را ادامه دهی.',
        };
    }

    public static function missingMessage(string $kind): string
    {
        return match ($kind) {
            self::USER => 'نام کاربری را وارد کن.',
            self::ACCOUNT => 'شناسه حساب را وارد کن.',
            default => 'شناسه بازیکن را وارد کن.',
        };
    }

    public static function notFoundMessage(string $kind): string
    {
        return match ($kind) {
            self::USER => 'این نام کاربری پیدا نشد.',
            self::ACCOUNT => 'این شناسهٔ حساب پیدا نشد.',
            default => 'این شناسهٔ بازیکن پیدا نشد.',
        };
    }

    public static function displayName(string $kind, string $id, ?string $remoteName = null): string
    {
        $remote = trim((string) $remoteName);
        if ($remote !== '' && ! str_starts_with($remote, 'بازیکن آزمایشی')) {
            return $remote;
        }
        $id = trim($id);
        if ($kind === self::USER) {
            $user = ltrim($id, '@');

            return $user !== '' ? '@'.$user : $id;
        }

        return $id;
    }

    public static function assertLooksValid(string $kind, string $id): void
    {
        $id = trim($id);
        if ($id === '') {
            throw new ApiException(self::missingMessage($kind), 400, 'MISSING_REQUIRED_FIELD');
        }

        $ok = match ($kind) {
            self::USER => self::looksLikeUser($id),
            self::ACCOUNT => self::looksLikeAccount($id),
            default => self::looksLikePlayer($id),
        };
        if (! $ok) {
            throw new ApiException(self::notFoundMessage($kind), 400, 'PLAYER_NOT_FOUND');
        }
    }

    private static function looksLikeUser(string $id): bool
    {
        $user = ltrim($id, '@');
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{4,31}$/', $user) === 1) {
            return true;
        }

        return preg_match('/^[0-9]{5,15}$/', $user) === 1;
    }

    private static function looksLikePlayer(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9._\-]{3,32}$/', $id) === 1;
    }

    private static function looksLikeAccount(string $id): bool
    {
        if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        if (preg_match('#^https://[^\s]{8,500}$#i', $id) === 1) {
            return true;
        }

        return preg_match('/^[A-Za-z0-9@._\-]{3,64}$/', $id) === 1;
    }

    private static function haystack(?Product $product): string
    {
        if (! $product) {
            return '';
        }
        $group = $product->relationLoaded('catalogGroup') ? $product->catalogGroup : null;

        return implode(' ', array_filter([
            (string) $product->title,
            (string) $product->name_en,
            (string) $product->handle,
            (string) $product->category_name,
            (string) $group?->title,
            (string) $group?->name,
            (string) $group?->slug,
        ]));
    }
}

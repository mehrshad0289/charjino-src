<?php

namespace App\Modules\Supplier\FazerCards;

final class FazerCardsCatalogScopes
{
    public const ALL = 'all';

    /**
     * @return list<array{id: string, title: string, titleEn: string, phases: list<string>, families: list<string>}>
     */
    public static function catalog(): array
    {
        return [
            [
                'id' => 'steam-topup',
                'title' => 'شارژ استیم',
                'titleEn' => 'Steam Top-Up (CIS)',
                'phases' => ['steam-topup'],
                'families' => [FazerCardsIds::STEAM_TOPUP],
            ],
            [
                'id' => 'steam-gifts',
                'title' => 'گیفت استیم',
                'titleEn' => 'Steam Gift Games',
                'phases' => ['steam-gifts'],
                'families' => [FazerCardsIds::STEAM_GIFT],
            ],
            [
                'id' => 'telegram',
                'title' => 'تلگرام (ستاره)',
                'titleEn' => 'Telegram stars',
                'phases' => ['telegram'],
                'families' => [FazerCardsIds::TELEGRAM_STARS, FazerCardsIds::TELEGRAM_PREMIUM],
            ],
            [
                'id' => 'gamekeys',
                'title' => 'کلید بازی',
                'titleEn' => 'Game Keys',
                'phases' => ['gamekeys'],
                'families' => [FazerCardsIds::GAMEKEY],
            ],
            [
                'id' => 'funpay',
                'title' => 'FunPay',
                'titleEn' => 'FunPay panel',
                'phases' => ['funpay'],
                'families' => [FazerCardsIds::FUNPAY],
            ],
            [
                'id' => 'manual',
                'title' => 'سرویس دستی',
                'titleEn' => 'Manual Services',
                'phases' => ['manual'],
                'families' => [FazerCardsIds::MANUAL],
            ],
            [
                'id' => 'giftcards',
                'title' => 'گیفت کارت',
                'titleEn' => 'Gift cards',
                'phases' => ['giftcards'],
                'families' => [FazerCardsIds::GIFTCARD],
            ],
            [
                'id' => 'topups',
                'title' => 'شارژ بازی',
                'titleEn' => 'Service top-up',
                'phases' => ['topups'],
                'families' => [FazerCardsIds::TOPUP],
            ],
        ];
    }

    /**
     * @return list<array{id: string, title: string, titleEn: string}>
     */
    public static function publicList(): array
    {
        return array_map(
            fn (array $row) => [
                'id' => $row['id'],
                'title' => $row['title'],
                'titleEn' => $row['titleEn'],
            ],
            self::catalog(),
        );
    }

    /**
     * @return array{id: string, title: string, titleEn: string, phases: list<string>, families: list<string>|null}
     */
    public static function resolve(?string $id): array
    {
        $normalized = self::normalize($id);
        if ($normalized === null || $normalized === self::ALL) {
            return [
                'id' => self::ALL,
                'title' => 'همهٔ دسته‌ها',
                'titleEn' => 'All catalog',
                'phases' => ['topups', 'giftcards', 'gamekeys', 'telegram', 'steam-topup', 'funpay', 'manual', 'finalize'],
                'families' => null,
            ];
        }

        foreach (self::catalog() as $row) {
            if ($row['id'] === $normalized) {
                return $row;
            }
        }

        throw new \InvalidArgumentException($normalized);
    }

    public static function normalize(?string $id): ?string
    {
        $value = strtolower(trim((string) $id));
        if ($value === '' || $value === self::ALL) {
            return $value === self::ALL ? self::ALL : null;
        }
        foreach (self::catalog() as $row) {
            if ($row['id'] === $value) {
                return $value;
            }
        }

        return $value;
    }

    public static function isKnown(?string $id): bool
    {
        $value = strtolower(trim((string) $id));
        if ($value === '' || $value === self::ALL) {
            return true;
        }
        foreach (self::catalog() as $row) {
            if ($row['id'] === $value) {
                return true;
            }
        }

        return false;
    }
}

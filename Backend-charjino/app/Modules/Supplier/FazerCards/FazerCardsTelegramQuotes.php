<?php

namespace App\Modules\Supplier\FazerCards;

use App\Support\Money;

final class FazerCardsTelegramQuotes
{
    /** POST /telegram/stars/buy: quantity must be between 50 and 10_000. */
    public const STAR_MIN = 50;

    public const STAR_MAX = 10000;

    /** @var list<int> */
    public const PREMIUM_MONTHS = [3, 6, 12];

    /** بسته‌های رایج ستاره داخل بازهٔ quote و محدودیت خرید فی‌زر. */
    private const STAR_PACKS = [50, 100, 150, 250, 350, 500, 750, 1000, 1500, 2500, 5000, 10000];

    /**
     * GET /telegram/stars یک quote است نه لیست بسته: price_per_star + min/max.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function starOffers(array $payload): array
    {
        [$unit, $min, $max] = self::starBounds($payload);
        $listed = FazerCardsPayload::listFrom($payload, ['items', 'stars', 'packages', 'offers']);
        if ($listed !== []) {
            return array_values(array_filter(
                $listed,
                fn (array $row) => self::quantityAllowed((int) ($row['quantity'] ?? $row['stars'] ?? 0), $min, $max)
                    && Money::isPositive(Money::string($row['price_usd'] ?? $row['usd'] ?? $row['price'] ?? '0')),
            ));
        }
        if (! Money::isPositive($unit) || $max < $min) {
            return [];
        }

        $qty = [$min => true];
        foreach (self::STAR_PACKS as $pack) {
            if (self::quantityAllowed($pack, $min, $max)) {
                $qty[$pack] = true;
            }
        }
        ksort($qty);

        $out = [];
        foreach (array_keys($qty) as $amount) {
            $out[] = [
                'quantity' => $amount,
                'name' => $amount.' ستاره',
                'price_usd' => self::times($unit, $amount),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function starUsd(array $payload, int $quantity): string
    {
        [$unit, $min, $max] = self::starBounds($payload);
        if (! self::quantityAllowed($quantity, $min, $max) || ! Money::isPositive($unit)) {
            return '0';
        }

        return self::times($unit, $quantity);
    }

    public static function quantityAllowed(int $quantity, ?int $min = null, ?int $max = null): bool
    {
        $min = $min ?? self::STAR_MIN;
        $max = $max ?? self::STAR_MAX;

        return $quantity >= $min && $quantity <= $max;
    }

    /**
     * GET /telegram/premium لیست plans با months برابر ۳/۶/۱۲ و price_usd است.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function premiumOffers(array $payload): array
    {
        $rows = FazerCardsPayload::listFrom($payload, ['plans', 'items', 'premium', 'packages', 'offers']);
        $out = [];
        foreach ($rows as $row) {
            $months = (int) ($row['months'] ?? 0);
            $usd = Money::string($row['price_usd'] ?? $row['usd'] ?? $row['price'] ?? '0');
            if (! in_array($months, self::PREMIUM_MONTHS, true) || ! Money::isPositive($usd)) {
                continue;
            }
            if (! isset($row['name'])) {
                $row['name'] = $months.' ماه پرمیوم';
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: int, 2: int}
     */
    private static function starBounds(array $payload): array
    {
        $data = FazerCardsPayload::unwrap($payload);
        $unit = Money::string($data['price_per_star'] ?? $data['usd_per_star'] ?? '0');
        $min = (int) ($data['min_amount'] ?? $data['min'] ?? self::STAR_MIN);
        $max = (int) ($data['max_amount'] ?? $data['max'] ?? self::STAR_MAX);
        $min = max(self::STAR_MIN, $min);
        $max = min(self::STAR_MAX, $max > 0 ? $max : self::STAR_MAX);

        return [$unit, $min, $max];
    }

    private static function times(string $unit, int $qty): string
    {
        if (function_exists('bcmul')) {
            return self::trimUsd(bcmul($unit, (string) $qty, 7));
        }

        return self::trimUsd(number_format((float) $unit * $qty, 7, '.', ''));
    }

    private static function trimUsd(string $raw): string
    {
        if (! str_contains($raw, '.')) {
            return $raw;
        }

        return rtrim(rtrim($raw, '0'), '.') ?: '0';
    }
}

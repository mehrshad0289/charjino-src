<?php

namespace App\Modules\Supplier\FazerCards;

use App\Support\Money;

final class FazerCardsSteamTopupQuotes
{
    /** @var list<string> */
    public const CURRENCIES = ['USD', 'RUB', 'UAH', 'KZT'];

    public const FREE_OFFER_ID = 'steam-topup:free';

    /**
     * یک SKU مبلغ آزاد برای کاتالوگ؛ قیمت واقعی هنگام سفارش از نرخ زنده است.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function offers(array $payload): array
    {
        $rates = self::rateMap($payload);
        if ($rates === [] && self::legacyList($payload) === []) {
            return [];
        }
        $min = self::minUsd();

        return [[
            'currency' => 'USD',
            'amount' => $min,
            'name' => 'مبلغ آزاد کیف پول استیم',
            'price_usd' => $min,
            'region' => null,
            'offer_id' => self::FREE_OFFER_ID,
        ]];
    }

    public static function minUsd(): string
    {
        $value = self::configMoney('charjino.supplier.fazercards_steam_min_usd', '0.15');

        return Money::isPositive($value) ? self::formatUsd($value) : '0.15';
    }

    public static function maxUsd(): string
    {
        $value = self::configMoney('charjino.supplier.fazercards_steam_max_usd', '1000');

        return Money::isPositive($value) ? self::formatUsd($value) : '1000.00';
    }

    private static function configMoney(string $key, string $default): string
    {
        if (! function_exists('config')) {
            return $default;
        }
        try {
            return Money::string(config($key, $default));
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function usdForOffer(array $payload, string $offerId): string
    {
        $offerId = trim($offerId);
        if ($offerId === self::FREE_OFFER_ID) {
            return self::minUsd();
        }
        $parts = explode('|', $offerId, 2);
        $currency = strtoupper(trim($parts[0] ?? ''));
        $amount = trim($parts[1] ?? '');
        if ($currency === '' || $amount === '') {
            return '0';
        }

        return self::usdForAmount($payload, $currency, $amount);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function usdForAmount(array $payload, string $currency, string $amount): string
    {
        $currency = strtoupper(trim($currency));
        if (! in_array($currency, self::CURRENCIES, true)) {
            return '0';
        }
        if ($currency === 'USD') {
            return self::formatUsd($amount);
        }
        $rates = self::rateMap($payload);
        $perUsd = $rates[$currency] ?? null;
        if ($perUsd === null) {
            return '0';
        }

        return self::usdCost($currency, $amount, $perUsd);
    }

    public static function offerKey(string $currency, int|string $amount): string
    {
        return strtoupper($currency).'|'.$amount;
    }

    /**
     * POST /steam-topup/check-login → { ok, can_refill, unverified? }.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function loginAllowed(array $payload): bool
    {
        $data = FazerCardsPayload::unwrap($payload);

        return self::flag($data['can_refill'] ?? false) || self::flag($data['unverified'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function rateMap(array $payload): array
    {
        $data = FazerCardsPayload::unwrap($payload);
        $rates = $data['rates'] ?? null;
        if (! is_array($rates) || $rates === []) {
            return [];
        }
        $out = [];
        foreach (self::CURRENCIES as $currency) {
            if (! array_key_exists($currency, $rates) || is_array($rates[$currency])) {
                continue;
            }
            $value = Money::string($rates[$currency]);
            if ($currency === 'USD' && ! Money::isPositive($value)) {
                $value = '1';
            }
            if (Money::isPositive($value)) {
                $out[$currency] = $value;
            }
        }

        return $out;
    }

    /**
     * مبلغ محلی را با نرخ Fazer به دلار تبدیل می‌کند و رو به بالا تا سنت گرد می‌کند.
     */
    public static function usdCost(string $currency, string $amount, string $localPerUsd): string
    {
        if ($currency === 'USD') {
            return self::formatUsd($amount);
        }
        if (! Money::isPositive($localPerUsd) || ! Money::isPositive($amount)) {
            return '0';
        }
        $raw = function_exists('bcdiv')
            ? bcdiv($amount, $localPerUsd, 8)
            : (string) ((float) $amount / (float) $localPerUsd);

        return self::ceilToCent($raw);
    }

    public static function localMin(string $currency, string $localPerUsd): string
    {
        return self::usdToLocal($currency, self::minUsd(), $localPerUsd);
    }

    public static function localMax(string $currency, string $localPerUsd): string
    {
        return self::usdToLocal($currency, self::maxUsd(), $localPerUsd);
    }

    public static function usdToLocal(string $currency, string $usd, string $localPerUsd): string
    {
        $currency = strtoupper($currency);
        if ($currency === 'USD') {
            return self::formatUsd($usd);
        }
        if (! Money::isPositive($usd) || ! Money::isPositive($localPerUsd)) {
            return '0';
        }
        $raw = function_exists('bcmul')
            ? bcmul($usd, $localPerUsd, 8)
            : (string) ((float) $usd * (float) $localPerUsd);

        return $currency === 'USD' ? self::formatUsd($raw) : self::formatLocal($raw);
    }

    public static function flag(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return is_string($value) && strtolower($value) === 'true';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private static function legacyList(array $payload): array
    {
        $rows = FazerCardsPayload::listFrom($payload, ['items', 'packages']);
        $out = [];
        foreach ($rows as $row) {
            $nested = FazerCardsPayload::listFrom($row, ['amounts', 'items']);
            $currency = strtoupper(trim((string) ($row['currency'] ?? $row['code'] ?? 'USD')));
            $candidates = $nested !== [] ? $nested : [$row];
            foreach ($candidates as $amountRow) {
                if (! is_array($amountRow)) {
                    $amountRow = ['amount' => $amountRow];
                }
                $cur = strtoupper(trim((string) ($amountRow['currency'] ?? $currency)));
                $amount = $amountRow['amount'] ?? $amountRow['value'] ?? '';
                $usd = Money::string($amountRow['price_usd'] ?? $amountRow['usd'] ?? $amountRow['price'] ?? '0');
                if ($cur === '' || $amount === '' || ! Money::isPositive($usd)) {
                    continue;
                }
                $out[] = [
                    'currency' => $cur,
                    'amount' => $amount,
                    'price_usd' => $usd,
                    'region' => $cur,
                ];
            }
        }

        return $out;
    }

    private static function formatUsd(string $amount): string
    {
        if (function_exists('bcadd')) {
            return bcadd($amount, '0', 2);
        }

        return number_format((float) $amount, 2, '.', '');
    }

    private static function formatLocal(string $amount): string
    {
        if (function_exists('bcadd')) {
            return bcadd($amount, '0', 4);
        }

        return number_format((float) $amount, 4, '.', '');
    }

    private static function ceilToCent(string $usd): string
    {
        if (function_exists('bcmul') && function_exists('bcdiv')) {
            $times = bcmul($usd, '100', 8);
            if (str_contains($times, '.')) {
                [$int, $frac] = explode('.', $times, 2);
                $cents = rtrim($frac, '0') === '' ? $int : (string) ((int) $int + 1);
            } else {
                $cents = $times;
            }

            return bcdiv($cents, '100', 2);
        }

        return number_format((int) ceil(((float) $usd) * 100 - 1e-8) / 100, 2, '.', '');
    }
}

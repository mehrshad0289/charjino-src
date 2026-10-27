<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;
use App\Support\CustomerError;
use App\Support\Money;

final class SteamTopupAmount
{
    /**
     * @param  array<string, mixed>  $ratesPayload
     * @return array{currency: string, amount: string, usd: string}
     */
    public static function validate(string $currency, string $amount, array $ratesPayload = []): array
    {
        $currency = strtoupper(trim($currency));
        $amount = trim($amount);
        if (! in_array($currency, FazerCardsSteamTopupQuotes::CURRENCIES, true)) {
            throw new ApiException(CustomerError::message('AMOUNT_OUT_OF_RANGE'), 400, 'AMOUNT_OUT_OF_RANGE');
        }
        if (! preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw new ApiException(CustomerError::message('AMOUNT_DECIMALS'), 400, 'AMOUNT_DECIMALS');
        }
        $decimals = self::decimalPlaces($amount);
        $maxDecimals = $currency === 'USD' ? 2 : 4;
        if ($decimals > $maxDecimals) {
            throw new ApiException(CustomerError::message('AMOUNT_DECIMALS'), 400, 'AMOUNT_DECIMALS');
        }
        $rates = $ratesPayload !== [] ? $ratesPayload : [];
        $usd = FazerCardsSteamTopupQuotes::usdForAmount($rates, $currency, $amount);
        if (! Money::isPositive($usd)) {
            throw new ApiException(CustomerError::message('AMOUNT_OUT_OF_RANGE'), 400, 'AMOUNT_OUT_OF_RANGE');
        }
        $min = FazerCardsSteamTopupQuotes::minUsd();
        $max = FazerCardsSteamTopupQuotes::maxUsd();
        if (Money::compare($usd, $min) < 0 || Money::compare($usd, $max) > 0) {
            throw new ApiException(CustomerError::message('AMOUNT_OUT_OF_RANGE'), 400, 'AMOUNT_OUT_OF_RANGE');
        }

        return [
            'currency' => $currency,
            'amount' => $amount,
            'usd' => $usd,
        ];
    }

    private static function decimalPlaces(string $amount): int
    {
        if (! str_contains($amount, '.')) {
            return 0;
        }
        [, $frac] = explode('.', $amount, 2);

        return strlen(rtrim($frac, '0')) === 0 ? 0 : strlen($frac);
    }
}

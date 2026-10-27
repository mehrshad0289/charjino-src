<?php

namespace App\Modules\Pricing;

use App\Exceptions\ApiException;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class FxRateService
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        if (Setting::getValue('fx_mode', 'auto') === 'manual') {
            $this->pricing->repriceAllSkus();

            return $this->pricing->snapshot() + ['refreshed' => false, 'reason' => 'نرخ دستی فعال است.'];
        }

        $toman = $this->fetchTomanPerUsd();
        Setting::putValue('usd_toman_rate', (string) $toman);
        Setting::putValue('usd_toman_updated_at', now()->toIso8601String());
        $priced = $this->pricing->repriceAllSkus();

        return $this->pricing->snapshot() + [
            'refreshed' => true,
            'repricedSkus' => $priced,
        ];
    }

    private function fetchTomanPerUsd(): int
    {
        try {
            $response = Http::timeout(12)->acceptJson()->get('https://api.nobitex.ir/v2/orderbook/USDTIRT');
            $rial = $response->json('lastTradePrice');
            if ($response->ok() && is_numeric($rial) && (float) $rial > 0) {
                Setting::putValue('usd_toman_source', 'nobitex-usdtirt');

                return max(1, (int) round(((float) $rial) / 10));
            }
        } catch (\Throwable) {
            // fallback
        }

        try {
            $response = Http::timeout(12)->acceptJson()->get('https://api.tetherland.com/currencies');
            $currencies = $response->json('data.currencies');
            $usdt = is_array($currencies)
                ? ($currencies['USDT'] ?? $currencies['usdt'] ?? null)
                : null;
            $price = is_array($usdt)
                ? ($usdt['price'] ?? $usdt['sell_price'] ?? $usdt['buy_price'] ?? $usdt['toman'] ?? null)
                : null;
            if ($response->ok() && is_numeric($price) && (float) $price > 0) {
                Setting::putValue('usd_toman_source', 'tetherland-usdt');

                return max(1, (int) round((float) $price));
            }
        } catch (\Throwable) {
            // fallback
        }

        $existing = (int) $this->pricing->usdTomanRate();
        if ($existing > 0) {
            return $existing;
        }

        throw new ApiException('گرفتن نرخ دلار انجام نشد. نرخ را دستی در پنل وارد کن.', 503);
    }
}

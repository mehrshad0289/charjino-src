<?php

namespace App\Modules\Orders;

use App\Exceptions\ApiException;
use App\Models\Sku;
use App\Modules\Pricing\PricingService;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsPriceFreshness;
use App\Modules\Supplier\FazerCards\SteamTopupAmount;
use App\Support\CustomerError;
use App\Support\Money;

class OrderQuoteService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly FazerCardsAdapter $fazer,
    ) {}

    /**
     * @param  array<string, string>  $requirements
     * @return array{
     *     unitCostUsd: string,
     *     totalCostUsd: string,
     *     stock: int|null,
     *     minQty: int,
     *     maxQty: int,
     *     unitPriceToman: int,
     *     totalToman: int,
     *     priceSource: string,
     *     steamCurrency: ?string,
     *     steamAmount: ?string,
     *     family: string
     * }
     */
    public function quote(Sku $sku, int $quantity, array $requirements = [], ?string $amount = null, ?string $currency = null): array
    {
        $sku->loadMissing('product');
        FazerCardsPriceFreshness::assertPurchasable($sku);
        $family = FazerCardsIds::familyOf($sku->fulfillment_type, $sku->fazercards_category_id);
        $quantity = max(1, $quantity);
        $minQty = 1;
        $maxQty = 1;
        $stock = $sku->stock !== null ? (int) $sku->stock : null;
        if (in_array($family, [FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY], true)) {
            $minQty = $sku->minOrderQuantity();
            $maxQty = $sku->maxOrderQuantity();
            if ($stock !== null) {
                $maxQty = max($minQty, min($maxQty, $stock));
            }
            if ($stock !== null && $stock <= 0) {
                throw new ApiException(CustomerError::message('OUT_OF_STOCK'), 400, 'OUT_OF_STOCK');
            }
            if ($quantity < $minQty || $quantity > $maxQty) {
                throw new ApiException(CustomerError::message('INVALID_QUANTITY'), 400, 'INVALID_QUANTITY');
            }
        } else {
            $quantity = 1;
        }

        $steamCurrency = null;
        $steamAmount = null;
        $source = 'synced';
        if ($family === FazerCardsIds::STEAM_TOPUP) {
            $cur = $currency ?: (string) ($requirements['currency'] ?? '');
            $amt = $amount ?: (string) ($requirements['amount'] ?? '');
            $payload = [];
            try {
                if ($this->fazer->configured()) {
                    $payload = $this->fazer->steamRatesPayload();
                    $source = 'live';
                }
            } catch (ApiException) {
            }
            $priced = SteamTopupAmount::validate($cur, $amt, $payload);
            $unitUsd = $priced['usd'];
            $steamCurrency = $priced['currency'];
            $steamAmount = $priced['amount'];
        } else {
            $freshSeconds = (int) config('charjino.orders.stock_fresh_seconds', 300);
            $fresh = $sku->synced_at && $sku->synced_at->gt(now()->subSeconds(max(1, $freshSeconds)));
            if ($sku->isFazerCards() && ! $fresh && $this->fazer->configured()) {
                try {
                    $live = $this->fazer->livePrice($sku);
                    $unitUsd = Money::string($live['unitPrice'] ?? '0');
                    $source = 'live';
                    if (isset($live['stock'])) {
                        $stock = (int) $live['stock'];
                    }
                } catch (ApiException) {
                    $unitUsd = $sku->usdCost();
                }
            } else {
                $unitUsd = $sku->usdCost();
            }
        }

        if (! Money::isPositive($unitUsd)) {
            throw new ApiException(CustomerError::message('SKU_UNAVAILABLE'), 400, 'SKU_UNAVAILABLE');
        }

        $totalUsd = $quantity > 1 && function_exists('bcmul')
            ? bcmul($unitUsd, (string) $quantity, 6)
            : (string) ((float) $unitUsd * $quantity);
        $unitToman = $this->pricing->sellTomanFor($sku, $unitUsd, $sku->product);
        $totalToman = $unitToman * $quantity;
        if ($family === FazerCardsIds::STEAM_TOPUP) {
            $totalToman = $this->pricing->sellTomanFor($sku, $unitUsd, $sku->product);
            $unitToman = $totalToman;
        }

        return [
            'unitCostUsd' => $unitUsd,
            'totalCostUsd' => $totalUsd,
            'stock' => $stock,
            'minQty' => $minQty,
            'maxQty' => $maxQty,
            'unitPriceToman' => $unitToman,
            'totalToman' => $totalToman,
            'priceSource' => $source,
            'steamCurrency' => $steamCurrency,
            'steamAmount' => $steamAmount,
            'family' => $family,
            'quantity' => $quantity,
        ];
    }
}

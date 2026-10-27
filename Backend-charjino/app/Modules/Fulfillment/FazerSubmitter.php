<?php

namespace App\Modules\Fulfillment;

use App\Exceptions\ApiException;
use App\Models\FulfillmentAttempt;
use App\Models\OrderItem;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsRedactor;
use App\Support\Money;

class FazerSubmitter
{
    public function __construct(private readonly FazerCardsAdapter $adapter) {}

    /**
     * @return array<string, mixed>
     */
    public function send(FulfillmentAttempt $attempt, OrderItem $item): array
    {
        $family = (string) $item->fulfillment_type;
        $idempotent = ! in_array($family, [FazerCardsIds::TELEGRAM_STARS, FazerCardsIds::TELEGRAM_PREMIUM], true);
        $price = Money::string($item->total_cost_usd);
        $maxTries = $idempotent ? max(1, (int) config('charjino.orders.same_key_retries', 3)) : 1;
        $last = null;
        for ($i = 0; $i < $maxTries; $i++) {
            try {
                return $this->adapter->placeFromItem($item, $price, (string) $attempt->idempotency_key, true);
            } catch (ApiException $e) {
                $last = $e;
                $retryable = in_array($e->errorCode, ['SUPPLIER_TIMEOUT', 'SUPPLIER_5XX', 'SUPPLIER_UNREACHABLE'], true)
                    || $e->status >= 500;
                if (! $idempotent || ! $retryable || $i === $maxTries - 1) {
                    throw $e;
                }
                if (! app()->environment('testing')) {
                    sleep([2, 8, 30][$i] ?? 8);
                }
            }
        }
        throw $last ?? new ApiException('سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.', 502, 'FAZER_FAILED');
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPreview(OrderItem $item): array
    {
        $req = is_array($item->requirements) ? $item->requirements : [];

        return FazerCardsRedactor::scrub([
            'fulfillment_type' => $item->fulfillment_type,
            'supplier_ref' => $item->supplier_ref,
            'quantity' => $item->quantity,
            'requirements' => $req,
            'steam_currency' => $item->steam_currency,
            'steam_amount' => $item->steam_amount,
        ]);
    }
}

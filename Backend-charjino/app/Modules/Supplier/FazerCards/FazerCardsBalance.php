<?php

namespace App\Modules\Supplier\FazerCards;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;

class FazerCardsBalance
{
    public function __construct(private readonly FazerCardsClient $client) {}

    /**
     * @return array{balance: string, reserved: string, spendable: string}
     */
    public function snapshot(?string $exceptOrderId = null): array
    {
        $ttl = (int) config('charjino.orders.balance_cache_seconds', 45);
        $balance = '0';
        if ($this->client->configured()) {
            $balance = Cache::remember('fazer:balance', max(15, $ttl), function () {
                $data = $this->client->fetch('balance', 'GET', [], null, 4)['data'];

                return Money::string($data['balance'] ?? '0');
            });
        }
        $reserved = $this->reservedUsd($exceptOrderId);
        $spendable = function_exists('bcsub')
            ? bcsub($balance, $reserved, 6)
            : (string) max(0, (float) $balance - (float) $reserved);

        return [
            'balance' => $balance,
            'reserved' => $reserved,
            'spendable' => $spendable,
        ];
    }

    public function canAfford(string $totalCostUsd, ?string $exceptOrderId = null): bool
    {
        if (! Money::isPositive($totalCostUsd)) {
            return true;
        }
        if (! $this->client->configured()) {
            return true;
        }
        $snap = $this->snapshot($exceptOrderId);

        return Money::compare($snap['spendable'], $totalCostUsd) >= 0;
    }

    public function reservedUsd(?string $exceptOrderId = null): string
    {
        $query = Order::query()->where('status', Order::STATUS_PAID);
        if ($exceptOrderId) {
            $query->where('id', '!=', $exceptOrderId);
        }
        $orderIds = $query->pluck('id');
        if ($orderIds->isEmpty()) {
            return '0';
        }
        $sum = OrderItem::query()->whereIn('order_id', $orderIds)->sum('total_cost_usd');

        return Money::string($sum ?: '0');
    }
}

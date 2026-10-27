<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Supplier\FazerCards\FazerCardsBalance;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FazerCardsBalanceHoldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config([
            'charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd',
            'charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2',
        ]);
        Cache::flush();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/balance')) {
                return Http::response(['ok' => true, 'balance' => '6.9359', 'currency' => 'USD'], 200);
            }

            return Http::response(['ok' => true], 200);
        });
    }

    public function test_paid_order_does_not_reserve_its_own_cost(): void
    {
        $order = $this->paidGiftcard('5.164200');
        $balance = $this->app->make(FazerCardsBalance::class);

        $this->assertTrue($balance->canAfford('5.164200', $order->id));
        $this->assertSame('0', $balance->reservedUsd($order->id));
        $this->assertFalse($balance->canAfford('5.164200'));
    }

    public function test_other_paid_orders_still_reduce_spendable(): void
    {
        $this->paidGiftcard('5.164200');
        $next = $this->paidGiftcard('5.164200');
        $balance = $this->app->make(FazerCardsBalance::class);

        $this->assertFalse($balance->canAfford('5.164200', $next->id));
        $this->assertSame(0, Money::compare($balance->reservedUsd($next->id), '5.164200'));
    }

    private function paidGiftcard(string $costUsd): Order
    {
        $user = User::query()->create([
            'phone' => '09'.str_pad((string) random_int(100000000, 999999999), 9, '0'),
            'role' => 'user',
            'status' => 'active',
        ]);
        $sku = Sku::query()->firstOrFail();
        $order = new Order;
        $order->user_id = $user->id;
        $order->sku_id = $sku->id;
        $order->product_id = $sku->product_id;
        $order->quantity = 1;
        $order->requirements = [];
        $order->amount_toman = 1435000;
        $order->status = Order::STATUS_PAID;
        $order->paid_at = now();
        $order->fulfillment_type = 'giftcard';
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', (string) Str::uuid());
        $order->save();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'sku_id' => $sku->id,
            'product_id' => $sku->product_id,
            'fulfillment_type' => 'giftcard',
            'quantity' => 1,
            'unit_price_toman' => 1435000,
            'total_toman' => 1435000,
            'unit_cost_usd' => $costUsd,
            'total_cost_usd' => $costUsd,
        ]);

        return $order;
    }
}

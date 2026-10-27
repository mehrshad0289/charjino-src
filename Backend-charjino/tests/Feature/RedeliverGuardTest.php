<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Fulfillment\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RedeliverGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_and_active_refund_are_blocked_failed_is_allowed(): void
    {
        $this->seed();
        config(['queue.default' => 'sync']);
        $order = $this->fazerOrder(Order::STATUS_NEEDS_REVIEW);
        $attempt = $this->attempt($order, FulfillmentAttempt::STATUS_UNKNOWN);

        $service = app(FulfillmentService::class);
        try {
            $service->redeliver($order, '1');
            $this->fail('expected reject');
        } catch (ApiException $e) {
            $this->assertSame('ORDER_NOT_PAYABLE', $e->errorCode);
        }

        $attempt->status = FulfillmentAttempt::STATUS_FAILED;
        $attempt->save();
        $order->status = Order::STATUS_FAILED;
        $order->save();

        \App\Models\Refund::query()->create([
            'order_id' => $order->id,
            'amount_toman' => $order->amount_toman,
            'method' => 'wallet',
            'status' => 'pending',
        ]);
        try {
            $service->redeliver($order->fresh(), '1');
            $this->fail('expected refund guard');
        } catch (ApiException $e) {
            $this->assertSame('ORDER_STATE_INVALID', $e->errorCode);
        }

        \App\Models\Refund::query()->where('order_id', $order->id)->delete();
        $before = $attempt->idempotency_key;
        $updated = $service->redeliver($order->fresh(), '1');
        $latest = $updated->fresh()?->latestAttempt();
        $this->assertNotNull($latest);
        $this->assertNotSame($before, $latest->idempotency_key);
    }

    private function fazerOrder(string $status): Order
    {
        $user = User::query()->create([
            'phone' => '09123334444',
            'role' => 'user',
            'status' => 'active',
        ]);
        $sku = Sku::query()->firstOrFail();
        $sku->fazercards_category_id = 'giftcard:steam';
        $sku->fazercards_offer_id = '10usd';
        $sku->fulfillment_type = 'giftcard';
        $sku->save();
        $order = new Order;
        $order->user_id = $user->id;
        $order->sku_id = $sku->id;
        $order->product_id = $sku->product_id;
        $order->quantity = 1;
        $order->requirements = [];
        $order->amount_toman = 10000;
        $order->status = $status;
        $order->fulfillment_type = 'giftcard';
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', 'redeliver');
        $order->save();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'sku_id' => $sku->id,
            'product_id' => $sku->product_id,
            'fulfillment_type' => 'giftcard',
            'quantity' => 1,
            'unit_price_toman' => 10000,
            'total_toman' => 10000,
            'unit_cost_usd' => '1',
            'total_cost_usd' => '1',
        ]);

        return $order;
    }

    private function attempt(Order $order, string $status): FulfillmentAttempt
    {
        $item = $order->primaryItem();

        return FulfillmentAttempt::query()->create([
            'order_id' => $order->id,
            'order_item_id' => $item?->id,
            'attempt_no' => 1,
            'idempotency_key' => 'cj-test-1-a1-'.Str::uuid(),
            'idempotent' => true,
            'fazer_kind' => 'giftcard',
            'supplier' => 'fazercards',
            'supplier_order_id' => (string) Str::uuid(),
            'status' => $status,
        ]);
    }
}

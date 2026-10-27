<?php

namespace Tests\Unit;

use App\Exceptions\IllegalTransition;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Orders\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_allowed_and_blocked_transitions(): void
    {
        $states = app(OrderStateMachine::class);
        $order = $this->order(Order::STATUS_DRAFT);

        $states->transition($order, Order::STATUS_AWAITING_PAYMENT, 'customer', 'PAY');
        $this->assertSame(Order::STATUS_AWAITING_PAYMENT, $order->fresh()?->status);

        $states->transition($order->fresh(), Order::STATUS_PAID, 'system', 'PAID');
        $this->assertSame(Order::STATUS_PAID, $order->fresh()?->status);

        $states->transition($order->fresh(), Order::STATUS_PROCESSING, 'system', 'FULFILL');
        $late = $states->applyFazerStatus($order->fresh(), 'created', 'webhook');
        $this->assertSame(Order::STATUS_PROCESSING, $late->status);

        $states->transition($order->fresh(), Order::STATUS_COMPLETED, 'system', 'DONE');
        $this->expectException(IllegalTransition::class);
        $states->transition($order->fresh(), Order::STATUS_PROCESSING, 'webhook', 'LATE');
    }

    public function test_failed_can_return_to_paid_for_redeliver(): void
    {
        $states = app(OrderStateMachine::class);
        $order = $this->order(Order::STATUS_FAILED);
        $states->transition($order, Order::STATUS_PAID, 'admin:1', 'REDELIVER');
        $this->assertSame(Order::STATUS_PAID, $order->fresh()?->status);
    }

    private function order(string $status): Order
    {
        $user = User::query()->create([
            'phone' => '0912000'.random_int(1000, 9999),
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
        $order->amount_toman = 10000;
        $order->status = $status;
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', 'sm');
        $order->save();

        return $order;
    }
}

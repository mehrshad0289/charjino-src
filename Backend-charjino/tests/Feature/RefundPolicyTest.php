<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Orders\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_mode_goes_to_refund_pending_without_wallet_credit(): void
    {
        $this->seed();
        config(['charjino.orders.refund_mode' => 'admin']);
        $order = $this->paidOrder();
        $updated = app(RefundService::class)->onDefinitiveFailure($order, 'FAZER_FAILED');

        $this->assertSame(Order::STATUS_REFUND_PENDING, $updated->status);
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('wallet_transactions', ['order_id' => $order->id]);
    }

    public function test_admin_wallet_refund_credits_once(): void
    {
        $this->seed();
        $order = $this->paidOrder();
        $refunds = app(RefundService::class);
        $refunds->onDefinitiveFailure($order, 'FAZER_FAILED');
        $first = $refunds->refundToWallet($order->fresh(), 'admin:1', 'ADMIN');
        $again = $refunds->refundToWallet($order->fresh(), 'admin:1', 'ADMIN');

        $this->assertSame(Order::STATUS_REFUNDED, $first->status);
        $this->assertSame(Order::STATUS_REFUNDED, $again->status);
        $this->assertSame(1, \App\Models\WalletTransaction::query()->where('order_id', $order->id)->count());
        $wallet = \App\Models\Wallet::query()->find($order->user_id);
        $this->assertSame((int) $order->amount_toman, (int) $wallet?->balance_toman);
    }

    private function paidOrder(): Order
    {
        $user = User::query()->create([
            'phone' => '09121112222',
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
        $order->amount_toman = 25000;
        $order->status = Order::STATUS_PAID;
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', 'refund');
        $order->save();

        return $order;
    }
}

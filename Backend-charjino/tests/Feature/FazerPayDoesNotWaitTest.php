<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Jobs\SubmitFazerOrder;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Fulfillment\FazerOrderOutcome;
use App\Modules\Orders\OrderQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class FazerPayDoesNotWaitTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHORITY = 'A0000000000000000000000000000wwOGYpd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config([
            'charjino.orders.mock_payments' => false,
            'charjino.orders.payment_driver' => 'zarinpal',
            'payments.driver' => 'zarinpal',
            'payments.callback_url' => 'http://localhost:3001/payments/zarinpal',
            'payments.zarinpal.merchant_id' => '11111111-1111-1111-1111-111111111111',
            'payments.zarinpal.sandbox' => true,
            'charjino.shop_url' => 'http://localhost:3001',
            'charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd',
            'charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2',
            'charjino.orders.block_pay_on_low_balance' => true,
        ]);
    }

    public function test_pay_starts_gateway_when_fazer_wallet_is_empty(): void
    {
        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => self::AUTHORITY,
                ],
                'errors' => [],
            ]),
            'https://api.fzr.cards/*' => Http::response(['ok' => true, 'balance' => '0.0000', 'currency' => 'USD'], 200),
        ]);

        [$token, $number] = $this->createOrder();
        $pay = $this->postJson('/api/orders/'.$number.'/pay', [
            'returnOrigin' => 'http://localhost:3001',
        ], ['Authorization' => 'Bearer '.$token]);

        $pay->assertOk()->assertJsonPath('data.provider', 'zarinpal');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/balance'));
    }

    public function test_quote_uses_synced_cost_if_fazer_live_price_fails(): void
    {
        $sku = Sku::query()->firstOrFail();
        $sku->fazercards_category_id = 'giftcard:rewarble_usd';
        $sku->fazercards_offer_id = '5_usd';
        $sku->fulfillment_type = 'giftcard';
        $sku->cost_usd = '5.164200';
        $sku->live_cost_usd = '5.164200';
        $sku->stock = 10;
        $sku->synced_at = now()->subHour();
        $sku->save();

        Http::fake([
            'https://api.fzr.cards/api/v2/*' => Http::response([
                'ok' => false,
                'error' => ['code' => 'OUT_OF_STOCK', 'message' => 'empty'],
            ], 400),
        ]);

        $quote = $this->app->make(OrderQuoteService::class)->quote($sku, 1);
        $this->assertSame('5.164200', $quote['unitCostUsd']);
        $this->assertSame('synced', $quote['priceSource']);
    }

    public function test_poller_redrives_paid_queued_attempt_that_never_sent(): void
    {
        Queue::fake();
        $order = $this->paidGiftcard();
        $item = $order->primaryItem();
        FulfillmentAttempt::query()->create([
            'order_id' => $order->id,
            'order_item_id' => $item?->id,
            'attempt_no' => 1,
            'idempotency_key' => 'cj-test-queued',
            'idempotent' => true,
            'fazer_kind' => 'giftcard',
            'supplier' => 'fazercards',
            'supplier_order_id' => 'cj-queued-placeholder',
            'status' => FulfillmentAttempt::STATUS_QUEUED,
            'dry_run' => false,
        ]);
        $order->updated_at = now()->subMinutes(3);
        $order->save();

        $this->artisan('charjino:poll-fulfillment')->assertSuccessful();

        Queue::assertPushed(SubmitFazerOrder::class, fn (SubmitFazerOrder $job) => $job->orderId === $order->id);
    }

    public function test_fazer_insufficient_balance_after_pay_holds_for_support(): void
    {
        $order = $this->paidGiftcard();
        $item = $order->primaryItem();
        $attempt = FulfillmentAttempt::query()->create([
            'order_id' => $order->id,
            'order_item_id' => $item?->id,
            'attempt_no' => 1,
            'idempotency_key' => 'cj-test-hold',
            'idempotent' => true,
            'fazer_kind' => 'giftcard',
            'supplier' => 'fazercards',
            'supplier_order_id' => 'cj-hold-placeholder',
            'status' => FulfillmentAttempt::STATUS_SENDING,
            'sending_at' => now(),
            'dry_run' => false,
        ]);
        $order->status = Order::STATUS_SUBMITTING;
        $order->save();

        app(FazerOrderOutcome::class)->applyException(
            $attempt,
            new ApiException('موجودی کیف پول دلاری تأمین‌کننده کافی نیست.', 400, 'INSUFFICIENT_BALANCE'),
        );

        $order->refresh();
        $attempt->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('INSUFFICIENT_BALANCE', $order->hold_reason);
        $this->assertSame(FulfillmentAttempt::STATUS_QUEUED, $attempt->status);
        $this->assertDatabaseMissing('refunds', ['order_id' => $order->id]);
    }

    public function test_fazer_out_of_stock_after_pay_holds_for_support(): void
    {
        $order = $this->paidGiftcard();
        $item = $order->primaryItem();
        $attempt = FulfillmentAttempt::query()->create([
            'order_id' => $order->id,
            'order_item_id' => $item?->id,
            'attempt_no' => 1,
            'idempotency_key' => 'cj-test-stock',
            'idempotent' => true,
            'fazer_kind' => 'giftcard',
            'supplier' => 'fazercards',
            'supplier_order_id' => 'cj-stock-placeholder',
            'status' => FulfillmentAttempt::STATUS_SENDING,
            'sending_at' => now(),
            'dry_run' => false,
        ]);
        $order->status = Order::STATUS_SUBMITTING;
        $order->save();

        app(FazerOrderOutcome::class)->applyException(
            $attempt,
            new ApiException('این بسته فعلاً موجود نیست.', 400, 'OUT_OF_STOCK'),
        );

        $order->refresh();
        $attempt->refresh();
        $this->assertSame(Order::STATUS_NEEDS_REVIEW, $order->status);
        $this->assertSame('OUT_OF_STOCK', $order->needs_review_reason);
        $this->assertSame(FulfillmentAttempt::STATUS_QUEUED, $attempt->status);
        $this->assertDatabaseMissing('refunds', ['order_id' => $order->id]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createOrder(): array
    {
        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->synced_at = now();
        $sku->save();
        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ], [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $create->assertOk();

        return [$token, (string) $create->json('data.number')];
    }

    private function shopToken(): string
    {
        $this->postJson('/api/auth/request-otp', ['phoneNumber' => '09123456789'])->assertOk();
        $verify = $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => '09123456789',
            'code' => '123456',
        ]);
        $verify->assertOk();

        return (string) $verify->json('data.access_token');
    }

    private function paidGiftcard(): Order
    {
        $user = User::query()->create([
            'phone' => '09120009999',
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
        $order->hold_reason = 'INSUFFICIENT_BALANCE';
        $order->hold_until = now()->subMinute();
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
            'unit_cost_usd' => '5.164200',
            'total_cost_usd' => '5.164200',
        ]);

        return $order->fresh() ?? $order;
    }
}

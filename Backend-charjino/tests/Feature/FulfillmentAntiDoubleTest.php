<?php

namespace Tests\Feature;

use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Fulfillment\FulfillmentGateway;
use App\Modules\Fulfillment\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\FakeFulfillmentGateway;
use Tests\TestCase;

class FulfillmentAntiDoubleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        RateLimiter::clear('s2t-poll');
    }

    public function test_price_increased_retries_same_uuid_with_new_expected_price(): void
    {
        $fake = new FakeFulfillmentGateway;
        $fake->createErrors = ['PRICE_INCREASED', null];
        $fake->prices = ['0.100000', '0.120000'];
        $this->app->instance(FulfillmentGateway::class, $fake);

        $order = $this->paidOrder();
        $svc = $this->app->make(FulfillmentService::class);
        $svc->fulfill($order->id);

        $attempt = $order->fresh()?->latestAttempt();
        $this->assertNotNull($attempt);
        $this->assertSame(2, $fake->createCalls);
        $this->assertSame([$attempt->supplier_order_id, $attempt->supplier_order_id], $fake->createIds);
        $this->assertSame('0.120000', $fake->lastExpectedPrice);
        $this->assertSame('0.120000', $attempt->expected_unit_price);
        $this->assertSame(FulfillmentAttempt::STATUS_PENDING, $attempt->status);
    }

    public function test_duplicate_order_reads_same_uuid_and_does_not_mint_another(): void
    {
        $fake = new FakeFulfillmentGateway;
        $fake->createErrors = ['DUPLICATE_ORDER'];
        $fake->getStatus = 'pending';
        $this->app->instance(FulfillmentGateway::class, $fake);

        $order = $this->paidOrder();
        $this->app->make(FulfillmentService::class)->fulfill($order->id);

        $attempt = $order->fresh()?->latestAttempt();
        $this->assertNotNull($attempt);
        $this->assertSame(1, $fake->createCalls);
        $this->assertSame(1, $fake->getOrderCalls);
        $this->assertSame($attempt->supplier_order_id, $fake->createIds[0]);
        $this->assertSame(1, FulfillmentAttempt::query()->where('order_id', $order->id)->count());
    }

    public function test_poll_respects_three_gets_per_sixty_seconds(): void
    {
        $fake = new FakeFulfillmentGateway;
        $fake->getStatus = 'pending';
        config(['charjino.orders.poll_after_minutes' => 0]);
        $this->app->instance(FulfillmentGateway::class, $fake);

        $order = $this->paidOrder();
        $uuid = (string) Str::uuid();
        FulfillmentAttempt::query()->create([
            'order_id' => $order->id,
            'supplier' => 'shop2topup',
            'supplier_order_id' => $uuid,
            'status' => FulfillmentAttempt::STATUS_PENDING,
            'dry_run' => false,
            'last_polled_at' => now()->subMinutes(10),
        ]);
        $order->status = Order::STATUS_FULFILLING;
        $order->save();

        $svc = $this->app->make(FulfillmentService::class);
        $svc->pollStuck();
        $svc->pollStuck();
        $svc->pollStuck();
        $svc->pollStuck();

        $this->assertSame(3, $fake->getOrderCalls);
    }

    public function test_shop2_insufficient_balance_after_pay_holds_for_support(): void
    {
        $fake = new FakeFulfillmentGateway;
        $fake->createErrors = ['INSUFFICIENT_BALANCE'];
        $this->app->instance(FulfillmentGateway::class, $fake);

        $order = $this->paidOrder();
        $this->app->make(FulfillmentService::class)->fulfill($order->id);

        $order->refresh();
        $attempt = $order->latestAttempt();
        $this->assertSame(Order::STATUS_NEEDS_REVIEW, $order->status);
        $this->assertSame('INSUFFICIENT_BALANCE', $order->needs_review_reason);
        $this->assertSame(FulfillmentAttempt::STATUS_NOT_STARTED, $attempt?->status);
        $this->assertDatabaseMissing('refunds', ['order_id' => $order->id]);
    }

    private function paidOrder(): Order
    {
        $user = User::query()->create([
            'phone' => '09120000000',
            'role' => 'user',
            'status' => 'active',
        ]);
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->shop2_item_id = 12;
        $sku->fazercards_category_id = null;
        $sku->fazercards_offer_id = null;
        $sku->gift_sku_id = null;
        $sku->save();

        $order = new Order;
        $order->user_id = $user->id;
        $order->sku_id = $sku->id;
        $order->product_id = $sku->product_id;
        $order->quantity = 1;
        $order->requirements = ['player_id' => '5123456789'];
        $order->player_name = 'TestPlayer';
        $order->amount_toman = $sku->price;
        $order->cost_usd_snapshot = '0.10';
        $order->fx_rate_snapshot = '100000';
        $order->status = Order::STATUS_PAID;
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', 'test');
        $order->save();

        return $order;
    }
}

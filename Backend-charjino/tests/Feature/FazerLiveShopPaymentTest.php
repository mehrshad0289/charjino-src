<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Fulfillment\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FazerLiveShopPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_fazer_fulfill_without_shop_payment_does_not_call_supplier(): void
    {
        $this->seed();
        config([
            'charjino.supplier.fazercards_key' => 'test-fc-key',
            'charjino.supplier.fazercards_allow_live_order' => true,
            'charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2',
        ]);
        Http::fake();

        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->fazercards_category_id = 'pubg_mobile_fast';
        $sku->fazercards_offer_id = '60_uc';
        $sku->save();

        $user = User::query()->create([
            'phone' => '09120001111',
            'role' => 'user',
            'status' => 'active',
        ]);

        $order = new Order;
        $order->user_id = $user->id;
        $order->sku_id = $sku->id;
        $order->product_id = $sku->product_id;
        $order->quantity = 1;
        $order->requirements = ['player_id' => '51974754893'];
        $order->amount_toman = $sku->sellPrice();
        $order->status = Order::STATUS_PAID;
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', 'ops');
        $order->save();

        $this->app->make(FulfillmentService::class)->fulfill($order->id);

        $order->refresh();
        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertSame('SHOP_PAYMENT_REQUIRED', $order->latestAttempt()?->error_code);
        Http::assertNothingSent();
    }
}

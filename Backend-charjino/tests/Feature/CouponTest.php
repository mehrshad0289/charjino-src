<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['charjino.orders.mock_payments' => true]);
        config(['charjino.supplier.allow_live_order' => false]);
    }

    public function test_percentage_coupon_is_capped_and_amount_coupon_needs_min_purchase(): void
    {
        $admin = $this->adminToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $subtotal = $sku->sellPrice();
        $this->assertGreaterThan(20000, $subtotal);

        $percent = $this->withToken($admin)->postJson('/api/admin/coupons', [
            'name' => 'نیم‌بها با سقف',
            'code' => 'half-cap',
            'type' => Coupon::TYPE_PERCENTAGE,
            'percentage' => 50,
            'maxPercentageAmount' => 20000,
            'expiredAt' => now()->addWeek()->toDateString(),
        ]);
        $percent->assertCreated()->assertJsonPath('data.code', 'HALF-CAP');

        $this->withToken($admin)->postJson('/api/admin/coupons', [
            'name' => 'دو میلیون بدون حداقل',
            'code' => 'NOMIN',
            'type' => Coupon::TYPE_AMOUNT,
            'amount' => 2000000,
            'expiredAt' => now()->addWeek()->toDateString(),
        ])->assertStatus(422);

        $this->withToken($admin)->postJson('/api/admin/coupons', [
            'name' => 'دو میلیون با حداقل ناکافی',
            'code' => 'LOWMIN',
            'type' => Coupon::TYPE_AMOUNT,
            'amount' => 2000000,
            'minOrderToman' => 200000,
            'expiredAt' => now()->addWeek()->toDateString(),
        ])->assertStatus(422);

        $amount = $this->withToken($admin)->postJson('/api/admin/coupons', [
            'name' => 'ده هزار با حداقل',
            'code' => 'SAVE10',
            'type' => Coupon::TYPE_AMOUNT,
            'amount' => 10000,
            'minOrderToman' => 50000,
            'expiredAt' => now()->addWeek()->toDateString(),
        ]);
        $amount->assertCreated()->assertJsonPath('data.minOrderToman', 50000);

        $this->withToken($admin)->postJson('/api/admin/coupons', [
            'name' => 'دو میلیون با حداقل زیاد',
            'code' => 'BIG2M',
            'type' => Coupon::TYPE_AMOUNT,
            'amount' => 2000000,
            'minOrderToman' => 2000000,
            'expiredAt' => now()->addWeek()->toDateString(),
        ])->assertCreated();

        $this->flushHeaders();
        $this->postJson('/api/checkout/coupon', [
            'code' => 'HALF-CAP',
            'skuId' => $sku->id,
        ])->assertStatus(401);

        $token = $this->shopToken('09123334455');
        $preview = $this->withToken($token)->postJson('/api/checkout/coupon', [
            'code' => 'HALF-CAP',
            'skuId' => $sku->id,
            'quantity' => 1,
        ]);
        $expectedPercent = min(intdiv($subtotal * 50, 100), 20000, $subtotal - 1);
        $preview->assertOk()
            ->assertJsonPath('data.discountToman', $expectedPercent)
            ->assertJsonPath('data.payableToman', $subtotal - $expectedPercent);

        $this->withToken($token)->postJson('/api/checkout/coupon', [
            'code' => 'BIG2M',
            'skuId' => $sku->id,
        ])->assertStatus(422)->assertJsonPath('error.code', 'COUPON_MIN_ORDER');

        $fixed = $this->withToken($token)->postJson('/api/checkout/coupon', [
            'code' => 'SAVE10',
            'skuId' => $sku->id,
        ]);
        $fixed->assertOk()->assertJsonPath('data.discountToman', 10000);

        $create = $this->withToken($token)->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['playerId' => '5123456789', 'id_ack' => '1'],
            'couponCode' => 'HALF-CAP',
        ], ['Idempotency-Key' => 'coupon-aaaa-aaaa-aaaa-aaaaaaaaaaaa']);
        $create->assertOk()
            ->assertJsonPath('data.couponCode', 'HALF-CAP')
            ->assertJsonPath('data.discountToman', $expectedPercent)
            ->assertJsonPath('data.amountToman', $subtotal - $expectedPercent);

        $again = $this->withToken($token)->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['playerId' => '5123456789', 'id_ack' => '1'],
            'couponCode' => 'HALF-CAP',
        ], ['Idempotency-Key' => 'coupon-bbbb-bbbb-bbbb-bbbbbbbbbbbb']);
        $again->assertStatus(422)->assertJsonPath('error.code', 'COUPON_USAGE_LIMIT');
    }

    private function shopToken(string $phone): string
    {
        $this->postJson('/api/auth/request-otp', ['phoneNumber' => $phone])->assertOk();
        $verify = $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => $phone,
            'code' => '123456',
        ]);
        $verify->assertOk();

        return (string) $verify->json('data.access_token');
    }

    private function adminToken(): string
    {
        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $login->assertOk();

        return (string) $login->json('data.access_token');
    }
}

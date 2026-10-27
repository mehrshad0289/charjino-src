<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Sku;
use App\Modules\Orders\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZarinpalPaymentTest extends TestCase
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
            'charjino.supplier.allow_live_order' => false,
            'charjino.supplier.s2t_key' => '',
            'charjino.supplier.fazercards_allow_live_order' => false,
        ]);
    }

    public function test_pay_requests_zarinpal_and_returns_start_url(): void
    {
        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'message' => 'Success',
                    'authority' => self::AUTHORITY,
                    'fee_type' => 'Merchant',
                    'fee' => 100,
                ],
                'errors' => [],
            ]),
        ]);

        [$token, $number] = $this->createOrder('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaa1');
        $order = Order::query()->where('number', $number)->firstOrFail();

        $pay = $this->postJson('/api/orders/'.$number.'/pay', [
            'returnOrigin' => 'http://localhost:3001',
        ], ['Authorization' => 'Bearer '.$token]);
        $pay->assertOk()
            ->assertJsonPath('data.provider', 'zarinpal')
            ->assertJsonPath('data.authority', self::AUTHORITY)
            ->assertJsonPath('data.redirectUrl', 'https://sandbox.zarinpal.com/pg/StartPay/'.self::AUTHORITY);

        $payment = Payment::query()->where('authority', self::AUTHORITY)->first();
        $this->assertNotNull($payment);
        $this->assertSame('zarinpal', $payment->provider);
        $this->assertSame(Payment::STATUS_REDIRECTED, $payment->status);
        $this->assertSame((int) $order->amount_toman * 10, (int) data_get($payment->raw, 'amount_rial'));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($order) {
            if (! str_ends_with($request->url(), 'request.json')) {
                return false;
            }
            $data = $request->data();

            return (int) ($data['amount'] ?? 0) === ((int) $order->amount_toman * 10)
                && ($data['currency'] ?? '') === 'IRR'
                && ($data['callback_url'] ?? '') === 'http://localhost:3001/payments/zarinpal'
                && ($data['metadata']['auto_verify'] ?? null) === false
                && ($data['metadata']['order_id'] ?? '') === $order->number
                && ! isset($data['merchant']);
        });
    }

    public function test_verify_success_and_duplicate_callback_does_not_double_fulfill(): void
    {
        $this->fakeGateway();
        [$token, $number] = $this->createOrder('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbb1');
        $this->postJson('/api/orders/'.$number.'/pay', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $body = ['authority' => self::AUTHORITY, 'status' => 'OK'];
        $first = $this->postJson('/api/payments/verify/zarinpal', $body);
        $first->assertOk()
            ->assertJsonPath('data.number', $number)
            ->assertJsonPath('data.paymentProvider', 'zarinpal')
            ->assertJsonPath('data.paymentRef', '201');

        $order = Order::query()->where('number', $number)->firstOrFail();
        $this->assertContains($order->status, [Order::STATUS_PAID, Order::STATUS_FULFILLING, Order::STATUS_DELIVERED]);
        $paidAt = $order->status;

        $again = $this->postJson('/api/payments/verify/zarinpal', $body);
        $again->assertOk()->assertJsonPath('data.number', $number);
        $order->refresh();
        $this->assertSame($paidAt, $order->status);
        $this->assertSame(1, Payment::query()->where('authority', self::AUTHORITY)->where('status', Payment::STATUS_VERIFIED)->count());

        $this->get('/api/payments/callback/zarinpal?Authority='.self::AUTHORITY.'&Status=OK')
            ->assertRedirect();
    }

    public function test_user_cancel_fails_payment(): void
    {
        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => self::AUTHORITY,
                ],
                'errors' => [],
            ]),
        ]);
        [$token, $number] = $this->createOrder('cccccccc-cccc-cccc-cccc-ccccccccccc1');
        $this->postJson('/api/orders/'.$number.'/pay', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->postJson('/api/payments/verify/zarinpal', [
            'authority' => self::AUTHORITY,
            'status' => 'NOK',
        ])->assertStatus(400)->assertJsonPath('error.code', 'PAYMENT_FAILED');

        $payment = Payment::query()->where('authority', self::AUTHORITY)->firstOrFail();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $order = Order::query()->where('number', $number)->firstOrFail();
        $this->assertSame(Order::STATUS_AWAITING_PAYMENT, $order->status);
    }

    public function test_amount_mismatch_goes_to_review_without_fulfill(): void
    {
        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => self::AUTHORITY,
                ],
                'errors' => [],
            ]),
            'https://sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => [],
                'errors' => [
                    'message' => 'amounts values is not the same',
                    'code' => -50,
                ],
            ]),
        ]);
        [$token, $number] = $this->createOrder('dddddddd-dddd-dddd-dddd-ddddddddddd1');
        $this->postJson('/api/orders/'.$number.'/pay', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->postJson('/api/payments/verify/zarinpal', [
            'authority' => self::AUTHORITY,
            'status' => 'OK',
        ])->assertStatus(400)->assertJsonPath('error.code', 'PAYMENT_AMOUNT_MISMATCH');

        $payment = Payment::query()->where('authority', self::AUTHORITY)->firstOrFail();
        $this->assertSame(Payment::STATUS_REVIEW, $payment->status);
        $order = Order::query()->where('number', $number)->firstOrFail();
        $this->assertSame(Order::STATUS_AWAITING_PAYMENT, $order->status);
    }

    public function test_verified_after_expiry_marks_refund_pending(): void
    {
        $this->fakeGateway();
        [$token, $number] = $this->createOrder('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeee1');
        $this->postJson('/api/orders/'.$number.'/pay', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $order = Order::query()->where('number', $number)->firstOrFail();
        $order->expires_at = now()->subMinute();
        $order->save();

        $this->postJson('/api/payments/verify/zarinpal', [
            'authority' => self::AUTHORITY,
            'status' => 'OK',
        ])->assertOk()->assertJsonPath('data.status', Order::STATUS_REFUND_PENDING);

        $payment = Payment::query()->where('authority', self::AUTHORITY)->firstOrFail();
        $this->assertSame(Payment::STATUS_VERIFIED, $payment->status);
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_expire_unpaid_inquires_zarinpal_before_expiring(): void
    {
        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => self::AUTHORITY],
                'errors' => [],
            ]),
            'https://sandbox.zarinpal.com/pg/v4/payment/inquiry.json' => Http::response([
                'data' => ['status' => 'PAID', 'code' => 100, 'message' => 'ok'],
                'errors' => [],
            ]),
            'https://sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'message' => 'Verified',
                    'ref_id' => 201,
                    'card_pan' => '502229******5995',
                    'fee_type' => 'Merchant',
                    'fee' => 0,
                ],
                'errors' => [],
            ]),
        ]);
        [$token, $number] = $this->createOrder('ffffffff-ffff-ffff-ffff-fffffffffff1');
        $this->postJson('/api/orders/'.$number.'/pay', [], ['Authorization' => 'Bearer '.$token])->assertOk();
        $order = Order::query()->where('number', $number)->firstOrFail();
        $order->expires_at = now()->subMinute();
        $order->save();

        $expired = app(OrderService::class)->expireUnpaid();
        $this->assertSame(0, $expired);
        $order->refresh();
        $this->assertSame(Order::STATUS_REFUND_PENDING, $order->status);
    }

    public function test_code_101_is_success(): void
    {
        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => self::AUTHORITY],
                'errors' => [],
            ]),
            'https://sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => [
                    'code' => 101,
                    'message' => 'Verified',
                    'ref_id' => 909,
                ],
                'errors' => [],
            ]),
        ]);
        [$token, $number] = $this->createOrder('99999999-9999-9999-9999-999999999991');
        $this->postJson('/api/orders/'.$number.'/pay', [], ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->postJson('/api/payments/verify/zarinpal', [
            'authority' => self::AUTHORITY,
            'status' => 'OK',
        ])->assertOk()->assertJsonPath('data.paymentRef', '909');
    }

    private function fakeGateway(): void
    {
        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'message' => 'Success',
                    'authority' => self::AUTHORITY,
                    'fee_type' => 'Merchant',
                    'fee' => 100,
                ],
                'errors' => [],
            ]),
            'https://sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'message' => 'Verified',
                    'card_hash' => '1EBE3EBEBE35C7EC0F8D6EE4F2F859107A87822CA179BC9528767EA7B5489B69',
                    'card_pan' => '502229******5995',
                    'ref_id' => 201,
                    'fee_type' => 'Merchant',
                    'fee' => 0,
                ],
                'errors' => [],
            ]),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createOrder(string $idempotency): array
    {
        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ], [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => $idempotency,
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
}

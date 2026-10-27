<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZibalPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config([
            'charjino.orders.mock_payments' => false,
            'charjino.orders.payment_driver' => 'zibal',
            'payments.driver' => 'zibal',
            'charjino.zibal.merchant' => 'zibal',
            'charjino.zibal.callback_url' => 'http://localhost:3001/payments/zibal',
            'charjino.zibal.base_url' => 'https://gateway.zibal.ir/v1/',
            'charjino.zibal.start_pay_url' => 'https://gateway.zibal.ir/start/',
            'charjino.supplier.allow_live_order' => false,
            'charjino.supplier.s2t_key' => '',
        ]);
    }

    public function test_pay_requests_zibal_and_returns_start_url(): void
    {
        Http::fake([
            'https://gateway.zibal.ir/v1/request' => Http::response([
                'result' => 100,
                'trackId' => '44556677',
                'message' => 'success',
            ]),
        ]);

        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => '55555555-5555-5555-5555-555555555555',
        ];

        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ], $headers);
        $create->assertOk();
        $number = $create->json('data.number');

        $pay = $this->postJson('/api/orders/'.$number.'/pay', [
            'returnOrigin' => 'http://localhost:3001',
        ], ['Authorization' => 'Bearer '.$token]);
        $pay->assertOk()
            ->assertJsonPath('data.provider', 'zibal')
            ->assertJsonPath('data.authority', '44556677')
            ->assertJsonPath('data.redirectUrl', 'https://gateway.zibal.ir/start/44556677');

        $order = Order::query()->where('number', $number)->firstOrFail();
        $payment = Payment::query()->where('authority', '44556677')->first();
        $this->assertNotNull($payment);
        $this->assertSame('zibal', $payment->provider);
        $this->assertSame(Payment::STATUS_REDIRECTED, $payment->status);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($order) {
            if (! str_contains($request->url(), '/v1/request')) {
                return false;
            }
            $data = $request->data();

            return (int) ($data['amount'] ?? 0) === ((int) $order->amount_toman * 10)
                && (string) ($data['orderId'] ?? '') === $order->number
                && (string) ($data['callbackUrl'] ?? '') === 'http://localhost:3001/payments/zibal'
                && (string) ($data['merchant'] ?? '') === 'zibal';
        });
    }

    public function test_verify_zibal_marks_paid_and_is_idempotent(): void
    {
        Http::fake([
            'https://gateway.zibal.ir/v1/request' => Http::response([
                'result' => 100,
                'trackId' => '99887766',
            ]),
            'https://gateway.zibal.ir/v1/verify' => Http::response([
                'result' => 100,
                'refNumber' => '4968301',
                'cardNumber' => '999999******9999',
                'amount' => null,
                'paidAt' => '2026-09-29 19:00:00',
                'message' => 'success',
            ]),
        ]);

        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => '66666666-6666-6666-6666-666666666666',
        ];
        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ], $headers);
        $number = $create->json('data.number');
        $this->postJson('/api/orders/'.$number.'/pay', [
            'returnOrigin' => 'http://localhost:3001',
        ], ['Authorization' => 'Bearer '.$token])->assertOk();

        $order = Order::query()->where('number', $number)->firstOrFail();
        $verifyBody = [
            'trackId' => '99887766',
            'success' => '1',
            'status' => '1',
            'orderId' => $number,
        ];

        $first = $this->postJson('/api/payments/verify/zibal', $verifyBody);
        $first->assertOk()
            ->assertJsonPath('data.number', $number)
            ->assertJsonPath('data.paymentProvider', 'zibal')
            ->assertJsonPath('data.paymentRef', '4968301')
            ->assertJsonPath('data.paymentCardMasked', '999999******9999');

        $order->refresh();
        $this->assertContains($order->status, [Order::STATUS_PAID, Order::STATUS_FULFILLING, Order::STATUS_DELIVERED]);

        $again = $this->postJson('/api/payments/verify/zibal', $verifyBody);
        $again->assertOk()->assertJsonPath('data.number', $number);

        $redirect = 'http://localhost:3001/payments/zibal?trackId=99887766&success=1&status=1&orderId='.$number;
        $this->get('/api/payments/callback/zibal?trackId=99887766&success=1&status=1&orderId='.$number)
            ->assertRedirect($redirect);
        $this->get('/api/payments/verify/zibal?trackId=99887766&success=1&status=1&orderId='.$number)
            ->assertRedirect($redirect);
    }

    public function test_zibal_user_cancel_fails_payment(): void
    {
        Http::fake([
            'https://gateway.zibal.ir/v1/request' => Http::response([
                'result' => 100,
                'trackId' => '11001100',
            ]),
        ]);

        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => '77777777-7777-7777-7777-777777777777',
        ];
        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ], $headers);
        $number = $create->json('data.number');
        $this->postJson('/api/orders/'.$number.'/pay', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->postJson('/api/payments/verify/zibal', [
            'trackId' => '11001100',
            'success' => '0',
            'status' => '2',
            'orderId' => $number,
        ])->assertStatus(400)->assertJsonPath('error.code', 'PAYMENT_FAILED');
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

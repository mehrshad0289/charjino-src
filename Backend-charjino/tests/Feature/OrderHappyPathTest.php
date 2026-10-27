<?php

namespace Tests\Feature;

use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Models\Sku;
use App\Modules\Fulfillment\Shop2WebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderHappyPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::fake();
        config(['charjino.supplier.allow_live_order' => false]);
        config(['charjino.supplier.s2t_key' => '']);
        config(['charjino.supplier.webhook_secret' => 'test-webhook-secret']);
        config(['charjino.orders.mock_payments' => true]);
    }

    public function test_validate_draft_mock_psp_paid_dry_run_fulfill_then_webhook_delivered(): void
    {
        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->shop2_item_id = 12;
        $sku->save();

        $headers = ['Authorization' => 'Bearer '.$token];

        $validate = $this->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ], $headers);
        $validate->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.playerName', '5123456789')
            ->assertJsonPath('data.identityKind', 'player')
            ->assertJsonPath('data.validation', 'skipped')
            ->assertJsonPath('data.validateMode', 'manual');

        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['playerId' => '5123456789', 'id_ack' => '1'],
        ], $headers + ['Idempotency-Key' => '11111111-1111-1111-1111-111111111111']);
        $create->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DRAFT)
            ->assertJsonPath('data.playerId', '5123456789')
            ->assertJsonMissingPath('data.costUsd')
            ->assertJsonMissingPath('data.costUsdSnapshot');

        $number = $create->json('data.number');
        $this->assertNotEmpty($number);

        $again = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['playerId' => '5123456789'],
        ], $headers + ['Idempotency-Key' => '11111111-1111-1111-1111-111111111111']);
        $again->assertOk()->assertJsonPath('data.number', $number);

        $pay = $this->postJson('/api/orders/'.$number.'/pay', [], $headers);
        $pay->assertOk()
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.order.status', Order::STATUS_PENDING_PAYMENT);
        $authority = $pay->json('data.authority');

        $callback = $this->postJson('/api/payments/callback/mock', [
            'authority' => $authority,
            'status' => 'OK',
        ]);
        $callback->assertOk();

        $order = Order::query()->where('number', $number)->firstOrFail();
        $this->assertSame(Order::STATUS_FULFILLING, $order->status);
        $this->assertTrue($order->toCustomer()['dryRun']);

        $attempt = $order->latestAttempt();
        $this->assertNotNull($attempt);
        $this->assertTrue($attempt->dry_run);
        $this->assertSame(FulfillmentAttempt::STATUS_PENDING, $attempt->status);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
            $attempt->supplier_order_id
        );

        Http::assertNothingSent();

        $raw = json_encode([
            'event' => 'order.completed',
            'order_id' => $attempt->supplier_order_id,
            'status' => 'completed',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertIsString($raw);

        $ok = $this->postShop2Webhook($raw, 'order.completed');
        $ok->assertOk()->assertJsonPath('data.event', 'order.completed');

        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()?->status);

        $show = $this->getJson('/api/orders/'.$number, $headers);
        $show->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DELIVERED)
            ->assertJsonMissingPath('data.costUsdSnapshot')
            ->assertJsonMissingPath('data.supplierOrderId');

        $dup = $this->postShop2Webhook($raw, 'order.completed');
        $dup->assertOk()->assertJsonPath('data.duplicate', true);
        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()?->status);
    }

    public function test_mock_psp_browser_get_redirects_to_shop_invoice(): void
    {
        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => '33333333-3333-3333-3333-333333333333',
        ];

        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ], $headers);
        $create->assertOk();
        $number = $create->json('data.number');

        $pay = $this->postJson('/api/orders/'.$number.'/pay', [], ['Authorization' => 'Bearer '.$token]);
        $pay->assertOk();
        $authority = $pay->json('data.authority');

        $this->get('/api/payments/callback/mock?authority='.urlencode((string) $authority).'&status=OK')
            ->assertRedirect('http://127.0.0.1:3001/invoices/'.$number.'?from=psp');
    }

    public function test_mock_psp_browser_get_redirects_to_shop_return_origin(): void
    {
        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => '44444444-4444-4444-4444-444444444444',
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
        $pay->assertOk();
        $authority = $pay->json('data.authority');
        $this->assertStringContainsString('front=http%3A%2F%2Flocalhost%3A3001', (string) $pay->json('data.redirectUrl'));

        $this->get('/api/payments/callback/mock?authority='.urlencode((string) $authority).'&status=OK&front='.urlencode('http://localhost:3001'))
            ->assertRedirect('http://localhost:3001/invoices/'.$number.'?from=psp');
    }

    public function test_webhook_rejects_invalid_hmac_signature(): void
    {
        $raw = json_encode([
            'event' => 'order.completed',
            'order_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'status' => 'completed',
        ]);
        $this->assertIsString($raw);

        $response = $this->postShop2Webhook($raw, 'order.completed', 'sha256='.str_repeat('0', 64));
        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'WEBHOOK_INVALID_SIGNATURE');
    }

    public function test_rejects_game_password_on_checkout(): void
    {
        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();

        $response = $this->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => [
                'playerId' => '5123456789',
                'password' => 'secret-game-login',
            ],
        ], ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(400)->assertJsonPath('error.code', 'FORBIDDEN_BUYER_FIELD');
    }

    public function test_idempotency_conflict_on_different_body(): void
    {
        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => '22222222-2222-2222-2222-222222222222',
        ];

        $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '111'],
        ], $headers)->assertOk();

        $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '222'],
        ], $headers)->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_second_checkout_after_paid_same_key_opens_new_draft(): void
    {
        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => '88888888-8888-8888-8888-888888888888',
        ];
        $body = [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ];

        $first = $this->postJson('/api/orders', $body, $headers);
        $first->assertOk();
        $firstNumber = $first->json('data.number');
        $pay = $this->postJson('/api/orders/'.$firstNumber.'/pay', [], ['Authorization' => 'Bearer '.$token]);
        $pay->assertOk();
        $this->postJson('/api/payments/callback/mock', [
            'authority' => $pay->json('data.authority'),
            'status' => 'OK',
        ])->assertOk();

        $second = $this->postJson('/api/orders', $body, $headers);
        $second->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DRAFT);
        $this->assertNotSame($firstNumber, $second->json('data.number'));
        $this->assertNotSame(Order::STATUS_DRAFT, Order::query()->where('number', $firstNumber)->value('status'));
    }

    public function test_two_users_can_buy_the_same_sku_even_with_the_same_idempotency_key(): void
    {
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $body = [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ];
        $sharedKey = 'cccccccc-cccc-cccc-cccc-cccccccccccc';

        $first = $this->postJson('/api/orders', $body, [
            'Authorization' => 'Bearer '.$this->shopToken('09123456789'),
            'Idempotency-Key' => $sharedKey,
        ]);
        $first->assertOk()->assertJsonPath('data.status', Order::STATUS_DRAFT);

        $second = $this->postJson('/api/orders', $body, [
            'Authorization' => 'Bearer '.$this->shopToken('09120000000'),
            'Idempotency-Key' => $sharedKey,
        ]);
        $second->assertOk()->assertJsonPath('data.status', Order::STATUS_DRAFT);
        $this->assertNotSame($first->json('data.number'), $second->json('data.number'));
        $this->assertSame(2, Order::query()->where('sku_id', $sku->id)->count());
    }

    public function test_unpaid_retry_same_body_returns_existing_draft(): void
    {
        $token = $this->shopToken('09123456789');
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        ];
        $body = [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ];

        $first = $this->postJson('/api/orders', $body, $headers);
        $first->assertOk();
        $again = $this->postJson('/api/orders', $body, $headers);
        $again->assertOk()->assertJsonPath('data.number', $first->json('data.number'));
    }

    public function test_new_key_same_product_cancels_previous_unpaid(): void
    {
        $token = $this->shopToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $auth = ['Authorization' => 'Bearer '.$token];
        $body = [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ];

        $first = $this->postJson('/api/orders', $body, $auth + ['Idempotency-Key' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa']);
        $first->assertOk()->assertJsonPath('data.status', Order::STATUS_DRAFT);
        $firstNumber = $first->json('data.number');

        $second = $this->postJson('/api/orders', $body, $auth + ['Idempotency-Key' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb']);
        $second->assertOk()->assertJsonPath('data.status', Order::STATUS_DRAFT);
        $this->assertNotSame($firstNumber, $second->json('data.number'));
        $this->assertSame(Order::STATUS_CANCELLED, Order::query()->where('number', $firstNumber)->value('status'));
    }

    private function shopToken(string $phone = '09123456789'): string
    {
        $this->postJson('/api/auth/request-otp', ['phoneNumber' => $phone])->assertOk();
        $verify = $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => $phone,
            'code' => '123456',
        ]);
        $verify->assertOk();

        return (string) $verify->json('data.access_token');
    }

    private function postShop2Webhook(string $raw, string $event, ?string $signature = null): \Illuminate\Testing\TestResponse
    {
        $sig = $signature ?? Shop2WebhookSignature::expected($raw, (string) config('charjino.supplier.webhook_secret'));

        return $this->call(
            'POST',
            '/api/webhooks/shop2topup',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SHOP2TOPUP_SIGNATURE' => $sig,
                'HTTP_X_SHOP2TOPUP_EVENT' => $event,
            ],
            $raw,
        );
    }
}

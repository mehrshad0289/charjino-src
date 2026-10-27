<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FazerCardsBillingProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['charjino.supplier.fazercards_key' => '']);
        config(['charjino.supplier.allow_live_order' => false]);
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);
    }

    public function test_orders_use_page_limit_not_cursor(): void
    {
        $token = $this->saveKey();
        Http::fake(function (Request $request) {
            $path = parse_url((string) $request->url(), PHP_URL_PATH) ?: '';
            if ($request->method() === 'GET' && str_ends_with($path, '/orders/ord-1')) {
                return Http::response([
                    'ok' => true,
                    'order' => ['id' => 'ord-1', 'status' => 'completed'],
                ], 200);
            }
            if ($request->method() === 'GET' && str_ends_with($path, '/orders')) {
                return Http::response([
                    'ok' => true,
                    'items' => [['id' => 'ord-1', 'status' => 'completed']],
                    'total' => 1,
                    'page' => 2,
                    'limit' => 100,
                ], 200);
            }

            return Http::response(['ok' => false], 404);
        });

        $list = $this->withToken($token)->getJson('/api/admin/fazercards/proxy/orders?page=2&limit=200&cursor=skip-me');
        $list->assertOk()
            ->assertJsonPath('data.data.ok', true)
            ->assertJsonPath('data.data.total', 1)
            ->assertJsonPath('data.data.page', 2)
            ->assertJsonPath('data.data.items.0.id', 'ord-1');

        Http::assertSent(function (Request $request) {
            $url = (string) $request->url();

            return $request->method() === 'GET'
                && str_contains($url, '/api/v2/orders?')
                && str_contains($url, 'page=2')
                && str_contains($url, 'limit=100')
                && ! str_contains($url, 'cursor=');
        });

        $detail = $this->withToken($token)->getJson('/api/admin/fazercards/proxy/orders/ord-1');
        $detail->assertOk()->assertJsonPath('data.data.order.id', 'ord-1');
    }

    public function test_activation_quote_sends_plan_and_promo(): void
    {
        $token = $this->saveKey();
        Http::fake([
            'https://api.fzr.cards/api/v2/subscription/activation-quote*' => Http::response([
                'ok' => true,
                'plan' => 'gold',
                'listPriceUsd' => '30.00',
                'chargedAmountUsd' => '24.00',
                'firstPurchase' => true,
                'currency' => 'USD',
            ], 200),
            'https://api.fzr.cards/api/v2/subscription/plans' => Http::response([
                'ok' => true,
                'currency' => 'USD',
                'plans' => [
                    ['plan' => 'bronze', 'priceUsd' => '10.00'],
                    ['plan' => 'silver', 'priceUsd' => '20.00'],
                    ['plan' => 'gold', 'priceUsd' => '30.00'],
                ],
            ], 200),
        ]);

        $quote = $this->withToken($token)->getJson(
            '/api/admin/fazercards/proxy/subscription/activation-quote?plan=GOLD&promo_code=WELCOME',
        );
        $quote->assertOk()
            ->assertJsonPath('data.data.chargedAmountUsd', '24.00')
            ->assertJsonPath('data.data.firstPurchase', true);

        Http::assertSent(function (Request $request) {
            $url = (string) $request->url();

            return $request->method() === 'GET'
                && str_contains($url, '/subscription/activation-quote')
                && str_contains($url, 'plan=gold')
                && str_contains($url, 'promo_code=WELCOME');
        });

        $plans = $this->withToken($token)->getJson('/api/admin/fazercards/proxy/subscription/plans');
        $plans->assertOk()
            ->assertJsonPath('data.data.currency', 'USD')
            ->assertJsonPath('data.data.plans.2.plan', 'gold');
    }

    public function test_activate_rejects_unknown_plan_and_needs_live_flag(): void
    {
        $token = $this->saveKey();
        Http::fake();

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/subscription/activate-from-balance', [
                'plan' => 'platinum',
                'confirm' => true,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_PLAN');

        Http::fake([
            'https://api.fzr.cards/api/v2/subscription/activation-quote*' => Http::response([
                'ok' => true,
                'plan' => 'bronze',
                'listPriceUsd' => '10.00',
                'chargedAmountUsd' => '10.00',
                'firstPurchase' => false,
                'currency' => 'USD',
            ], 200),
        ]);

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/subscription/activate-from-balance', [
                'plan' => 'bronze',
                'confirm' => true,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'LIVE_ORDER_LOCKED');
    }

    public function test_auto_renew_sends_plan_auto_renew_and_upgrade_checks_wallet(): void
    {
        $token = $this->saveKey();
        config(['charjino.supplier.allow_live_order' => true]);
        $this->withToken($token)->putJson('/api/admin/fazercards/settings', ['allowLiveOrder' => true])->assertOk();

        Http::fake(function (Request $request) {
            $url = (string) $request->url();
            if ($request->method() === 'PATCH' && str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/subscription/auto-renew')) {
                return Http::response([
                    'ok' => true,
                    'plan' => 'bronze',
                    'planExpiresAt' => '2026-10-30T00:00:00.000Z',
                    'planAutoRenew' => true,
                    'subscriptionActive' => true,
                    'currency' => 'USD',
                ], 200);
            }
            if ($request->method() === 'GET' && str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/subscription')) {
                return Http::response([
                    'ok' => true,
                    'plan' => 'bronze',
                    'planExpiresAt' => '2026-10-03T00:00:00.000Z',
                    'planAutoRenew' => false,
                    'subscriptionActive' => true,
                    'currency' => 'USD',
                ], 200);
            }
            if (str_contains($url, '/subscription/plans')) {
                return Http::response([
                    'ok' => true,
                    'currency' => 'USD',
                    'plans' => [
                        ['plan' => 'bronze', 'priceUsd' => '10.00'],
                        ['plan' => 'gold', 'priceUsd' => '30.00'],
                    ],
                ], 200);
            }
            if (str_contains($url, '/balance')) {
                return Http::response(['ok' => true, 'balance' => '50.0000', 'currency' => 'USD'], 200);
            }
            if ($request->method() === 'PATCH' && str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/subscription/plan')) {
                return Http::response([
                    'ok' => true,
                    'plan' => 'gold',
                    'planExpiresAt' => '2026-10-30T00:00:00.000Z',
                    'planAutoRenew' => true,
                    'subscriptionActive' => true,
                    'currency' => 'USD',
                ], 200);
            }

            return Http::response(['ok' => false], 404);
        });

        $renew = $this->withToken($token)->patchJson('/api/admin/fazercards/proxy/subscription/auto-renew', [
            'enabled' => true,
            'confirm' => true,
        ]);
        $renew->assertOk()->assertJsonPath('data.data.planAutoRenew', true);
        Http::assertSent(function (Request $request) {
            return $request->method() === 'PATCH'
                && str_contains((string) $request->url(), '/subscription/auto-renew')
                && ($request->data()['planAutoRenew'] ?? null) === true
                && ! array_key_exists('enabled', $request->data());
        });

        $upgrade = $this->withToken($token)->patchJson('/api/admin/fazercards/proxy/subscription/plan', [
            'plan' => 'gold',
            'confirm' => true,
        ]);
        $upgrade->assertOk()->assertJsonPath('data.data.plan', 'gold');
        Http::assertSent(function (Request $request) {
            return $request->method() === 'PATCH'
                && str_contains((string) $request->url(), '/subscription/plan')
                && ($request->data()['plan'] ?? null) === 'gold';
        });
    }

    public function test_invoice_create_does_not_need_live_order_and_verify_needs_binance_id(): void
    {
        $token = $this->saveKey();
        Http::fake([
            'https://api.fzr.cards/api/v2/payments/methods' => Http::response([
                'ok' => true,
                'items' => [[
                    'code' => 'USDT_TRC20',
                    'label' => 'USDT TRC20',
                    'minAmountUsd' => 5,
                    'maxAmountUsd' => 500,
                ]],
            ], 200),
            'https://api.fzr.cards/api/v2/payments/create' => Http::response([
                'ok' => true,
                'payment' => [
                    'id' => 'tx123',
                    'network' => 'TRC20',
                    'amount' => '10',
                    'uniqueAmount' => '10.001',
                    'address' => 'Taddr',
                    'memo' => null,
                    'binanceId' => null,
                    'displayName' => 'USDT TRC20',
                    'status' => 'pending',
                    'verifyAttempts' => 0,
                    'expiresAt' => '2026-10-01T00:00:00.000Z',
                    'completedAt' => null,
                    'cancelledAt' => null,
                    'createdAt' => '2026-09-30T00:00:00.000Z',
                ],
            ], 201),
            'https://api.fzr.cards/api/v2/payments/tx123' => Http::response([
                'ok' => true,
                'payment' => [
                    'id' => 'tx123',
                    'network' => 'TRC20',
                    'amount' => '10',
                    'status' => 'pending',
                    'verifyAttempts' => 0,
                    'expiresAt' => '2026-10-01T00:00:00.000Z',
                    'createdAt' => '2026-09-30T00:00:00.000Z',
                ],
            ], 200),
            'https://api.fzr.cards/api/v2/payments/tx123/verify' => Http::response([
                'ok' => true,
                'payment' => ['id' => 'tx123', 'status' => 'completed', 'verifyAttempts' => 1],
                'verifySuccess' => true,
                'message' => 'ok',
            ], 200),
        ]);

        $methods = $this->withToken($token)->getJson('/api/admin/fazercards/proxy/payments/methods');
        $methods->assertOk()->assertJsonPath('data.data.items.0.code', 'USDT_TRC20');

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/payments/create', [
                'method' => 'USDT_TRC20',
                'amount' => 10,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'LIVE_ORDER_UNCONFIRMED');

        $invoice = $this->withToken($token)->postJson('/api/admin/fazercards/proxy/payments/create', [
            'method' => 'USDT_TRC20',
            'amount' => 10,
            'confirm' => true,
        ]);
        $invoice->assertOk()
            ->assertJsonPath('data.status', 201)
            ->assertJsonPath('data.data.payment.id', 'tx123')
            ->assertJsonPath('data.data.payment.status', 'pending');

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/payments/tx123/verify', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'MISSING_REQUIRED_FIELD');

        $verify = $this->withToken($token)->postJson('/api/admin/fazercards/proxy/payments/tx123/verify', [
            'binanceOrderId' => 'bn-99',
        ]);
        $verify->assertOk()
            ->assertJsonPath('data.data.verifySuccess', true)
            ->assertJsonPath('data.data.payment.id', 'tx123');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_contains((string) $request->url(), '/payments/tx123/verify')
                && ($request->data()['binanceOrderId'] ?? null) === 'bn-99';
        });

        $detail = $this->withToken($token)->getJson('/api/admin/fazercards/proxy/payments/tx123');
        $detail->assertOk()->assertJsonPath('data.data.payment.id', 'tx123');
    }

    public function test_binance_verify_cannot_go_through_fetch(): void
    {
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('تأیید Binance باید از هلپر binanceOrderId عبور کند.');
        $this->app->make(\App\Modules\Supplier\FazerCards\FazerCardsClient::class)
            ->fetch('payments/tx123/verify', 'POST', [], ['binanceOrderId' => 'bn-99']);
    }

    public function test_create_payment_requires_method_and_amount(): void
    {
        $token = $this->saveKey();
        Http::fake();
        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/payments/create', [
                'method' => '',
                'amount' => 0,
                'confirm' => true,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'MISSING_REQUIRED_FIELD');
    }

    private function saveKey(): string
    {
        $token = $this->adminToken();
        $this->withToken($token)->putJson('/api/admin/fazercards/key', [
            'apiKey' => 'fc_test_secret_key_abcd',
        ])->assertOk();

        return $token;
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

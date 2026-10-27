<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\Sku;
use App\Modules\Supplier\FazerCards\FazerCardsCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FazerCardsAdminTest extends TestCase
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

    public function test_admin_can_save_key_without_echoing_secret(): void
    {
        $token = $this->adminToken();

        $save = $this->withToken($token)->putJson('/api/admin/fazercards/key', [
            'apiKey' => 'fc_test_secret_key_abcd',
        ]);
        $save->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.keyLast4', 'abcd');
        $this->assertStringNotContainsString('fc_test_secret_key_abcd', $save->getContent());
        $this->assertNotNull(Setting::getValue(FazerCardsCredentials::SETTING_KEY));
        $this->assertNotSame('fc_test_secret_key_abcd', Setting::getValue(FazerCardsCredentials::SETTING_KEY));

        Http::fake([
            'https://api.fzr.cards/api/v2/me' => Http::response([
                'ok' => true,
                'login' => 'lab',
                'email' => 'ops@example.com',
                'plan' => 'gold',
                'planExpiresAt' => '2026-10-03',
                'subscriptionActive' => true,
            ], 200),
            'https://api.fzr.cards/api/v2/balance' => Http::response([
                'ok' => true,
                'balance' => '0.0000',
                'currency' => 'USD',
            ], 200),
            'https://api.fzr.cards/api/v2/subscription' => Http::response([
                'ok' => true,
                'plan' => 'gold',
                'planExpiresAt' => '2026-10-03T00:00:00.000Z',
                'planAutoRenew' => true,
                'subscriptionActive' => true,
                'currency' => 'USD',
            ], 200),
        ]);

        $status = $this->withToken($token)->getJson('/api/admin/fazercards');
        $status->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.keyLast4', 'abcd')
            ->assertJsonPath('data.account.email', 'ops@example.com')
            ->assertJsonPath('data.account.wallet', '0.0000')
            ->assertJsonPath('data.account.planAutoRenew', true)
            ->assertJsonPath('data.account.active', true)
            ->assertJsonPath('data.account.currency', 'USD')
            ->assertJsonPath('data.catalogScopes.0.id', 'steam-topup');
        $this->assertCount(8, $status->json('data.catalogScopes'));
        $this->assertStringNotContainsString('fc_test_secret_key_abcd', $status->getContent());
    }

    public function test_proxy_rejects_unknown_and_unconfirmed_paid(): void
    {
        $token = $this->adminToken();
        $this->withToken($token)->putJson('/api/admin/fazercards/key', [
            'apiKey' => 'fc_test_secret_key_abcd',
        ])->assertOk();

        $this->withToken($token)
            ->getJson('/api/admin/fazercards/proxy/not-a-real-path')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'UNKNOWN_ENDPOINT');

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/topups/order', [
                'category_id' => 'pubg_mobile_auto',
                'offer_id' => '60_uc',
                'fields' => ['player_id' => '1'],
                'confirm' => true,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'LIVE_ORDER_LOCKED');

        config(['charjino.supplier.allow_live_order' => true]);
        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/topups/order', [
                'category_id' => 'pubg_mobile_auto',
                'offer_id' => '60_uc',
                'fields' => ['player_id' => '1'],
                'confirm' => true,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'LIVE_ORDER_LOCKED');

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/funpay/login', [
                'email' => 'ops@example.com',
                'password' => 'super-secret-pass',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'LIVE_ORDER_UNCONFIRMED');
    }

    public function test_validate_unsupported_is_soft_skip_and_webhook_secret_is_redacted(): void
    {
        $token = $this->adminToken();
        $this->withToken($token)->putJson('/api/admin/fazercards/key', [
            'apiKey' => 'fc_test_secret_key_abcd',
        ])->assertOk();

        Http::fake([
            'https://api.fzr.cards/api/v2/topups/validate-id' => Http::response([
                'ok' => true,
                'items' => [
                    ['category_id' => 'pubg_mobile', 'fields' => [['key' => 'player_id']]],
                ],
            ], 200),
            'https://api.fzr.cards/api/v2/account/webhook' => Http::response([
                'ok' => true,
                'url' => 'https://charjino.test/api/webhooks/fazercards',
                'secret' => 'whsec_should_never_leak_zzzz',
            ], 200),
            'https://api.fzr.cards/api/v2/funpay/login' => Http::response([
                'ok' => true,
                'user' => [
                    'id' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                    'email' => 'ops@example.com',
                    'login' => 'lab',
                    'plan' => 'gold',
                    'planExpiresAt' => '2026-10-03T00:00:00.000Z',
                    'addonActiveUntil' => '2026-11-03T00:00:00.000Z',
                ],
                'apiKey' => 'fp_live_super_secret_key',
            ], 200),
        ]);

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/topups/validate-id', [
                'category_id' => 'codm_activision_us',
                'fields' => ['player_id' => '5123456789'],
            ])
            ->assertOk()
            ->assertJsonPath('data.data.validation', 'unsupported');

        $webhook = $this->withToken($token)->getJson('/api/admin/fazercards/proxy/account/webhook');
        $webhook->assertOk();
        $this->assertStringNotContainsString('whsec_should_never_leak_zzzz', $webhook->getContent());
        $webhook->assertJsonPath('data.data.secret', '****zzzz');

        $login = $this->withToken($token)->postJson('/api/admin/fazercards/proxy/funpay/login', [
            'email' => 'ops@example.com',
            'password' => 'super-secret-pass',
            'confirm' => true,
        ]);
        $login->assertOk()
            ->assertJsonPath('data.data.user.email', 'ops@example.com')
            ->assertJsonPath('data.data.user.addonActiveUntil', '2026-11-03T00:00:00.000Z')
            ->assertJsonPath('data.data.apiKey', '****_key');
        $this->assertStringNotContainsString('fp_live_super_secret_key', $login->getContent());
        $this->assertStringNotContainsString('super-secret-pass', $login->getContent());
        $this->assertSame(
            'abcd',
            $this->app->make(\App\Modules\Supplier\FazerCards\FazerCardsCredentials::class)->keyLast4()
        );
    }

    public function test_funpay_me_matches_openapi_and_login_needs_email(): void
    {
        $token = $this->adminToken();
        $this->withToken($token)->putJson('/api/admin/fazercards/key', [
            'apiKey' => 'fc_test_secret_key_abcd',
        ])->assertOk();

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/funpay/login', [
                'email' => '',
                'password' => 'x',
                'confirm' => true,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'MISSING_REQUIRED_FIELD');

        Http::fake([
            'https://api.fzr.cards/api/v2/funpay/me' => Http::response([
                'ok' => true,
                'user' => [
                    'id' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                    'email' => 'ops@example.com',
                    'login' => 'lab',
                    'plan' => 'gold',
                    'planExpiresAt' => '2026-10-03T00:00:00.000Z',
                    'planAutoRenew' => true,
                    'subscriptionActive' => true,
                    'balanceUsd' => '12.0000',
                ],
                'addon' => [
                    'active' => true,
                    'status' => 'active',
                    'priceUsd' => '9.00',
                    'startedAt' => '2026-09-01T00:00:00.000Z',
                    'currentPeriodStart' => '2026-09-01T00:00:00.000Z',
                    'currentPeriodEnd' => '2026-10-01T00:00:00.000Z',
                    'autoRenew' => true,
                ],
            ], 200),
            'https://api.fzr.cards/api/v2/funpay/login' => Http::response([
                'ok' => false,
                'error' => 'funpay_integration addon is not active',
                'code' => 'FORBIDDEN',
            ], 403),
        ]);

        $me = $this->withToken($token)->getJson('/api/admin/fazercards/proxy/funpay/me');
        $me->assertOk()
            ->assertJsonPath('data.data.user.balanceUsd', '12.0000')
            ->assertJsonPath('data.data.user.subscriptionActive', true)
            ->assertJsonPath('data.data.addon.active', true)
            ->assertJsonPath('data.data.addon.priceUsd', '9.00');

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/proxy/funpay/login', [
                'email' => 'ops@example.com',
                'password' => 'super-secret-pass',
                'confirm' => true,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FUNPAY_ADDON_REQUIRED');
    }

    public function test_funpay_login_cannot_go_through_fetch(): void
    {
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('این عملیات باید با تأیید صریح از پروکسی ادمین عبور کند.');
        $this->app->make(\App\Modules\Supplier\FazerCards\FazerCardsClient::class)
            ->fetch('funpay/login', 'POST', [], [
                'email' => 'ops@example.com',
                'password' => 'super-secret-pass',
            ]);
    }

    public function test_admin_can_toggle_live_order_separately_from_shop2(): void
    {
        $token = $this->adminToken();
        config(['charjino.supplier.allow_live_order' => true]);

        $this->withToken($token)
            ->putJson('/api/admin/fazercards/settings', ['allowLiveOrder' => true])
            ->assertOk()
            ->assertJsonPath('data.liveOrderEnabled', true);

        $this->withToken($token)
            ->putJson('/api/admin/fazercards/settings', ['allowLiveOrder' => false])
            ->assertOk()
            ->assertJsonPath('data.liveOrderEnabled', false);
    }

    public function test_public_fazercards_webhook_is_idempotent(): void
    {
        $raw = json_encode([
            'event' => 'webhook.test',
            'order_id' => '11111111-1111-1111-1111-111111111111',
        ], JSON_THROW_ON_ERROR);
        $sig = \App\Modules\Fulfillment\FazerCardsWebhookSignature::expected(
            $raw,
            (string) config('charjino.supplier.fazercards_webhook_secret'),
        );

        $ok = $this->call(
            'POST',
            '/api/webhooks/fazercards',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
                'HTTP_X_WEBHOOK_EVENT' => 'webhook.test',
            ],
            $raw,
        );
        $ok->assertOk()->assertJsonPath('data.duplicate', false);

        $dup = $this->call(
            'POST',
            '/api/webhooks/fazercards',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
                'HTTP_X_WEBHOOK_EVENT' => 'webhook.test',
            ],
            $raw,
        );
        $dup->assertOk()->assertJsonPath('data.duplicate', true);
    }

    public function test_cod_sku_validate_is_manual_without_supplier_call(): void
    {
        $token = $this->shopToken();
        $product = Product::query()->where('handle', 'cod-mobile')->firstOrFail();
        $product->fazercards_category_id = 'codm_activision_us';
        $product->save();
        $sku = Sku::query()->where('code', 'cp-160')->firstOrFail();
        $sku->fazercards_category_id = 'codm_activision_us';
        $sku->fazercards_offer_id = '88_cp';
        $sku->visible = true;
        $sku->status = 'active';
        $sku->save();

        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        Http::fake([
            'https://api.fzr.cards/api/v2/topups/validate-id' => Http::response([
                'ok' => true,
                'items' => [
                    ['category_id' => 'pubg_mobile', 'fields' => [['key' => 'player_id']]],
                ],
            ], 200),
        ]);

        $detail = $this->getJson('/api/catalog/products/cod-mobile');
        $detail->assertOk()->assertJsonPath('data.validateMode', 'manual');

        $check = $this->withToken($token)->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => '5123456789'],
        ]);
        $check->assertOk()
            ->assertJsonPath('data.playerName', '5123456789')
            ->assertJsonPath('data.validateMode', 'manual')
            ->assertJsonPath('data.validation', 'unsupported');
    }

    public function test_sync_rejects_unknown_family(): void
    {
        $token = $this->adminToken();
        $this->withToken($token)
            ->postJson('/api/admin/fazercards/sync', ['family' => 'vpn'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CATALOG_SCOPE');
    }

    public function test_admin_can_cancel_catalog_sync(): void
    {
        $token = $this->adminToken();
        $this->withToken($token)->putJson('/api/admin/fazercards/key', [
            'apiKey' => 'fc_test_secret_key_abcd',
        ])->assertOk();

        $this->withToken($token)
            ->postJson('/api/admin/fazercards/sync/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_admin_can_search_and_import_steam_gift_without_ordering(): void
    {
        $token = $this->adminToken();
        $this->withToken($token)->putJson('/api/admin/fazercards/key', [
            'apiKey' => 'fc_test_secret_key_abcd',
        ])->assertOk();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($request->url(), PHP_URL_PATH)), '/');
            if ($path === 'me') {
                return Http::response(['ok' => true, 'email' => 'ops@example.com', 'login' => 'lab'], 200);
            }
            if ($path === 'balance') {
                return Http::response(['ok' => true, 'balance' => '12.0000'], 200);
            }
            if ($path === 'steam-gifts/games') {
                return Http::response([
                    'ok' => true,
                    'games' => [
                        ['name' => 'Dota 2', 'appid' => 570],
                        ['name' => 'Counter-Strike 2', 'appid' => 730],
                    ],
                ], 200);
            }
            if ($path === 'steam-gifts/games/730') {
                return Http::response([
                    'ok' => true,
                    'appid' => 730,
                    'name' => 'Counter-Strike 2',
                    'offers' => [[
                        'sub_id' => 42,
                        'name' => 'CS2',
                        'regions' => [['region' => 'US', 'price' => '9.99']],
                    ]],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $search = $this->withToken($token)->getJson('/api/admin/fazercards/steam-gifts/search?q=Counter');
        $search->assertOk()
            ->assertJsonPath('data.items.0.appid', 730)
            ->assertJsonPath('data.items.0.inShop', false);

        $import = $this->withToken($token)->postJson('/api/admin/fazercards/steam-gifts/import', [
            'appId' => 730,
        ]);
        $import->assertOk()->assertJsonPath('data.fazercardsCategoryId', 'steam-gift:730');
        $product = Product::query()->where('fazercards_category_id', 'steam-gift:730')->first();
        $this->assertNotNull($product);
        $this->assertTrue((bool) $product->visible);
        $this->assertTrue((bool) ($product->requirements['shopPinned'] ?? false));
        Http::assertNotSent(fn (\Illuminate\Http\Client\Request $request) => str_contains((string) $request->url(), '/steam-gifts/order'));
    }

    public function test_admin_can_search_and_import_listed_family_without_ordering(): void
    {
        $token = $this->adminToken();
        $this->withToken($token)->putJson('/api/admin/fazercards/key', [
            'apiKey' => 'fc_test_secret_key_abcd',
        ])->assertOk();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($request->url(), PHP_URL_PATH)), '/');
            if ($path === 'me') {
                return Http::response(['ok' => true, 'email' => 'ops@example.com', 'login' => 'lab'], 200);
            }
            if ($path === 'balance') {
                return Http::response(['ok' => true, 'balance' => '12.0000'], 200);
            }
            if ($path === 'topups') {
                return Http::response([
                    'ok' => true,
                    'items' => [
                        ['category_id' => 'blood_strike', 'name' => 'Blood Strike', 'imageurl' => 'https://cdn.example/blood.jpg'],
                        ['category_id' => 'old_game', 'name' => 'Old Game'],
                    ],
                    'meta' => ['has_more' => false],
                ], 200);
            }
            if ($path === 'topups/offers') {
                return Http::response([
                    'ok' => true,
                    'offers' => [
                        ['offer_id' => '60_uc', 'name' => '60 UC', 'price_usd' => '0.84'],
                    ],
                    'fields' => [['name' => 'player_id']],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $search = $this->withToken($token)->getJson('/api/admin/fazercards/catalog/search?family=topups&q=Blood');
        $search->assertOk()
            ->assertJsonPath('data.items.0.remoteId', 'blood_strike')
            ->assertJsonPath('data.items.0.inShop', false);

        $import = $this->withToken($token)->postJson('/api/admin/fazercards/catalog/import', [
            'family' => 'topups',
            'remoteId' => 'blood_strike',
        ]);
        $import->assertOk()->assertJsonPath('data.fazercardsCategoryId', 'blood_strike');
        $product = Product::query()->where('fazercards_category_id', 'blood_strike')->first();
        $this->assertNotNull($product);
        $this->assertTrue((bool) $product->visible);
        $this->assertTrue((bool) ($product->requirements['shopPinned'] ?? false));
        Http::assertNotSent(fn (\Illuminate\Http\Client\Request $request) => str_contains((string) $request->url(), '/topups/order'));
    }

    public function test_health_exposes_fazercards_flag(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('data.fazercardsConfigured', false)
            ->assertJsonPath('data.fazercardsLiveOrderEnabled', false)
            ->assertJsonPath('data.paymentDriver', 'mock');
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

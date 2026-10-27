<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Modules\Supplier\Shop2\Shop2Adapter;
use App\Modules\Supplier\Shop2\Shop2Client;
use App\Modules\Supplier\Shop2\SkuDiscovery;
use App\Modules\Supplier\Shop2\SkuMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Shop2AdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['charjino.supplier.s2t_key' => 'test.key-id.secret']);
        config(['charjino.supplier.allow_live_order' => false]);
        config(['charjino.supplier.s2t_base' => 'https://shop2topup.com/api/endpoints/v1']);
        @unlink(SkuMap::storagePath());
    }

    public function test_maps_ip_not_allowed(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'error' => ['code' => 'IP_NOT_ALLOWED', 'message' => 'nope'],
            ], 403),
        ]);

        try {
            $this->app->make(Shop2Client::class)->account();
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('IP_NOT_ALLOWED', $e->errorCode);
            $this->assertSame(403, $e->status);
        }
    }

    public function test_maps_region_mismatch(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'error' => ['code' => 'REGION_MISMATCH', 'message' => 'region'],
            ], 400),
        ]);

        try {
            $this->app->make(Shop2Adapter::class)->validatePlayer(12, '123456789');
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('REGION_MISMATCH', $e->errorCode);
            $this->assertSame(400, $e->status);
        }
    }

    public function test_maps_insufficient_balance(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'error' => ['code' => 'INSUFFICIENT_BALANCE', 'message' => 'empty'],
            ], 400),
        ]);

        try {
            $this->app->make(Shop2Client::class)->account();
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('INSUFFICIENT_BALANCE', $e->errorCode);
            $this->assertSame(400, $e->status);
        }
    }

    public function test_create_order_stays_locked_and_never_posts(): void
    {
        Http::fake();

        try {
            $this->app->make(Shop2Client::class)->createOrder([
                'order_id' => '01912345-6789-7abc-8def-0123456789ab',
                'sub_category_id' => 12,
                'quantity' => 1,
                'confirm' => true,
                'expected_unit_price' => '0.95',
                'requirements' => ['player_id' => '1'],
            ]);
            $this->fail('expected lock');
        } catch (ApiException $e) {
            $this->assertSame('LIVE_ORDER_LOCKED', $e->errorCode);
        }

        Http::assertNothingSent();
    }

    public function test_smoke_account_discovery_requirements_without_create(): void
    {
        $this->fakeCatalog();

        $this->artisan('charjino:s2t-smoke', ['--discover' => true])
            ->assertSuccessful();

        $map = SkuMap::read();
        $games = array_column($map['skus'], 'game');
        $this->assertContains('PUBG_MOBILE', $games);
        $this->assertContains('COD_MOBILE', $games);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/account'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/catalog/big-categories'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/requirements'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/orders/create'));
    }

    public function test_admin_validate_player_and_live_price(): void
    {
        $this->seed();
        $this->fakeCatalog();

        $token = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/admin/supplier/price?itemId=101')
            ->assertOk()
            ->assertJsonPath('data.unitPrice', '0.950000');

        $this->withToken($token)
            ->postJson('/api/admin/supplier/validate-player', [
                'itemId' => 101,
                'playerId' => '123456789',
            ])
            ->assertOk()
            ->assertJsonPath('data.playerName', 'TestPlayer')
            ->assertJsonPath('data.region', 'Asia');

        $this->withToken($token)
            ->postJson('/api/admin/supplier/validate-player', [
                'itemId' => 101,
                'playerId' => 'account_password',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'PLAYER_NOT_FOUND');
    }

    public function test_discovery_writes_sku_map_without_secrets(): void
    {
        $this->fakeCatalog();
        $map = $this->app->make(SkuDiscovery::class)->discover();
        $json = (string) file_get_contents(SkuMap::storagePath());

        $this->assertStringNotContainsString('secret', $json);
        $this->assertStringNotContainsString('test.key', $json);
        $this->assertGreaterThanOrEqual(2, count($map['skus']));
    }

    private function fakeCatalog(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/account') && $request->method() === 'GET') {
                return Http::response([
                    'success' => true,
                    'account' => ['email' => 'reseller@example.com', 'wallet' => '0.000000', 'active' => true],
                ], 200);
            }
            if (str_contains($url, '/catalog/big-categories')) {
                return Http::response([
                    'success' => true,
                    'data' => ['big_categories' => [
                        ['id' => 1, 'name' => 'PUBG Mobile'],
                        ['id' => 2, 'name' => 'Call of Duty Mobile'],
                    ]],
                ], 200);
            }
            if (str_contains($url, '/catalog/categories') && str_contains($url, 'bigCategoryId=1')) {
                return Http::response([
                    'success' => true,
                    'data' => ['categories' => [
                        ['id' => 10, 'name' => 'Direct Topup'],
                    ]],
                ], 200);
            }
            if (str_contains($url, '/catalog/categories')) {
                return Http::response([
                    'success' => true,
                    'data' => ['categories' => [
                        ['id' => 20, 'name' => 'Direct Topup SG'],
                    ]],
                ], 200);
            }
            if (str_contains($url, '/catalog/subcategories') && str_contains($url, 'categoryId=10')) {
                return Http::response([
                    'success' => true,
                    'data' => ['subcategories' => [
                        ['item_id' => 101, 'name' => '60 UC', 'unit_price' => '0.95'],
                        ['item_id' => 102, 'name' => '300 UC', 'unit_price' => '4.50'],
                    ]],
                ], 200);
            }
            if (str_contains($url, '/catalog/subcategories') && str_contains($url, 'categoryId=20')) {
                return Http::response([
                    'success' => true,
                    'data' => ['subcategories' => [
                        ['item_id' => 201, 'name' => '80 CP', 'unit_price' => '1.10'],
                    ]],
                ], 200);
            }
            if (str_contains($url, '/catalog/category/10/requirements')) {
                return Http::response([
                    'success' => true,
                    'data' => ['requirements' => [
                        ['field_name' => 'player_id', 'data_type' => 'text'],
                        ['field_name' => 'account_password', 'data_type' => 'text'],
                    ]],
                ], 200);
            }
            if (str_contains($url, '/catalog/category/20/requirements')) {
                return Http::response([
                    'success' => true,
                    'data' => ['requirements' => [
                        ['field_name' => 'player_id', 'data_type' => 'text'],
                    ]],
                ], 200);
            }
            if (str_contains($url, '/catalog/subcategory/') && str_contains($url, '/price')) {
                return Http::response([
                    'success' => true,
                    'data' => ['unit_price' => '0.950000'],
                ], 200);
            }
            if (str_contains($url, '/player/validate')) {
                return Http::response([
                    'success' => true,
                    'data' => ['player_name' => 'TestPlayer', 'region' => 'Asia'],
                ], 200);
            }

            return Http::response(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR']], 500);
        });
    }
}

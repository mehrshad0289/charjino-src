<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Sku;
use App\Modules\Catalog\PurchaseSchema;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CodmRegionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const UID = '7387097367248044034';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Setting::putValue('usd_toman_rate', '100000');
        config([
            'charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd',
            'charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2',
            'charjino.codm_region_check.enabled' => true,
            'charjino.codm_region_check.device_id' => '11111111-2222-4333-8444-555555555555',
            'charjino.orders.mock_payments' => true,
        ]);
    }

    public function test_validate_player_match_returns_nickname(): void
    {
        Http::fake([
            'order-us.codashop.com/validate' => Http::response([
                'success' => true,
                'result' => ['type' => 'SUCCESS', 'nickname' => 'badboy.2013', 'shortId' => '2M2MXJ', 'countryId' => 840],
            ]),
            '*' => Http::response(['ok' => true, 'items' => []], 200),
        ]);
        $sku = $this->codmSku();
        $token = $this->shopToken();

        $this->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => ['user_id' => self::UID],
        ], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.playerName', 'badboy.2013')
            ->assertJsonPath('data.validateMode', 'codm_store')
            ->assertJsonPath('data.codmRegion.status', 'match')
            ->assertJsonPath('data.codmRegion.shortId', '2M2MXJ');
    }

    public function test_mismatch_blocks_draft_and_returns_422(): void
    {
        Http::fake([
            'order-us.codashop.com/validate' => Http::response([
                'success' => false,
                'errorCode' => -200,
                'errorMsg' => 'mismatch',
                'homeBaseCountry2Name' => 'IR',
            ]),
            '*' => Http::response(['ok' => true, 'items' => []], 200),
        ]);
        $sku = $this->codmSku();
        $token = $this->shopToken();

        $this->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => ['user_id' => self::UID],
        ], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CODM_REGION_MISMATCH')
            ->assertJsonPath('data.accountCountry', 'IR');

        $before = Order::query()->count();
        $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['user_id' => self::UID, 'id_ack' => '1'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CODM_REGION_MISMATCH');
        $this->assertSame($before, Order::query()->count());
    }

    public function test_unknown_keeps_ack_path(): void
    {
        Http::fake([
            'order-us.codashop.com/validate' => Http::response('<html>challenge</html>', 200, ['Content-Type' => 'text/html']),
            'api.fzr.cards/*' => function ($request) {
                $path = (string) parse_url($request->url(), PHP_URL_PATH);
                if (str_contains($path, 'validate-id') && $request->method() === 'GET') {
                    return Http::response(['ok' => true, 'items' => [['category_id' => 'pubg_mobile', 'fields' => [['key' => 'player_id']]]]], 200);
                }

                return Http::response(['ok' => true], 200);
            },
        ]);
        $sku = $this->codmSku();
        $token = $this->shopToken();

        $this->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => ['user_id' => self::UID],
        ], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.codmRegion.status', 'unknown')
            ->assertJsonPath('data.validation', 'unsupported');

        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['user_id' => self::UID, 'id_ack' => '1'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()]);
        $create->assertOk()->assertJsonPath('data.status', Order::STATUS_DRAFT);
        $order = Order::query()->where('number', $create->json('data.number'))->firstOrFail();
        $this->assertSame('unknown', $order->requirements['codm_region'] ?? null);
    }

    public function test_match_skips_ack_on_draft(): void
    {
        Http::fake([
            'order-us.codashop.com/validate' => Http::response([
                'success' => true,
                'result' => ['type' => 'SUCCESS', 'nickname' => 'badboy.2013', 'shortId' => '2M2MXJ', 'countryId' => 840],
            ]),
            '*' => Http::response(['ok' => true, 'items' => []], 200),
        ]);
        $sku = $this->codmSku();
        $token = $this->shopToken('09123456780');

        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['user_id' => self::UID],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()]);
        $create->assertOk()->assertJsonPath('data.status', Order::STATUS_DRAFT);
        $order = Order::query()->where('number', $create->json('data.number'))->firstOrFail();
        $this->assertSame('match', $order->requirements['codm_region'] ?? null);
        $this->assertArrayNotHasKey('id_ack', $order->requirements);
        $this->assertSame('badboy.2013', $order->player_name);
    }

    public function test_garena_skips_codashop_probe(): void
    {
        Http::fake([
            'order-*.codashop.com/*' => Http::response(['should' => 'not-run'], 500),
            '*' => Http::response(['ok' => true, 'items' => [['category_id' => 'pubg_mobile']]], 200),
        ]);
        $sku = $this->codmSku('codm_garena_sgmy', 'player_id', 'topup-codm-garena-sgmy');
        $token = $this->shopToken();

        $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['player_id' => '99112233', 'id_ack' => '1'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DRAFT);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'codashop.com'));
    }

    private function codmSku(string $category = 'codm_activision_us', string $field = 'user_id', ?string $handle = 'cod-mobile'): Sku
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'شارژ بازی', 'title' => 'شارژ بازی', 'visible' => true, 'sort_order' => 10],
        );
        $handle ??= 'topup-'.$category;
        $product = Product::query()->updateOrCreate(
            ['handle' => $handle],
            [
                'catalog_group_id' => $group->id,
                'code' => $handle,
                'title' => $category,
                'name_en' => $category,
                'publisher' => 'Activision',
                'category_name' => 'شارژ بازی',
                'subtitle' => '',
                'thumbnail' => '/image/catalog/cod-mobile.svg',
                'fazercards_category_id' => $category,
                'fulfillment_type' => FazerCardsIds::TOPUP,
                'visible' => true,
                'status' => 'active',
                'inputs' => [['key' => $field]],
                'purchase_schema' => PurchaseSchema::build(FazerCardsIds::TOPUP, [['key' => $field]], [
                    'validateCategoryId' => $category,
                ]),
            ],
        );

        return Sku::query()->updateOrCreate(
            ['code' => 'cp-'.$handle],
            [
                'product_id' => $product->id,
                'name' => '80 + 8 CP',
                'price' => 10000,
                'cost_usd' => '1.00',
                'fazercards_category_id' => $category,
                'fazercards_offer_id' => '88_cp',
                'fulfillment_type' => FazerCardsIds::TOPUP,
                'status' => 'active',
                'visible' => true,
                'synced_at' => now(),
            ],
        );
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
}

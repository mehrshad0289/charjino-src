<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\CatalogReclassifier;
use App\Modules\Catalog\FazerCardsCatalogSync;
use App\Modules\Supplier\FazerCards\FazerCardsSteamTopupQuotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogExclusionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);
        $this->fakeFazer();
    }

    public function test_login_credential_and_manual_are_excluded_from_storefront(): void
    {
        $this->app->make(FazerCardsCatalogSync::class)->sync();

        $login = Product::query()->where('fazercards_category_id', 'mlbb_via_login')->first();
        $this->assertNotNull($login);
        $this->assertSame('login_required', $login->excluded_reason);
        $this->assertFalse((bool) $login->visible);
        $this->getJson('/api/catalog/products/'.$login->handle)->assertNotFound();

        $cred = Product::query()->where('fazercards_category_id', 'valorant_special')->first();
        $this->assertNotNull($cred);
        $this->assertSame('credential_field', $cred->excluded_reason);
        $this->assertFalse((bool) $cred->visible);

        $manual = Product::query()->where('fazercards_category_id', 'manual:ms-boost')->first();
        $this->assertNotNull($manual);
        $this->assertSame('manual', $manual->excluded_reason);
        $this->assertFalse((bool) $manual->visible);
        $this->getJson('/api/catalog/products/'.$manual->handle)->assertNotFound();
    }

    public function test_giftcard_has_empty_fields_and_stock_qty(): void
    {
        $this->app->make(FazerCardsCatalogSync::class)->sync();
        $gift = Product::query()->where('fazercards_category_id', 'giftcard:steam')->firstOrFail();
        $this->assertSame([], $gift->inputs);
        $this->assertSame([], $gift->purchase_schema['fields'] ?? null);
        $this->assertTrue($gift->purchase_schema['quantity']['enabled']);
        $sku = Sku::query()->where('fazercards_offer_id', 'steam-10')->firstOrFail();
        $this->assertSame(4, (int) $sku->stock);
        $this->assertSame(4, (int) $sku->max_order_quantity);
        $this->assertNull(Sku::query()->where('fazercards_offer_id', 'steam-gone')->first());
        $detail = $this->getJson('/api/catalog/products/'.$gift->handle);
        $detail->assertOk();
        $this->assertSame([], $detail->json('data.inputFields'));
        $this->assertSame(4, $detail->json('data.skus.0.stock'));
    }

    public function test_steam_wallet_is_one_free_sku_and_topup_qty_is_one(): void
    {
        $this->app->make(FazerCardsCatalogSync::class)->sync();
        $free = Sku::query()->where('fazercards_offer_id', FazerCardsSteamTopupQuotes::FREE_OFFER_ID)->first();
        $this->assertNotNull($free);
        $this->assertSame('active', $free->status);
        $this->assertSame(1, (int) $free->max_order_quantity);
        $legacy = Sku::query()->where('fazercards_offer_id', 'RUB|1000')->first();
        if ($legacy) {
            $this->assertSame('hidden', $legacy->status);
        }
        $uc = Sku::query()->where('fazercards_offer_id', '60_uc')->where('fulfillment_type', 'topup')->firstOrFail();
        $this->assertSame(1, (int) $uc->max_order_quantity);
        $pubg = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $this->assertSame('player_id', $pubg->purchase_schema['fields'][0]['key'] ?? null);
        $this->assertSame('fazer', $pubg->purchase_schema['validation']['mode'] ?? null);
        $stars = Product::query()->where('fazercards_category_id', 'telegram-stars:stars')->firstOrFail();
        $this->assertSame('telegram_username', $stars->purchase_schema['fields'][0]['key'] ?? null);
    }

    public function test_gamekey_region_restriction_is_cached_on_product(): void
    {
        $this->app->make(FazerCardsCatalogSync::class)->sync();
        $key = Product::query()->where('fazercards_category_id', 'gamekey:elden')->firstOrFail();
        $this->assertTrue((bool) ($key->requirements['regionRestriction'] ?? false));
        $this->assertSame(['US', 'EU'], $key->requirements['regionAvailability']['available'] ?? null);
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/gamekeys/region-restriction'));
        $detail = $this->getJson('/api/catalog/products/'.$key->handle);
        $detail->assertOk();
        $this->assertSame(['US', 'EU'], $detail->json('data.regionAvailability.available'));
    }

    public function test_listed_shop_cap_records_incomplete_sync(): void
    {
        config(['charjino.supplier.fazercards_listed_shop_max' => 1]);
        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('topups');
        $incomplete = $result['syncIncomplete'] ?? [];
        $this->assertNotSame([], $incomplete);
        $this->assertStringContainsString('همگام‌سازی ناقص', (string) ($incomplete[0]['message'] ?? $result['message']));
    }

    public function test_reclassify_hides_existing_login_and_manual_and_clears_gift_inputs(): void
    {
        $group = \App\Models\CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'Game top-up', 'title' => 'شارژ بازی', 'visible' => true, 'sort_order' => 10],
        );
        $login = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'old-login',
            'code' => 'old-login',
            'title' => 'Old Via Login',
            'publisher' => '',
            'category_name' => 'شارژ بازی',
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fazercards_category_id' => 'old_via_login',
            'fulfillment_type' => 'topup',
            'visible' => true,
            'status' => 'active',
            'inputs' => ['playerId'],
        ]);
        $giftGroup = \App\Models\CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'Gift cards', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $gift = Product::query()->create([
            'catalog_group_id' => $giftGroup->id,
            'handle' => 'old-gift',
            'code' => 'old-gift',
            'title' => 'Old Gift',
            'publisher' => '',
            'category_name' => 'گیفت کارت',
            'subtitle' => '',
            'thumbnail' => '/image/catalog/steam.svg',
            'fazercards_category_id' => 'giftcard:old',
            'fulfillment_type' => 'giftcard',
            'visible' => true,
            'status' => 'active',
            'inputs' => ['playerId'],
        ]);
        $this->app->make(CatalogReclassifier::class)->run();
        $login->refresh();
        $gift->refresh();
        $this->assertSame('login_required', $login->excluded_reason);
        $this->assertFalse((bool) $login->visible);
        $this->assertSame([], $gift->inputs);
        $this->assertSame([], $gift->purchase_schema['fields'] ?? ['x']);
    }

    /**
     * @return array<string, mixed>
     */
    private function offersFor(string $categoryId): array
    {
        if ($categoryId === 'valorant_special') {
            return [
                'ok' => true,
                'category_id' => $categoryId,
                'name' => 'Valorant Special',
                'note' => 'Region: ID',
                'offers' => [['offer_id' => 'vp-1', 'name' => 'VP', 'price_usd' => '1']],
                'fields' => [
                    ['key' => 'riot_id', 'label' => 'Riot ID', 'type' => 'text'],
                    ['key' => 'password', 'label' => 'Password', 'type' => 'text'],
                ],
            ];
        }

        return [
            'ok' => true,
            'category_id' => $categoryId !== '' ? $categoryId : 'pubg_mobile_auto',
            'name' => $categoryId === 'mlbb_via_login' ? 'MLBB Via Login' : 'PUBG Mobile Auto',
            'note' => 'UC',
            'imageurl' => 'https://cdn.example/pubg.jpg',
            'offers' => [['offer_id' => '60_uc', 'name' => '60 UC', 'price_usd' => '0.84']],
            'fields' => [['key' => 'player_id', 'label' => 'Player ID', 'type' => 'text']],
        ];
    }

    private function fakeFazer(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', $path), '/');
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return match (true) {
                $path === 'me' => Http::response(['ok' => true, 'email' => 'ops@example.com', 'plan' => 'gold'], 200),
                $path === 'balance' => Http::response(['ok' => true, 'balance' => '12.0000'], 200),
                $path === 'topups' => Http::response([
                    'ok' => true,
                    'items' => [
                        ['category_id' => 'pubg_mobile_auto', 'name' => 'PUBG Mobile Auto', 'imageurl' => 'https://cdn.example/pubg.jpg'],
                        ['category_id' => 'mlbb_via_login', 'name' => 'MLBB Via Login'],
                        ['category_id' => 'valorant_special', 'name' => 'Valorant Special'],
                        ['category_id' => 'vpn_proxy', 'name' => 'VPN Proxy'],
                    ],
                    'meta' => ['total' => 4, 'has_more' => false],
                ], 200),
                $path === 'topups/offers' => Http::response($this->offersFor((string) ($query['category_id'] ?? '')), 200),
                $path === 'giftcards' => Http::response([
                    'ok' => true,
                    'items' => [[
                        'category_id' => 'steam',
                        'name' => 'Steam',
                        'imageurl' => 'https://cdn.example/steam-card.jpg',
                    ]],
                    'meta' => ['has_more' => false],
                ], 200),
                $path === 'giftcards/cards' => Http::response([
                    'ok' => true,
                    'offers' => [
                        ['card_id' => 'steam-10', 'name' => 'Steam 10', 'price_usd' => '10', 'stock' => 4],
                        ['card_id' => 'steam-gone', 'name' => 'Gone', 'price_usd' => '25', 'stock' => 0],
                    ],
                ], 200),
                $path === 'gamekeys' => Http::response([
                    'ok' => true,
                    'items' => [[
                        'game_id' => 'elden',
                        'name' => 'Elden Ring',
                        'region' => 'Global',
                        'platform' => 'Steam',
                        'region_restriction' => true,
                        'imageurl' => 'https://cdn.example/elden.jpg',
                    ]],
                    'meta' => ['has_more' => false],
                ], 200),
                $path === 'gamekeys/keys' => Http::response([
                    'ok' => true,
                    'GameName' => 'Elden Ring',
                    'region_restriction' => true,
                    'keys' => [
                        ['key_id' => 'er-std', 'name' => 'Standard', 'price_usd' => '40', 'stock' => 5, 'min_order_quantity' => 1, 'max_order_quantity' => 8],
                    ],
                ], 200),
                $path === 'gamekeys/region-restriction' => Http::response([
                    'ok' => true,
                    'game_id' => 'elden',
                    'region_type' => 'activation',
                    'available' => ['US', 'EU'],
                    'unavailable' => ['IR'],
                ], 200),
                $path === 'steam-topup/rates' => Http::response([
                    'ok' => true,
                    'rates' => ['USD' => 1, 'RUB' => 80, 'UAH' => 40, 'KZT' => 400],
                ], 200),
                $path === 'telegram/stars' => Http::response([
                    'ok' => true,
                    'price_per_star' => '0.016',
                    'min_amount' => 50,
                    'max_amount' => 10000,
                ], 200),
                $path === 'telegram/premium' => Http::response([
                    'ok' => true,
                    'plans' => [
                        ['months' => 3, 'price_usd' => '12'],
                        ['months' => 6, 'price_usd' => '16'],
                        ['months' => 12, 'price_usd' => '29'],
                    ],
                ], 200),
                $path === 'funpay/me' => Http::response(['ok' => true, 'addon' => ['active' => true]], 200),
                $path === 'steam-gifts/games' => Http::response(['ok' => true, 'games' => [], 'meta' => ['total' => 0]], 200),
                str_starts_with($path, 'manual-services/') && str_ends_with($path, '/offers') => Http::response([
                    'ok' => true,
                    'manual_service_id' => 'ms-boost',
                    'category' => ['id' => 'ms-boost', 'name' => 'Boost Service'],
                    'items' => [['id' => 'boost-1', 'name' => 'Boost', 'price_usd' => '5']],
                    'fields' => [['code' => 'player_id', 'name' => 'Player ID']],
                ], 200),
                $path === 'manual-services' => Http::response([
                    'ok' => true,
                    'items' => [['id' => 'ms-boost', 'name' => 'Boost Service']],
                ], 200),
                default => Http::response(['ok' => true, 'items' => [], 'meta' => ['has_more' => false]], 200),
            };
        });
    }
}

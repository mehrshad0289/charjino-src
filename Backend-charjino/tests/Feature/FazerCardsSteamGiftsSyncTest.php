<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\FazerCardsCatalogSync;
use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FazerCardsSteamGiftsSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);
    }

    public function test_http_tick_skips_ungiftable_app_and_ingests_next(): void
    {
        Http::fake(function (Request $request) {
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
                    'meta' => ['total' => 2, 'returned' => 2, 'truncated' => false],
                ], 200);
            }
            if ($path === 'steam-gifts/games/570') {
                return Http::response([
                    'ok' => false,
                    'error' => 'This game is not in the gift catalog.',
                    'code' => 'NOT_FOUND',
                ], 404);
            }
            if ($path === 'steam-gifts/games/730') {
                return Http::response([
                    'ok' => true,
                    'appid' => 730,
                    'offers' => [[
                        'sub_id' => 42,
                        'name' => 'CS2',
                        'regions' => [['region' => 'US', 'price' => '9.99']],
                    ]],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $sync = $this->app->make(FazerCardsCatalogSync::class);
        $first = $sync->tick(20_000, 5, 'steam-gifts');
        $this->assertSame('steam-gifts', $first['scope']);
        $this->assertSame(2, (int) $first['catalogTotal']);
        $this->assertSame(1, (int) $first['processedItems']);
        $cs2 = Product::query()->where('fazercards_category_id', 'steam-gift:730')->first();
        $this->assertNotNull($cs2);
        $this->assertNull(Product::query()->where('fazercards_category_id', 'steam-gift:570')->first());

        $second = $sync->tick(20_000, 5, 'steam-gifts');
        $this->assertGreaterThanOrEqual(2, (int) $second['processedItems']);
        $this->assertNull(Product::query()->where('fazercards_category_id', 'steam-gift:570')->first());
        $cs2 = Product::query()->where('fazercards_category_id', 'steam-gift:730')->first();
        $this->assertNotNull($cs2);
        $this->assertSame('https://cdn.cloudflare.steamstatic.com/steam/apps/730/header.jpg', $cs2->thumbnail);
        $this->assertNotNull(Sku::query()->where('fazercards_offer_id', '42|US')->first());
        Http::assertSent(function (Request $request) {
            $path = (string) parse_url((string) $request->url(), PHP_URL_PATH);

            return str_ends_with($path, '/steam-gifts/games') && ! str_contains((string) $request->url(), 'limit=');
        });
    }

    public function test_steam_gifts_sync_ingests_newest_not_entire_catalog(): void
    {
        config(['charjino.supplier.fazercards_steam_gifts_shop_max' => 1]);
        Http::fake(function (Request $request) {
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
                        ['name' => 'Old One', 'appid' => 10],
                        ['name' => 'Mid One', 'appid' => 20],
                        ['name' => 'New One', 'appid' => 30],
                    ],
                    'meta' => ['total' => 3, 'returned' => 3, 'truncated' => false],
                ], 200);
            }
            if (preg_match('#^steam-gifts/games/(\d+)$#', $path, $match) === 1) {
                return Http::response([
                    'ok' => true,
                    'appid' => (int) $match[1],
                    'offers' => [[
                        'sub_id' => 9,
                        'name' => 'Pack',
                        'regions' => [['region' => 'US', 'price' => '1.99']],
                    ]],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('steam-gifts');
        $this->assertSame('completed', $result['status']);
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'steam-gift:30')->first());
        $this->assertNull(Product::query()->where('fazercards_category_id', 'steam-gift:10')->first());
        $this->assertNull(Product::query()->where('fazercards_category_id', 'steam-gift:20')->first());
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/steam-gifts/games/30'));
        Http::assertNotSent(fn (Request $request) => str_contains((string) $request->url(), '/steam-gifts/games/10'));
        Http::assertNotSent(fn (Request $request) => str_contains((string) $request->url(), '/steam-gifts/order'));
    }

    public function test_steam_gifts_sync_keeps_newest_two_when_max_is_two(): void
    {
        config(['charjino.supplier.fazercards_steam_gifts_shop_max' => 2]);
        Http::fake(function (Request $request) {
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
                        ['name' => 'Old One', 'appid' => 10],
                        ['name' => 'Mid One', 'appid' => 20],
                        ['name' => 'New One', 'appid' => 30],
                    ],
                ], 200);
            }
            if (preg_match('#^steam-gifts/games/(\d+)$#', $path, $match) === 1) {
                return Http::response([
                    'ok' => true,
                    'appid' => (int) $match[1],
                    'offers' => [[
                        'sub_id' => 9,
                        'name' => 'Pack',
                        'regions' => [['region' => 'US', 'price' => '1.99']],
                    ]],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('steam-gifts');
        $this->assertSame('completed', $result['status']);
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'steam-gift:30')->first());
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'steam-gift:20')->first());
        $this->assertNull(Product::query()->where('fazercards_category_id', 'steam-gift:10')->first());
        Http::assertNotSent(fn (Request $request) => str_contains((string) $request->url(), '/steam-gifts/games/10'));
    }

    public function test_curate_hides_surplus_and_keeps_pinned(): void
    {
        config(['charjino.supplier.fazercards_steam_gifts_shop_max' => 1]);
        $this->steamGiftProduct(10, true, ['shopPinned' => true]);
        $this->steamGiftProduct(20, true);
        $this->steamGiftProduct(30, false);
        Http::fake(function (Request $request) {
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($request->url(), PHP_URL_PATH)), '/');
            if ($path === 'steam-gifts/games') {
                return Http::response([
                    'ok' => true,
                    'games' => [
                        ['name' => 'Old One', 'appid' => 10],
                        ['name' => 'Mid One', 'appid' => 20],
                        ['name' => 'New One', 'appid' => 30],
                    ],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $result = $this->app->make(FazerCardsSteamGiftShop::class)->curate();
        $this->assertGreaterThanOrEqual(1, $result['hidden']);
        $this->assertTrue((bool) Product::query()->where('fazercards_category_id', 'steam-gift:10')->value('visible'));
        $this->assertFalse((bool) Product::query()->where('fazercards_category_id', 'steam-gift:20')->value('visible'));
        $this->assertTrue((bool) Product::query()->where('fazercards_category_id', 'steam-gift:30')->value('visible'));
    }

    public function test_home_lists_newest_steam_gifts_first(): void
    {
        $group = CatalogGroup::query()->create([
            'name' => 'Steam gifts',
            'title' => 'گیفت استیم',
            'slug' => 'steam-gifts',
            'visible' => true,
            'sort_order' => 55,
        ]);
        foreach ([10, 30, 20] as $appid) {
            $product = $this->steamGiftProduct($appid, true);
            $product->catalog_group_id = $group->id;
            $product->save();
            Sku::query()->create([
                'product_id' => $product->id,
                'code' => 'steam-gift-'.$appid.'-us',
                'name' => 'US',
                'price' => 10000,
                'status' => 'active',
                'visible' => true,
            ]);
        }

        $home = $this->getJson('/api/catalog/home')->assertOk();
        $row = collect($home->json('data.groups'))->firstWhere('slug', 'steam-gifts');
        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('products', $row);
    }

    private function steamGiftProduct(int $appid, bool $visible, array $requirements = []): Product
    {
        $handle = 'steam-gift-'.$appid;

        return Product::query()->create([
            'handle' => $handle,
            'code' => $handle,
            'title' => 'Game '.$appid,
            'publisher' => 'Valve',
            'category_name' => 'گیفت استیم',
            'subtitle' => 'گیفت',
            'thumbnail' => '/image/catalog/steam.svg',
            'visible' => $visible,
            'fulfillment_type' => 'steam-gift',
            'fazercards_category_id' => 'steam-gift:'.$appid,
            'status' => 'active',
            'requirements' => $requirements !== [] ? $requirements : null,
        ]);
    }
}

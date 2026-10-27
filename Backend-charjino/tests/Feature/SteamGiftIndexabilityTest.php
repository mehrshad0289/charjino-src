<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Catalog\SteamGiftIndexability;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SteamGiftIndexabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_title_patterns_map_to_blocked_steam_types(): void
    {
        $svc = $this->app->make(SteamGiftIndexability::class);
        $this->assertSame('dlc', $svc->blockedTypeFromTitle('Witcher 3 — Hearts of Stone DLC'));
        $this->assertSame('soundtrack', $svc->blockedTypeFromTitle('Hades Original Soundtrack'));
        $this->assertSame('demo', $svc->blockedTypeFromTitle('Hollow Knight Demo'));
        $this->assertSame('tool', $svc->blockedTypeFromTitle('Dedicated Server'));
        $this->assertSame('video', $svc->blockedTypeFromTitle('Making of Trailer'));
        $this->assertNull($svc->blockedTypeFromTitle('Counter-Strike 2'));
        $this->assertSame('soundtrack', $svc->steamTypeToBlocked('music'));
        $this->assertSame('video', $svc->steamTypeToBlocked('episode'));
        $this->assertNull($svc->steamTypeToBlocked('game'));
    }

    public function test_refresh_keeps_popular_and_sold_games_and_blocks_dlc(): void
    {
        $group = $this->giftGroup();
        $cs2 = $this->gift($group, 'steam-gift-730', 'Counter-Strike 2', 730, false);
        $dlc = $this->gift($group, 'steam-gift-999001', 'Some Game DLC', 999001, false);
        $ost = $this->gift($group, 'steam-gift-999002', 'Some Game Soundtrack', 999002, false);
        $obscure = $this->gift($group, 'steam-gift-999003', 'Unknown Indie Jam', 999003, false);
        $sold = $this->gift($group, 'steam-gift-999004', 'Sold Niche Game', 999004, false);
        $this->paidOrder($sold);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, 'steamspy.com')) {
                return Http::response([
                    '730' => [
                        'appid' => 730,
                        'name' => 'Counter-Strike 2',
                        'positive' => 8000000,
                        'negative' => 100000,
                        'owners' => '50,000,000 .. 100,000,000',
                        'ccu' => 900000,
                    ],
                ], 200);
            }
            if (str_contains($url, 'featuredcategories')) {
                return Http::response(['top_sellers' => ['items' => [['id' => 730]]]], 200);
            }
            if (str_contains($url, 'GetMostPlayedGames')) {
                return Http::response(['response' => ['ranks' => [['appid' => 730]]]], 200);
            }
            if (str_contains($url, 'appdetails')) {
                $appId = (string) ($request['appids'] ?? '730');

                return Http::response([
                    $appId => ['success' => true, 'data' => ['type' => 'game', 'name' => 'Game']],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $result = $this->app->make(SteamGiftIndexability::class)->refresh();
        $this->assertGreaterThanOrEqual(2, $result['indexable']);
        $this->assertSame(2, $result['blocked']);
        $this->assertTrue($cs2->fresh()->indexable);
        $this->assertTrue($sold->fresh()->indexable);
        $this->assertFalse($dlc->fresh()->indexable);
        $this->assertFalse($ost->fresh()->indexable);
        $this->assertFalse($obscure->fresh()->indexable);

        $listed = $this->getJson('/api/catalog/products?category=steam-gifts&page=1&perPage=100&indexable=1');
        $listed->assertOk();
        $this->assertTrue((bool) $listed->json('data.items.0.indexable'));
        $handles = collect($listed->json('data.items'))->pluck('handle')->all();
        $this->assertContains('steam-gift-730', $handles);
        $this->assertContains('steam-gift-999004', $handles);
        $this->assertNotContains('steam-gift-999001', $handles);

        $all = $this->getJson('/api/catalog/products?category=steam-gifts&page=1&perPage=100');
        $allHandles = collect($all->json('data.items'))->pluck('handle')->all();
        $this->assertContains('steam-gift-999001', $allHandles);
        $this->getJson('/api/catalog/products/steam-gift-999001')->assertOk();
        $this->getJson('/api/catalog/products/steam-gift-730')
            ->assertOk()
            ->assertJsonPath('data.indexable', true);
        $this->getJson('/api/catalog/products/steam-gift-999001')
            ->assertOk()
            ->assertJsonPath('data.indexable', false);
    }

    public function test_free_steam_games_are_never_indexable_even_if_popular_or_sold(): void
    {
        $group = $this->giftGroup();
        $this->gift($group, 'steam-gift-3354750', 'skate.', 3354750, true);
        $soldFree = $this->gift($group, 'steam-gift-1085660', 'Destiny 2', 1085660, true);
        $this->paidOrder($soldFree);
        $paid = $this->gift($group, 'steam-gift-730', 'Counter-Strike 2', 730, true);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, 'steamspy.com')) {
                return Http::response([
                    '3354750' => [
                        'appid' => 3354750,
                        'name' => 'skate.',
                        'positive' => 900000,
                        'negative' => 10,
                        'owners' => '20,000,000 .. 50,000,000',
                        'price' => '0',
                    ],
                    '1085660' => [
                        'appid' => 1085660,
                        'name' => 'Destiny 2',
                        'positive' => 800000,
                        'negative' => 20,
                        'owners' => '20,000,000 .. 50,000,000',
                    ],
                    '730' => [
                        'appid' => 730,
                        'name' => 'Counter-Strike 2',
                        'positive' => 8000000,
                        'negative' => 100000,
                        'owners' => '50,000,000 .. 100,000,000',
                    ],
                ], 200);
            }
            if (str_contains($url, 'appdetails')) {
                $appId = (string) ($request['appids'] ?? '730');
                $free = in_array($appId, ['3354750', '1085660', '730'], true);

                return Http::response([
                    $appId => [
                        'success' => true,
                        'data' => [
                            'type' => 'game',
                            'name' => 'Game',
                            'is_free' => $free,
                        ],
                    ],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $result = $this->app->make(SteamGiftIndexability::class)->refresh();
        $this->assertGreaterThanOrEqual(1, $result['free']);
        $this->assertFalse(Product::query()->where('handle', 'steam-gift-3354750')->value('indexable'));
        $this->assertFalse(Product::query()->where('handle', 'steam-gift-1085660')->value('indexable'));
        $this->assertTrue((bool) Product::query()->where('handle', 'steam-gift-730')->value('indexable'));
        $this->assertTrue(SteamGiftIndexability::hasPaidCatalogItems(730));
        $this->assertSame('Prime', SteamGiftIndexability::paidItemLabel(730));
        $this->assertFalse(SteamGiftIndexability::hasPaidCatalogItems(3354750));
    }

    public function test_allowlisted_free_games_with_paid_items_stay_indexable(): void
    {
        $group = $this->giftGroup();
        $this->gift($group, 'steam-gift-730', 'Counter-Strike 2', 730, true);
        $this->gift($group, 'steam-gift-3354750', 'skate.', 3354750, true);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, 'steamspy.com')) {
                return Http::response([
                    '730' => [
                        'appid' => 730,
                        'name' => 'Counter-Strike 2',
                        'positive' => 8000000,
                        'negative' => 100000,
                        'owners' => '50,000,000 .. 100,000,000',
                        'price' => '0',
                    ],
                    '3354750' => [
                        'appid' => 3354750,
                        'name' => 'skate.',
                        'positive' => 900000,
                        'negative' => 10,
                        'owners' => '20,000,000 .. 50,000,000',
                        'price' => '0',
                    ],
                ], 200);
            }
            if (str_contains($url, 'appdetails')) {
                $appId = (string) ($request['appids'] ?? '730');

                return Http::response([
                    $appId => [
                        'success' => true,
                        'data' => [
                            'type' => 'game',
                            'name' => 'Game',
                            'is_free' => true,
                        ],
                    ],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $this->app->make(SteamGiftIndexability::class)->refresh();
        $this->assertTrue((bool) Product::query()->where('handle', 'steam-gift-730')->value('indexable'));
        $this->assertFalse(Product::query()->where('handle', 'steam-gift-3354750')->value('indexable'));
        $this->getJson('/api/catalog/products/steam-gift-730')
            ->assertOk()
            ->assertJsonPath('data.indexable', true);
    }

    public function test_steam_app_type_dlc_is_never_indexable_even_if_popular(): void
    {
        $group = $this->giftGroup();
        $this->gift($group, 'steam-gift-888001', 'Popular Addon', 888001, false);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, 'steamspy.com')) {
                return Http::response([
                    '888001' => [
                        'appid' => 888001,
                        'name' => 'Popular Addon',
                        'positive' => 900000,
                        'negative' => 10,
                        'owners' => '20,000,000 .. 50,000,000',
                    ],
                ], 200);
            }
            if (str_contains($url, 'appdetails')) {
                return Http::response([
                    '888001' => ['success' => true, 'data' => ['type' => 'dlc', 'name' => 'Popular Addon']],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $this->app->make(SteamGiftIndexability::class)->refresh();
        $this->assertFalse(Product::query()->where('handle', 'steam-gift-888001')->value('indexable'));
    }

    private function giftGroup(): CatalogGroup
    {
        return CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
    }

    private function gift(CatalogGroup $group, string $handle, string $title, int $appId, bool $indexable): Product
    {
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $title,
            'name_en' => $title,
            'publisher' => '',
            'category_name' => $group->title,
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'indexable' => $indexable,
            'status' => 'active',
            'fulfillment_type' => FazerCardsIds::STEAM_GIFT,
            'fazercards_category_id' => 'steam-gift:'.$appId,
            'requirements' => ['appid' => $appId],
            'inputs' => [],
            'sort_order' => 10,
        ]);
        Sku::query()->create([
            'product_id' => $product->id,
            'code' => $handle.'-1',
            'name' => '1',
            'price' => 10000,
            'cost_usd' => '1',
            'status' => 'active',
            'visible' => true,
            'fulfillment_type' => FazerCardsIds::STEAM_GIFT,
            'sort_order' => 10,
        ]);

        return $product;
    }

    private function paidOrder(Product $product): Order
    {
        $user = User::query()->create([
            'phone' => '09'.str_pad((string) random_int(100000000, 999999999), 9, '0'),
            'role' => 'user',
            'status' => 'active',
        ]);
        $sku = $product->skus()->firstOrFail();
        $order = new Order;
        $order->user_id = $user->id;
        $order->sku_id = $sku->id;
        $order->product_id = $product->id;
        $order->quantity = 1;
        $order->requirements = [];
        $order->amount_toman = 10000;
        $order->status = Order::STATUS_PAID;
        $order->paid_at = now();
        $order->fulfillment_type = FazerCardsIds::STEAM_GIFT;
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', (string) Str::uuid());
        $order->save();

        return $order;
    }
}

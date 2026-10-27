<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\CatalogGroup;
use App\Models\FazerCardsSyncCheckpoint;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\FazerCardsCatalogSync;
use App\Modules\Orders\OrderQuoteService;
use App\Modules\Supplier\FazerCards\FazerCardsHeavySyncLock;
use App\Modules\Supplier\FazerCards\FazerCardsListedShop;
use App\Modules\Supplier\FazerCards\FazerCardsPriceFreshness;
use App\Modules\Supplier\FazerCards\FazerCardsRateLimiter;
use App\Modules\Supplier\FazerCards\FazerCardsRequestLane;
use App\Modules\Supplier\FazerCards\FazerCardsSkuSyncFailure;
use App\Modules\Supplier\FazerCards\FazerCardsSyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FazerCardsSyncOpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);
    }

    public function test_fail_count_increments_once_and_zeros_after_success(): void
    {
        $product = $this->fazerProduct('fail-count');
        $sku = Sku::query()->create([
            'product_id' => $product->id,
            'code' => 'fail-count-1',
            'name' => 'Pack',
            'price' => 1000,
            'fazercards_category_id' => 'giftcard:fail-count',
            'fazercards_offer_id' => 'p1',
            'fulfillment_type' => 'giftcard',
            'status' => 'active',
            'visible' => true,
            'synced_at' => now()->subDays(2),
            'sync_failed_count' => 0,
        ]);
        $syncedAt = $sku->synced_at?->toIso8601String();
        FazerCardsSkuSyncFailure::markProduct((int) $product->id);
        $sku->refresh();
        $this->assertSame(1, (int) $sku->sync_failed_count);
        $this->assertNotNull($sku->last_sync_failed_at);
        $failedAt = $sku->last_sync_failed_at?->toIso8601String();
        $this->assertSame($syncedAt, $sku->synced_at?->toIso8601String());
        FazerCardsSkuSyncFailure::clearCount((int) $product->id);
        $sku->refresh();
        $this->assertSame(0, (int) $sku->sync_failed_count);
        $this->assertSame($failedAt, $sku->last_sync_failed_at?->toIso8601String());
    }

    public function test_heavy_lock_owner_cannot_be_released_or_extended_by_other_process(): void
    {
        $lock = FazerCardsHeavySyncLock::acquire();
        $this->assertNotNull($lock);
        $this->assertTrue($lock->stillOwned());
        $this->assertNull(FazerCardsHeavySyncLock::acquire());
        $other = Cache::lock(FazerCardsHeavySyncLock::NAME, 600);
        $this->assertFalse($other->get());
        $this->assertFalse($other->release());
        $this->assertFalse($other->refresh(600));
        $stolen = Cache::restoreLock(FazerCardsHeavySyncLock::NAME, 'not-the-owner');
        $this->assertFalse($stolen->release());
        $this->assertFalse($stolen->refresh(600));
        $this->assertTrue($lock->stillOwned());
        $lock->release();
        $again = FazerCardsHeavySyncLock::acquire();
        $this->assertNotNull($again);
        Cache::lock(FazerCardsHeavySyncLock::NAME, 1)->forceRelease();
        $this->assertFalse($again->stillOwned());
        $this->assertFalse($again->heartbeat());
        $again->release();
    }

    public function test_light_runs_while_heavy_status_is_running(): void
    {
        FazerCardsSyncStatus::putState([
            'status' => 'running',
            'scope' => 'steam-gifts',
            'phase' => 'steam-gifts',
        ]);
        Http::fake(fn () => Http::response(['ok' => true, 'items' => []], 200));
        $result = $this->app->make(FazerCardsCatalogSync::class)->lightRefresh();
        $this->assertNotSame('skipped', $result['status']);
        $this->assertSame('light', $result['scope']);
    }

    public function test_second_heavy_sync_is_rejected_while_lock_held(): void
    {
        $held = FazerCardsHeavySyncLock::acquire();
        $this->assertNotNull($held);
        $second = $this->app->make(FazerCardsCatalogSync::class)->tick(20_000, 1, 'giftcards');
        $this->assertSame('همگام در حال اجراست.', $second['message'] ?? null);
        $held->release();
    }

    public function test_rate_limiter_caps_heavy_and_keeps_order_priority(): void
    {
        config(['charjino.supplier.fazercards_rate_limiter_enforce' => true]);
        Cache::put('fazercards:rl:catalog_limit', 10, 120);
        Cache::put('fazercards:rl:catalog_remaining', 10, 120);
        $heavyOk = 0;
        $heavyBlocked = 0;
        for ($i = 0; $i < 6; $i++) {
            try {
                FazerCardsRequestLane::using(FazerCardsRequestLane::HEAVY, function () use (&$heavyOk): void {
                    FazerCardsRateLimiter::before(FazerCardsRequestLane::HEAVY);
                    $heavyOk++;
                });
            } catch (ApiException $e) {
                $this->assertSame('RATE_LIMIT_EXCEEDED', $e->errorCode);
                $heavyBlocked++;
            }
        }
        $this->assertSame(5, $heavyOk);
        $this->assertSame(1, $heavyBlocked);
        FazerCardsRequestLane::using(FazerCardsRequestLane::ORDER, function (): void {
            FazerCardsRateLimiter::before(FazerCardsRequestLane::ORDER);
        });
        $this->assertSame(5, FazerCardsRateLimiter::windowCount(FazerCardsRequestLane::HEAVY));
        $this->assertLessThanOrEqual(5, (int) floor(FazerCardsRateLimiter::catalogLimit() * 0.5));
    }

    public function test_stale_fazer_sku_is_unavailable_until_fresh_sync(): void
    {
        $product = $this->fazerProduct('stale-buy');
        $sku = Sku::query()->create([
            'product_id' => $product->id,
            'code' => 'stale-buy-1',
            'name' => 'Pack',
            'price' => 10000,
            'cost_usd' => '1.00',
            'live_cost_usd' => '1.00',
            'fazercards_category_id' => 'giftcard:stale-buy',
            'fazercards_offer_id' => 'p1',
            'fulfillment_type' => 'giftcard',
            'status' => 'active',
            'visible' => true,
            'stock' => 4,
            'synced_at' => now()->subDays(2),
        ]);
        $this->assertTrue(FazerCardsPriceFreshness::blocksSale($sku));
        try {
            $this->app->make(OrderQuoteService::class)->quote($sku, 1);
            $this->fail('stale sku quoted');
        } catch (ApiException $e) {
            $this->assertSame('SKU_UNAVAILABLE', $e->errorCode);
        }
        $sku->refresh();
        $this->assertTrue((bool) $sku->visible);
        $this->assertSame('active', $sku->status);
        $sku->synced_at = now();
        $sku->save();
        $quote = $this->app->make(OrderQuoteService::class)->quote($sku->fresh(), 1);
        $this->assertSame('1.00', $quote['unitCostUsd']);
        $own = Product::query()->whereNull('fazercards_category_id')->first();
        $this->assertNotNull($own);
        $ownSku = Sku::query()->where('product_id', $own->id)->first();
        $this->assertNotNull($ownSku);
        $this->assertFalse(FazerCardsPriceFreshness::blocksSale($ownSku));
    }

    public function test_visible_steam_mode_details_only_visible_products(): void
    {
        config(['charjino.supplier.fazercards_steam_gifts_shop_max' => 3]);
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 55, 'image_url' => '/image/catalog/steam.svg'],
        );
        Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'steam-gift-730',
            'code' => 'steam-gift-730',
            'title' => 'CS2',
            'publisher' => 'Valve',
            'category_name' => 'گیفت استیم',
            'subtitle' => 'گیفت',
            'fazercards_category_id' => 'steam-gift:730',
            'fulfillment_type' => 'steam-gift',
            'visible' => true,
            'status' => 'active',
            'thumbnail' => '/image/catalog/steam.svg',
        ]);
        Http::fake(function (Request $request) {
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($request->url(), PHP_URL_PATH)), '/');
            if ($path === 'me') {
                return Http::response(['ok' => true, 'email' => 'ops@example.com'], 200);
            }
            if ($path === 'steam-gifts/games') {
                return Http::response([
                    'ok' => true,
                    'games' => [
                        ['name' => 'Hidden New', 'appid' => 10],
                        ['name' => 'CS2', 'appid' => 730],
                    ],
                    'meta' => ['total' => 2, 'returned' => 2, 'truncated' => false],
                ], 200);
            }
            if (preg_match('#^steam-gifts/games/(\d+)$#', $path, $match) === 1) {
                return Http::response([
                    'ok' => true,
                    'appid' => (int) $match[1],
                    'name' => 'Game '.$match[1],
                    'offers' => [[
                        'sub_id' => 9,
                        'name' => 'Pack',
                        'regions' => [['region' => 'US', 'price' => '1.99']],
                    ]],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('steam-gifts', 'visible');
        $this->assertSame('completed', $result['status']);
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/steam-gifts/games/730'));
        Http::assertNotSent(fn (Request $request) => str_contains((string) $request->url(), '/steam-gifts/games/10'));
    }

    public function test_weekly_stops_at_8am_and_resumes_without_repeat(): void
    {
        config(['charjino.supplier.fazercards_steam_gifts_shop_max' => 2]);
        Http::fake(function (Request $request) {
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($request->url(), PHP_URL_PATH)), '/');
            if ($path === 'me') {
                return Http::response(['ok' => true, 'email' => 'ops@example.com'], 200);
            }
            if ($path === 'steam-gifts/games') {
                return Http::response([
                    'ok' => true,
                    'games' => [
                        ['name' => 'Old', 'appid' => 10],
                        ['name' => 'New', 'appid' => 20],
                    ],
                    'meta' => ['total' => 2, 'returned' => 2, 'truncated' => false],
                ], 200);
            }
            if (preg_match('#^steam-gifts/games/(\d+)$#', $path, $match) === 1) {
                return Http::response([
                    'ok' => true,
                    'appid' => (int) $match[1],
                    'offers' => [[
                        'sub_id' => 1,
                        'name' => 'Pack',
                        'regions' => [['region' => 'US', 'price' => '2.00']],
                    ]],
                ], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        Carbon::setTestNow(Carbon::parse('2026-10-22 01:00:00', 'Asia/Tehran'));
        $sync = $this->app->make(FazerCardsCatalogSync::class);
        $first = $sync->tick(20_000, 1, 'steam-gifts', false, 'weekly');
        $this->assertSame('running', $first['status']);
        $this->assertSame(1, (int) $first['processedItems']);
        $this->assertTrue(FazerCardsSyncCheckpoint::weeklyRunning());
        $afterFirst = (int) FazerCardsSyncCheckpoint::weekly()?->last_index;

        Carbon::setTestNow(Carbon::parse('2026-10-22 08:00:00', 'Asia/Tehran'));
        $paused = $sync->tick(20_000, 5, 'steam-gifts', true, 'weekly');
        $this->assertStringContainsString('۰۸:۰۰', (string) ($paused['message'] ?? ''));
        $this->assertSame($afterFirst, (int) FazerCardsSyncCheckpoint::weekly()?->last_index);
        $this->assertSame(1, Product::query()->where('fulfillment_type', 'steam-gift')->count());

        Carbon::setTestNow(Carbon::parse('2026-10-23 01:00:00', 'Asia/Tehran'));
        $resumed = $this->app->make(FazerCardsCatalogSync::class)->sync('steam-gifts', 'weekly');
        $this->assertSame('completed', $resumed['status']);
        $this->assertSame(2, Product::query()->where('fulfillment_type', 'steam-gift')->count());
        $this->assertFalse(FazerCardsSyncCheckpoint::weeklyRunning());
        Carbon::setTestNow();
    }

    public function test_pagination_compare_identical_sets(): void
    {
        Http::fake(function (Request $request) {
            $url = (string) $request->url();
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($url, PHP_URL_PATH)), '/');
            if ($path !== 'topups') {
                return Http::response(['ok' => true], 200);
            }
            $items = [
                ['category_id' => 'a', 'name' => 'A'],
                ['category_id' => 'b', 'name' => 'B'],
            ];
            if (! isset($query['limit'])) {
                return Http::response(['ok' => true, 'items' => $items, 'meta' => ['total' => 2, 'has_more' => false]], 200);
            }

            return Http::response(['ok' => true, 'items' => $items, 'meta' => ['total' => 2, 'has_more' => false, 'next_cursor' => '']], 200);
        });
        $spec = $this->app->make(FazerCardsCatalogSync::class)->familySpecFor('topups');
        $this->assertIsArray($spec);
        $cmp = $this->app->make(FazerCardsListedShop::class)->comparePagination($spec, 500);
        $this->assertTrue($cmp['identical']);
        $this->assertSame(2, $cmp['omitUnique']);
        $this->assertSame(2, $cmp['pagedUnique']);
    }

    public function test_forget_run_does_not_clear_another_owner(): void
    {
        FazerCardsSyncStatus::putState([
            'status' => 'running',
            'scope' => 'steam-gifts',
            'lockOwner' => 'other-owner-token',
        ]);
        $held = FazerCardsHeavySyncLock::acquire();
        $this->assertNotNull($held);
        $sync = $this->app->make(FazerCardsCatalogSync::class);
        $method = new \ReflectionMethod($sync, 'forgetRunIfOwned');
        $method->setAccessible(true);
        $lockProp = new \ReflectionProperty($sync, 'heavyLock');
        $lockProp->setAccessible(true);
        $lockProp->setValue($sync, $held);
        $method->invoke($sync);
        $state = FazerCardsSyncStatus::state();
        $this->assertSame('running', $state['status'] ?? null);
        $this->assertSame('other-owner-token', $state['lockOwner'] ?? null);
        $held->release();
    }

    private function fazerProduct(string $remote): Product
    {
        $group = CatalogGroup::query()->firstOrFail();

        return Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $remote,
            'code' => $remote,
            'title' => $remote,
            'publisher' => 'Test',
            'category_name' => 'گیفت کارت',
            'subtitle' => 't',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fazercards_category_id' => 'giftcard:'.$remote,
            'fulfillment_type' => 'giftcard',
            'visible' => true,
            'status' => 'active',
        ]);
    }
}

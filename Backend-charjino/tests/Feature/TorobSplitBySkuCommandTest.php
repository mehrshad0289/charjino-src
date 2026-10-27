<?php

namespace Tests\Feature;

use App\Http\Controllers\Torob\TorobProductController;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobSplitRuntimeStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorobSplitBySkuCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'torob.shop_base_url' => 'https://charjino.ir',
            'torob.split_by_sku_product_ids' => [],
            'torob.split_by_sku.enabled_product_ids' => [],
            'torob.split_by_sku.enabled_group_slugs' => [],
            'torob.split_by_sku.exclude_product_ids' => [],
            'torob.split_by_sku.max_items_per_product' => 30,
            'torob.split_by_sku.max_feed_multiplier' => 100,
            'torob.split_by_sku.canonical_path' => storage_path('framework/testing/torob-canonical-cmd.json'),
            'torob.split_by_sku.runtime_path' => storage_path('framework/testing/torob-runtime-cmd.json'),
            'torob.split_by_sku.baseline_path' => storage_path('framework/testing/torob-baseline-cmd.json'),
        ]);
        @unlink(storage_path('framework/testing/torob-canonical-cmd.json'));
        @unlink(storage_path('framework/testing/torob-runtime-cmd.json'));
        @unlink(storage_path('framework/testing/torob-baseline-cmd.json'));
        foreach (glob(storage_path('app/torob-verify/*.json')) ?: [] as $file) {
            @unlink($file);
        }
    }

    public function test_status_and_enable_dry_run_do_not_write_runtime(): void
    {
        $product = $this->gift('dry-run-card', [10, 25]);
        $this->artisan('torob:split-by-sku', ['action' => 'status'])
            ->expectsOutputToContain('enabled_product_ids=(none)')
            ->assertSuccessful();
        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--ids' => (string) $product->id,
            '--dry-run' => true,
        ])->expectsOutputToContain('dry-run')->assertSuccessful();
        $this->assertFileDoesNotExist(storage_path('framework/testing/torob-runtime-cmd.json'));
    }

    public function test_enable_dry_run_skips_enabled_and_prefers_priority_brands(): void
    {
        $this->gift('giftcard-obscure-usd', [400, 500]);
        $play = $this->gift('giftcard-google-play-us', [10, 15, 25]);
        $already = $this->gift('giftcard-rewarble-usd', [5, 10]);
        config([
            'torob.split_by_sku.enabled_product_ids' => [$already->id],
            'torob.split_by_sku_product_ids' => [$already->id],
        ]);

        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--group' => 'gift-card',
            '--limit' => 1,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('giftcard-google-play-us')
            ->doesntExpectOutputToContain('giftcard-obscure-usd')
            ->doesntExpectOutputToContain('giftcard-rewarble-usd')
            ->assertSuccessful();
        $this->assertFileDoesNotExist(storage_path('framework/testing/torob-runtime-cmd.json'));
    }

    public function test_enable_skips_xbox_game_pass(): void
    {
        $this->gift('giftcard-xbox-game-pass-us', [200, 500]);
        $card = $this->gift('giftcard-xbox-us', [15, 50]);
        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--group' => 'gift-card',
            '--limit' => 1,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('giftcard-xbox-us')
            ->doesntExpectOutputToContain('giftcard-xbox-game-pass-us')
            ->assertSuccessful();
        $this->assertNotNull($card->id);
    }

    public function test_enable_refuses_steam_gifts(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'گیفت استیم', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 5],
        );
        $this->makeProduct('steam-gift-730', [
            ['name' => 'Standard', 'price' => 120000, 'sort_order' => 1],
            ['name' => 'Deluxe', 'price' => 220000, 'sort_order' => 2],
        ], [
            'catalog_group_id' => $group->id,
            'title' => 'Counter-Strike 2',
        ]);
        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--group' => 'steam-gifts',
            '--limit' => 10,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('هرگز فعال نمی‌شود')
            ->assertFailed();
        $this->assertFileDoesNotExist(storage_path('framework/testing/torob-runtime-cmd.json'));
    }

    public function test_enable_refuses_manual_services(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'manual-services'],
            ['name' => 'سرویس دستی', 'title' => 'سرویس دستی', 'visible' => true, 'sort_order' => 60],
        );
        $product = $this->makeProduct('manual-service-demo', [
            ['name' => '1 ماه', 'price' => 120000, 'sort_order' => 1],
            ['name' => '3 ماه', 'price' => 220000, 'sort_order' => 2],
        ], [
            'catalog_group_id' => $group->id,
            'title' => 'سرویس دستی',
        ]);
        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--ids' => (string) $product->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('blocked_group:manual-services')
            ->expectsOutputToContain('dry-run')
            ->assertSuccessful();
        $this->assertFileDoesNotExist(storage_path('framework/testing/torob-runtime-cmd.json'));
    }

    public function test_real_enable_requires_file_or_ids(): void
    {
        $this->gift('giftcard-google-play-us', [10, 25]);
        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--group' => 'gift-card',
            '--limit' => 1,
        ])
            ->expectsOutputToContain('--file')
            ->assertFailed();
        $this->assertFileDoesNotExist(storage_path('framework/testing/torob-runtime-cmd.json'));
    }

    public function test_enable_from_rollout_file_keeps_file_order(): void
    {
        $second = $this->gift('giftcard-xbox-us', [15, 25]);
        $first = $this->gift('giftcard-google-play-us', [10, 20]);
        $dir = storage_path('app/torob/rollout');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir.DIRECTORY_SEPARATOR.'stage-test.json';
        file_put_contents($file, json_encode(['product_ids' => [$first->id, $second->id]], JSON_THROW_ON_ERROR));
        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--file' => $file,
        ])->assertSuccessful();
        $runtime = $this->app->make(TorobSplitRuntimeStore::class);
        $this->assertContains($first->id, $runtime->productIds());
        $this->assertContains($second->id, $runtime->productIds());
        @unlink($file);
    }

    public function test_force_does_not_bypass_over_max_or_unknown_mapping(): void
    {
        $this->app['env'] = 'production';
        $overSkus = [];
        for ($i = 1; $i <= 31; $i++) {
            $overSkus[] = ['name' => $i.' USD', 'price' => $i * 10000, 'sort_order' => $i];
        }
        $giftGroup = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $over = $this->makeProduct('giftcard-google-play-us', $overSkus, [
            'catalog_group_id' => $giftGroup->id,
            'title' => 'گیفت کارت گوگل پلی آمریکا',
        ]);
        $unknown = $this->makeProduct('nonesuch-wallet-zz', [
            ['name' => '10 USD', 'price' => 100000, 'sort_order' => 1],
            ['name' => '25 USD', 'price' => 200000, 'sort_order' => 2],
        ], [
            'catalog_group_id' => $giftGroup->id,
            'title' => 'گیفت کارت Unknown آمریکا',
        ]);

        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--ids' => $over->id.','.$unknown->id,
        ])
            ->expectsOutputToContain('--force')
            ->assertFailed();

        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--ids' => $over->id.','.$unknown->id,
            '--force' => true,
        ])
            ->expectsOutputToContain('over_max')
            ->expectsOutputToContain('unknown_mapping')
            ->assertSuccessful();

        $runtime = $this->app->make(TorobSplitRuntimeStore::class);
        $this->assertNotContains($over->id, $runtime->productIds());
        $this->assertNotContains($unknown->id, $runtime->productIds());
        $this->assertCount(1, $this->app->make(TorobProductMapper::class)->toRows($over->fresh(['skus', 'catalogGroup'])));
        $this->assertCount(1, $this->app->make(TorobProductMapper::class)->toRows($unknown->fresh(['skus', 'catalogGroup'])));
    }

    public function test_enable_then_disable_is_reversible(): void
    {
        $product = $this->gift('giftcard-google-play-us', [10, 25]);
        $mapper = $this->app->make(TorobProductMapper::class);
        $canonical = $this->app->make(\App\Services\Torob\TorobSplitCanonicalStore::class);
        $query = $this->app->make(\App\Services\Torob\TorobProductQueryService::class);
        $fresh = $product->fresh(['skus', 'catalogGroup']);
        $beforeRows = $mapper->toRows($fresh);
        $beforeUniques = array_column($beforeRows, 'page_unique');
        $beforeCanonical = $canonical->all();
        $beforeTotal = $query->listableTotal();
        $this->assertCount(1, $beforeRows);
        $this->assertSame([(string) $product->id], $beforeUniques);

        $this->artisan('torob:split-by-sku', [
            'action' => 'enable',
            '--ids' => (string) $product->id,
        ])->assertSuccessful();

        $runtime = $this->app->make(TorobSplitRuntimeStore::class);
        $this->assertContains($product->id, $runtime->productIds());
        $enabled = $product->fresh(['skus', 'catalogGroup']);
        $this->assertTrue($mapper->splitsBySku($enabled));
        $this->assertNotNull($canonical->get($product->id));
        $this->assertCount(2, $mapper->toRows($enabled));
        $this->assertContains((string) $product->id, array_column($mapper->toRows($enabled), 'page_unique'));

        $this->artisan('torob:split-by-sku', [
            'action' => 'disable',
            '--ids' => (string) $product->id,
        ])->assertSuccessful();
        $this->assertNotContains($product->id, $this->app->make(TorobSplitRuntimeStore::class)->productIds());
        $after = $product->fresh(['skus', 'catalogGroup']);
        $afterRows = $mapper->toRows($after);
        $this->assertSame($beforeUniques, array_column($afterRows, 'page_unique'));
        $this->assertCount(1, $afterRows);
        $this->assertNull($canonical->get($product->id));
        $this->assertSame($beforeCanonical, $canonical->all());
        $this->assertSame($beforeTotal, $query->listableTotal());
        $this->assertFalse($mapper->splitsBySku($after));
    }

    public function test_report_simulate_all_counts_multi_sku(): void
    {
        $this->gift('report-a', [10, 25]);
        $this->makePlain('report-plain');
        $this->artisan('torob:split-by-sku', [
            'action' => 'report',
            '--simulate-all' => true,
        ])->expectsOutputToContain('multi_sku_products=1')->assertSuccessful();
    }

    public function test_compare_prices_matches_catalog(): void
    {
        $product = $this->gift('giftcard-google-play-us', [10, 25]);
        config([
            'torob.split_by_sku.enabled_product_ids' => [$product->id],
            'torob.split_by_sku_product_ids' => [$product->id],
        ]);
        $this->artisan('torob:split-by-sku', [
            'action' => 'compare-prices',
            '--handle' => 'giftcard-google-play-us',
        ])->expectsOutputToContain('mismatch=0')->assertSuccessful();
    }

    public function test_report_prints_twenty_exemption_samples(): void
    {
        $over = [];
        for ($i = 1; $i <= 31; $i++) {
            $over[] = ['name' => $i.' USD', 'price' => $i * 10000, 'sort_order' => $i];
        }
        $this->makeProduct('over-max-card', $over, [
            'title' => 'گیفت کارت Over دلار آمریکا (USD)',
        ]);
        $this->makeProduct('giftcard-google-play-us', [
            ['name' => '10 USD', 'price' => 100000, 'sort_order' => 1],
            ['name' => '10 USD', 'price' => 110000, 'sort_order' => 2],
        ], ['title' => 'گیفت کارت Dup دلار آمریکا (USD)']);

        $this->artisan('torob:split-by-sku', [
            'action' => 'report',
            '--simulate-all' => true,
        ])
            ->expectsOutputToContain('exemption_over_max=1')
            ->expectsOutputToContain('sample_over_max_handle=over-max-card')
            ->expectsOutputToContain('sample_over_max_detail=sku_count=31 orderable=31 exceeds max_items_per_product=30')
            ->expectsOutputToContain('exemption_ambiguous_titles=1')
            ->expectsOutputToContain('sample_ambiguous_titles_handle=giftcard-google-play-us')
            ->assertSuccessful();
    }

    public function test_verify_feed_passes_for_split_and_telegram_group(): void
    {
        config(['torob.page_size' => 5]);
        $telegram = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'telegram'],
            ['name' => 'تلگرام', 'title' => 'تلگرام', 'visible' => true, 'sort_order' => 3],
        );
        $plain = $this->makeProduct('tg-plain', [['name' => 'بسته', 'price' => 12000]], [
            'catalog_group_id' => $telegram->id,
            'title' => 'ستاره تلگرام',
        ]);
        $skus = [];
        foreach ([5, 10, 15, 20, 25, 30, 40, 50, 100, 250] as $index => $amount) {
            $skus[] = [
                'name' => $amount.' USD',
                'price' => $amount * 10000,
                'sort_order' => $index + 1,
                'fazercards_offer_id' => $amount === 5 ? '5_usd' : $amount.'_usd',
            ];
        }
        $split = $this->makeProduct('giftcard-rewarble-usd', $skus, [
            'catalog_group_id' => $telegram->id,
            'title' => 'گیفت کارت Verify دلار آمریکا (USD)',
        ]);
        config([
            'torob.split_by_sku.enabled_product_ids' => [$split->id],
            'torob.split_by_sku_product_ids' => [$split->id],
        ]);
        $this->artisan('torob:split-by-sku', [
            'action' => 'freeze-canonical',
            '--ids' => (string) $split->id,
        ])->assertSuccessful();

        $this->artisan('torob:verify-feed', [
            '--ids' => (string) $split->id,
            '--group' => 'telegram',
            '--sample' => '3',
        ])
            ->expectsOutputToContain('pass=yes')
            ->expectsOutputToContain('rows=10')
            ->assertSuccessful();

        $files = glob(storage_path('app/torob-verify/*.json')) ?: [];
        $this->assertNotEmpty($files);
        sort($files);
        $report = json_decode((string) file_get_contents((string) end($files)), true);
        $this->assertTrue($report['pass'] ?? false);
        $this->assertSame(10, $report['row_count'] ?? null);
        $this->assertContains((string) $split->id, array_column($report['rows'] ?? [], 'page_unique'));
        $this->assertNotContains((string) $plain->id, array_column($report['rows'] ?? [], 'page_unique'));
        $pagination = collect($report['checks'] ?? [])->firstWhere('name', 'pagination');
        $this->assertTrue($pagination['pass'] ?? false);
        $this->assertSame(11, $pagination['detail']['total'] ?? null);
        $this->assertSame(11, $pagination['detail']['sum'] ?? null);
        $this->assertSame(3, $pagination['detail']['max_pages'] ?? null);
    }

    public function test_cache_key_includes_split_rule_fingerprint(): void
    {
        $controller = $this->app->make(TorobProductController::class);
        $before = $controller->responseCacheKey(['page' => 1, 'sort' => 'date_added_desc']);
        config(['torob.split_by_sku.enabled_product_ids' => [887]]);
        $after = $this->app->make(TorobProductController::class)->responseCacheKey(['page' => 1, 'sort' => 'date_added_desc']);
        $this->assertStringStartsWith('torob:v3:resp:', $before);
        $this->assertNotSame($before, $after);
    }

    /**
     * @param  list<int>  $amounts
     */
    private function gift(string $handle, array $amounts): Product
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $skus = [];
        foreach ($amounts as $index => $amount) {
            $skus[] = [
                'name' => $amount.' USD',
                'price' => $amount * 10000,
                'sort_order' => $index + 1,
            ];
        }

        return $this->makeProduct($handle, $skus, [
            'catalog_group_id' => $group->id,
            'title' => 'گیفت کارت Command دلار آمریکا (USD)',
        ]);
    }

    private function makePlain(string $handle): Product
    {
        return $this->makeProduct($handle, [['name' => 'بسته', 'price' => 11000]]);
    }

    /**
     * @param  list<array<string, mixed>>  $skus
     * @param  array<string, mixed>  $product
     */
    private function makeProduct(string $handle, array $skus, array $product = []): Product
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'torob-cmd-group'],
            ['name' => 'شارژینو', 'title' => 'شارژینو', 'visible' => true, 'sort_order' => 1],
        );
        $created = Product::query()->create(array_merge([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $handle,
            'publisher' => '',
            'subtitle' => '',
            'thumbnail' => '',
            'category_name' => $group->name,
            'visible' => true,
            'status' => 'active',
            'indexable' => true,
            'sort_order' => 10,
            'inputs' => [],
        ], $product));
        foreach ($skus as $index => $sku) {
            Sku::query()->create(array_merge([
                'product_id' => $created->id,
                'code' => $handle.'-'.$index,
                'name' => 'بسته '.$index,
                'price' => 10000,
                'status' => 'active',
                'visible' => true,
                'sort_order' => $index + 1,
            ], $sku));
        }

        return $created->fresh(['skus', 'catalogGroup']);
    }
}

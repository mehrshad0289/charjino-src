<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobProductQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TorobProductQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private TorobProductQueryService $query;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'torob.shop_base_url' => 'https://charjino.ir',
            'torob.page_size' => 100,
            'torob.excluded_product_ids' => [],
            'torob.split_by_sku_product_ids' => [],
            'torob.split_by_sku.enabled_product_ids' => [],
            'torob.split_by_sku.enabled_group_slugs' => [],
            'torob.split_by_sku.exclude_product_ids' => [],
            'torob.split_by_sku.max_items_per_product' => 30,
            'torob.split_by_sku.canonical_path' => storage_path('framework/testing/torob-canonical-query.json'),
            'torob.split_by_sku.runtime_path' => storage_path('framework/testing/torob-runtime-query.json'),
        ]);
        @unlink(storage_path('framework/testing/torob-canonical-query.json'));
        @unlink(storage_path('framework/testing/torob-runtime-query.json'));
        $this->query = $this->app->make(TorobProductQueryService::class);
    }

    public function test_split_extra_rows_are_included_in_total(): void
    {
        $product = $this->makeProduct('giftcard-google-play-us', [
            ['name' => '10 USD', 'price' => 20000],
            ['name' => '20 USD', 'price' => 30000],
        ], ['title' => 'گیفت کارت Query دلار آمریکا (USD)']);
        config([
            'torob.split_by_sku.enabled_product_ids' => [$product->id],
            'torob.split_by_sku_product_ids' => [$product->id],
        ]);

        $payload = $this->query->paginated(1, 'date_added_desc');

        $this->assertSame(2, $payload['total']);
        $this->assertCount(2, $payload['products']);
        $this->assertSame(2, collect($payload['products'])->pluck('page_unique')->unique()->count());
    }

    public function test_one_row_per_product_not_per_sku(): void
    {
        $this->makeProduct('one-row', [
            ['price' => 20000],
            ['price' => 30000],
        ]);

        $payload = $this->query->paginated(1, 'date_added_desc');

        $this->assertSame(1, $payload['total']);
        $this->assertCount(1, $payload['products']);
        $this->assertSame(20000, $payload['products'][0]['current_price']);
    }

    public function test_excluded_product_ids_are_removed(): void
    {
        $kept = $this->makeProduct('kept-product', [['price' => 11000]]);
        $drop = $this->makeProduct('drop-product', [['price' => 12000]]);
        config(['torob.excluded_product_ids' => [$drop->id]]);
        $this->query = $this->app->make(TorobProductQueryService::class);

        $payload = $this->query->paginated(1, 'date_added_desc');
        $ids = collect($payload['products'])->pluck('page_unique')->all();

        $this->assertContains((string) $kept->id, $ids);
        $this->assertNotContains((string) $drop->id, $ids);
    }

    public function test_cursor_uses_id_lt_not_offset(): void
    {
        $first = $this->makeProduct('cursor-a', [['price' => 11000]]);
        $second = $this->makeProduct('cursor-b', [['price' => 12000]]);
        $high = $first->id > $second->id ? $first : $second;
        $low = $high->id === $first->id ? $second : $first;

        $sql = $this->query->explainSqls($high->id)['cursor_product_id_desc'];
        $this->assertStringContainsString(' < ', $sql);
        $this->assertStringNotContainsString('offset', mb_strtolower($sql));

        $payload = $this->query->cursor($high->id);
        $this->assertCount(1, $payload['products']);
        $this->assertSame((string) $low->id, $payload['products'][0]['page_unique']);
    }

    public function test_fetch_by_page_url(): void
    {
        $product = $this->makeProduct('by-url-product', [['price' => 44000]]);
        $url = 'https://charjino.ir/catalog/'.$product->handle;

        $payload = $this->query->byUrls([$url]);

        $this->assertSame(1, $payload['total']);
        $this->assertSame((string) $product->id, $payload['products'][0]['page_unique']);
    }

    public function test_by_urls_accepts_uppercase_other_host_and_trailing_slash(): void
    {
        $product = $this->makeProduct('by-url-product', [['price' => 44000]]);
        $mapper = $this->app->make(TorobProductMapper::class);

        $this->assertSame(
            'by-url-product',
            $mapper->parseProductUrl('https://www.charjino.ir/catalog/by-url-product/')['handle'] ?? null,
        );
        $this->assertSame(
            'BY-URL-PRODUCT',
            $mapper->parseProductUrl('https://shop.example/catalog/BY-URL-PRODUCT/')['handle'] ?? null,
        );

        $payload = $this->query->byUrls([
            'https://www.charjino.ir/catalog/BY-URL-PRODUCT/',
            'http://localhost:3001/catalog/by-url-product?utm=torob',
        ]);

        $this->assertSame(1, $payload['total']);
        $this->assertSame((string) $product->id, $payload['products'][0]['page_unique']);
        $sql = $this->query->explainSqls(null, [1], ['https://charjino.ir/catalog/BY-URL-PRODUCT'])['by_urls'];
        $this->assertStringContainsString('handle', mb_strtolower($sql));
        $this->assertStringNotContainsString('lower(', mb_strtolower($sql));
    }

    public function test_one_hundred_urls_issue_a_single_products_query(): void
    {
        $product = $this->makeProduct('bulk-url-product', [['price' => 55000]]);
        $urls = [];
        for ($i = 0; $i < 100; $i++) {
            $host = $i % 2 === 0 ? 'https://charjino.ir' : 'https://www.charjino.ir';
            $slash = $i % 3 === 0 ? '/' : '';
            $handle = $i % 5 === 0 ? 'BULK-URL-PRODUCT' : 'bulk-url-product';
            $urls[] = $host.'/catalog/'.$handle.$slash;
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $payload = $this->query->byUrls($urls);
        $sqls = collect(DB::getQueryLog())->pluck('query')->all();
        DB::disableQueryLog();

        $productQueries = collect($sqls)->filter(
            fn (string $sql) => (bool) preg_match('/from ["`]products["`]/i', $sql),
        );
        $this->assertSame(1, $payload['total']);
        $this->assertSame((string) $product->id, $payload['products'][0]['page_unique']);
        $this->assertCount(1, $productQueries, implode("\n", $sqls));
        $this->assertCount(100, $urls);
    }

    public function test_row_pages_can_cut_inside_a_split_product(): void
    {
        config(['torob.page_size' => 5]);
        $this->query = $this->app->make(TorobProductQueryService::class);
        $plain = $this->makeProduct('plain-cut', [['price' => 11000]]);
        $split = $this->makeProduct('giftcard-google-play-us', [
            ['name' => '10 USD', 'price' => 20000, 'sort_order' => 1],
            ['name' => '20 USD', 'price' => 30000, 'sort_order' => 2],
            ['name' => '30 USD', 'price' => 40000, 'sort_order' => 3],
            ['name' => '40 USD', 'price' => 50000, 'sort_order' => 4],
            ['name' => '50 USD', 'price' => 60000, 'sort_order' => 5],
            ['name' => '60 USD', 'price' => 70000, 'sort_order' => 6],
            ['name' => '70 USD', 'price' => 80000, 'sort_order' => 7],
            ['name' => '80 USD', 'price' => 90000, 'sort_order' => 8],
            ['name' => '90 USD', 'price' => 100000, 'sort_order' => 9],
            ['name' => '100 USD', 'price' => 110000, 'sort_order' => 10],
        ], ['title' => 'گیفت کارت Cut دلار آمریکا (USD)']);
        config([
            'torob.split_by_sku.enabled_product_ids' => [$split->id],
            'torob.split_by_sku_product_ids' => [$split->id],
        ]);

        $seen = [];
        $sum = 0;
        $page = 1;
        do {
            $payload = $this->query->paginated($page, 'date_added_desc');
            $this->assertSame(11, $payload['total']);
            $this->assertSame(3, $payload['max_pages']);
            $this->assertLessThanOrEqual(5, count($payload['products']));
            if ($page < 3) {
                $this->assertCount(5, $payload['products']);
            } else {
                $this->assertCount(1, $payload['products']);
            }
            foreach ($payload['products'] as $row) {
                $unique = (string) $row['page_unique'];
                $this->assertArrayNotHasKey($unique, $seen);
                $seen[$unique] = true;
            }
            $sum += count($payload['products']);
            $page++;
        } while ($page <= $payload['max_pages']);

        $this->assertSame(11, $sum);
        $this->assertCount(11, $seen);
        $this->assertArrayHasKey((string) $plain->id, $seen);
        $this->assertArrayHasKey((string) $split->id, $seen);
        $this->assertTrue(
            collect($this->query->paginated(1, 'date_added_desc')['products'])
                ->contains(fn (array $row): bool => str_starts_with((string) $row['page_unique'], $split->id.'_')
                    || (string) $row['page_unique'] === (string) $split->id),
        );
    }

    public function test_cursor_can_resume_inside_a_split_product(): void
    {
        config(['torob.page_size' => 4]);
        $this->query = $this->app->make(TorobProductQueryService::class);
        $this->makeProduct('plain-cursor-split', [['price' => 11000]]);
        $split = $this->makeProduct('giftcard-razer-gold-us', [
            ['name' => '10 USD', 'price' => 20000],
            ['name' => '20 USD', 'price' => 30000],
            ['name' => '30 USD', 'price' => 40000],
            ['name' => '40 USD', 'price' => 50000],
            ['name' => '50 USD', 'price' => 60000],
            ['name' => '60 USD', 'price' => 70000],
        ], ['title' => 'گیفت کارت Cursor دلار آمریکا (USD)']);
        config([
            'torob.split_by_sku.enabled_product_ids' => [$split->id],
            'torob.split_by_sku_product_ids' => [$split->id],
        ]);

        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $payload = $this->query->cursor($cursor);
            $pages++;
            $this->assertLessThanOrEqual(4, count($payload['products']));
            foreach ($payload['products'] as $row) {
                $unique = (string) $row['page_unique'];
                $this->assertArrayNotHasKey($unique, $seen);
                $seen[$unique] = true;
            }
            $next = $payload['cursor'] ?? null;
            if ($next === null) {
                break;
            }
            $cursor = (int) $next;
        } while ($pages < 10);

        $this->assertSame(7, count($seen));
        $this->assertGreaterThan(1, $pages);
    }

    public function test_page_size_is_exactly_one_hundred(): void
    {
        $this->assertSame(100, (int) config('torob.page_size'));
        for ($i = 1; $i <= 101; $i++) {
            $this->makeProduct('page-size-'.$i, [['price' => 10000 + $i]]);
        }

        $page1 = $this->query->paginated(1, 'date_added_desc');
        $page2 = $this->query->paginated(2, 'date_added_desc');

        $this->assertSame(101, $page1['total']);
        $this->assertSame(2, $page1['max_pages']);
        $this->assertCount(100, $page1['products']);
        $this->assertCount(1, $page2['products']);
    }

    public function test_row_pagination_first_middle_last_has_no_gaps_or_duplicates(): void
    {
        for ($i = 1; $i <= 250; $i++) {
            $this->makeProduct('page-nav-'.$i, [['price' => 10000 + $i]]);
        }

        $page1 = $this->query->paginated(1, 'date_added_desc');
        $page2 = $this->query->paginated(2, 'date_added_desc');
        $page3 = $this->query->paginated(3, 'date_added_desc');

        $this->assertSame(250, $page1['total']);
        $this->assertSame(3, $page1['max_pages']);
        $this->assertCount(100, $page1['products']);
        $this->assertCount(100, $page2['products']);
        $this->assertCount(50, $page3['products']);

        $uniques = array_merge(
            array_column($page1['products'], 'page_unique'),
            array_column($page2['products'], 'page_unique'),
            array_column($page3['products'], 'page_unique'),
        );
        $this->assertCount(250, $uniques);
        $this->assertCount(250, array_unique($uniques));
        $this->assertSame(250, $page1['total']);
        $this->assertSame($page1['total'], count($uniques));
    }

    public function test_eager_load_avoids_n_plus_one(): void
    {
        $this->makeProduct('n1-a', [['price' => 11000]]);
        $this->makeProduct('n1-b', [['price' => 12000]]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->query->paginated(1, 'date_added_desc');
        $sqls = collect(DB::getQueryLog())->pluck('query')->all();
        DB::disableQueryLog();

        $skuEager = collect($sqls)->filter(fn (string $sql) => (bool) preg_match('/from ["`]skus["`] where ["`]skus["`]\.["`]product_id["`] in/i', $sql));
        $groupEager = collect($sqls)->filter(fn (string $sql) => (bool) preg_match('/from ["`]catalog_groups["`] where ["`]catalog_groups["`]\.["`]id["`] in/i', $sql));
        $this->assertSame(1, $skuEager->count(), implode("\n", $sqls));
        $this->assertSame(1, $groupEager->count(), implode("\n", $sqls));
    }

    public function test_explain_sql_runs_without_error(): void
    {
        $this->makeProduct('explain-me', [['price' => 11000]]);
        $prefix = 'EXPLAIN QUERY PLAN ';
        foreach ($this->query->explainSqls(1, [1], ['https://charjino.ir/catalog/explain-me']) as $name => $sql) {
            $rows = DB::select($prefix.$sql);
            $this->assertNotEmpty($rows, $name.' '.$sql);
        }
    }

    public function test_image_report_says_dimensions_are_not_stored(): void
    {
        $this->makeProduct('image-report', [['price' => 11000]]);
        $this->artisan('torob:image-report')
            ->expectsOutputToContain('ابعاد ذخیره نشده')
            ->assertSuccessful();
    }

    public function test_stats_counts_listable_and_unavailable(): void
    {
        $this->makeProduct('stats-in', [['price' => 11000]]);
        $this->makeProduct('stats-out', [['price' => 12000]], [
            'visible' => false,
            'unavailable_at' => now(),
        ]);

        $counts = $this->query->availabilityCounts();
        $this->assertSame(2, $counts['listable']);
        $this->assertSame(1, $counts['availability_false']);
        $this->assertSame(2, $this->query->listableCount());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->query->listableCount();
        $sqls = collect(DB::getQueryLog())->pluck('query')->all();
        DB::disableQueryLog();
        foreach ($sqls as $sql) {
            $this->assertStringNotContainsString('offset', mb_strtolower($sql));
        }

        $this->artisan('torob:stats')
            ->expectsOutputToContain('listable=2')
            ->expectsOutputToContain('listable_not_indexable=نامعلوم')
            ->expectsOutputToContain('peak_memory_bytes=')
            ->expectsOutputToContain('elapsed_ms=')
            ->assertSuccessful();
    }

    /**
     * @param  list<array<string, mixed>>  $skus
     * @param  array<string, mixed>  $product
     */
    private function makeProduct(string $handle, array $skus, array $product = []): Product
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'torob-query-group'],
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

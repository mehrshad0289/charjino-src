<?php

namespace Tests\Feature;

use App\Http\Controllers\Torob\TorobProductController;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TorobProductApiTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private string $path = '/api/torob/v3/products';

    protected function setUp(): void
    {
        parent::setUp();
        $keypair = sodium_crypto_sign_keypair();
        $this->secret = base64_encode(sodium_crypto_sign_secretkey($keypair));
        config([
            'torob.jwt_skip' => false,
            'torob.jwt_audience' => 'api.charjino.ir',
            'torob.jwt_verify_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
            'torob.shop_base_url' => 'https://charjino.ir',
            'torob.page_size' => 100,
            'torob.cache_ttl' => 300,
            'torob.excluded_product_ids' => [],
            'torob.split_by_sku_product_ids' => [],
            'torob.split_by_sku.enabled_product_ids' => [],
            'torob.split_by_sku.enabled_group_slugs' => [],
            'torob.split_by_sku.exclude_product_ids' => [],
            'torob.split_by_sku.max_items_per_product' => 30,
            'torob.split_by_sku.canonical_path' => storage_path('framework/testing/torob-canonical-api.json'),
            'torob.split_by_sku.runtime_path' => storage_path('framework/testing/torob-runtime-api.json'),
        ]);
        @unlink(storage_path('framework/testing/torob-canonical-api.json'));
        @unlink(storage_path('framework/testing/torob-runtime-api.json'));
    }

    public function test_empty_body_returns_400(): void
    {
        $this->torobPost([])
            ->assertStatus(400)
            ->assertJson(['error' => 'request body is empty or invalid']);
    }

    public function test_page_without_sort_returns_400(): void
    {
        $this->torobPost(['page' => 1])
            ->assertStatus(400)
            ->assertJson(['error' => 'sort parameter is not provided']);
    }

    public function test_invalid_combination_returns_400(): void
    {
        $this->torobPost([
            'page' => 1,
            'sort' => 'date_added_desc',
            'page_urls' => ['https://charjino.ir/catalog/x'],
        ])->assertStatus(400)
            ->assertJson(['error' => 'invalid combination of parameters']);
    }

    public function test_more_than_one_hundred_urls_returns_400(): void
    {
        $urls = [];
        for ($i = 0; $i < 101; $i++) {
            $urls[] = 'https://charjino.ir/catalog/p-'.$i;
        }
        $this->torobPost(['page_urls' => $urls])
            ->assertStatus(400)
            ->assertJson(['error' => 'page_urls must not exceed 100 items']);
    }

    public function test_missing_token_returns_401(): void
    {
        $this->postJson($this->path, ['page' => 1, 'sort' => 'date_added_desc'])
            ->assertStatus(401)
            ->assertJson(['error' => 'missing X-Torob-Token']);
    }

    public function test_wrong_audience_returns_401(): void
    {
        $token = $this->token('other.example.com');
        $this->postJson($this->path, ['page' => 1, 'sort' => 'date_added_desc'], [
            'X-Torob-Token' => $token,
            'X-Torob-Token-Version' => '1',
        ])->assertStatus(401)
            ->assertJson(['error' => 'invalid audience']);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->postJson($this->path, ['page' => 1, 'sort' => 'date_added_desc'], [
            'X-Torob-Token' => 'not-a-jwt',
            'X-Torob-Token-Version' => '1',
        ]);
        $response->assertStatus(401);
        $this->assertNotSame('', (string) $response->json('error'));
    }

    public function test_get_method_returns_405(): void
    {
        $this->getJson($this->path)->assertStatus(405);
    }

    public function test_valid_payload_returns_v3(): void
    {
        $product = $this->makeProduct('torob-ok', [['price' => 88000]]);
        $response = $this->torobPost(['page' => 1, 'sort' => 'date_added_desc']);
        $response->assertOk()
            ->assertJsonPath('api_version', 'torob_api_v3')
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('products.0.page_unique', (string) $product->id)
            ->assertJsonPath('products.0.availability', true)
            ->assertJsonPath('products.0.current_price', 88000);
    }

    public function test_unavailable_product_has_zero_price(): void
    {
        $product = $this->makeProduct('torob-oos', [['price' => 77000]], [
            'visible' => false,
            'unavailable_at' => now(),
        ]);
        $this->torobPost(['page_uniques' => [(string) $product->id]])
            ->assertOk()
            ->assertJsonPath('products.0.availability', false)
            ->assertJsonPath('products.0.current_price', 0);
    }

    public function test_deleted_product_returns_empty_products(): void
    {
        $product = $this->makeProduct('torob-deleted', [['price' => 10000]]);
        $id = (string) $product->id;
        $product->delete();
        $this->torobPost(['page_uniques' => [$id]])
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('products', []);
    }

    public function test_fetch_by_url(): void
    {
        $product = $this->makeProduct('torob-by-url', [['price' => 33000]]);
        $this->torobPost(['page_urls' => ['https://charjino.ir/catalog/torob-by-url']])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('products.0.page_unique', (string) $product->id);
    }

    public function test_fetch_by_unique(): void
    {
        $product = $this->makeProduct('torob-by-unique', [['price' => 22000]]);
        $this->torobPost(['page_uniques' => [(string) $product->id]])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('products.0.page_unique', (string) $product->id);
    }

    public function test_urls_and_uniques_together_return_product_once(): void
    {
        $product = $this->makeProduct('giftcard-rewarble-usd', [['price' => 1459000]]);
        $response = $this->torobPost([
            'page_urls' => ['https://charjino.ir/catalog/giftcard-rewarble-usd'],
            'page_uniques' => [(string) $product->id],
        ]);
        $response->assertOk()
            ->assertJsonPath('api_version', 'torob_api_v3')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('products.0.page_unique', (string) $product->id)
            ->assertJsonPath('products.0.availability', true)
            ->assertJsonPath('products.0.current_price', 1459000);
        $this->assertCount(1, $response->json('products'));
    }

    public function test_urls_and_uniques_unknown_product_returns_empty_200(): void
    {
        $this->torobPost([
            'page_urls' => ['https://charjino.ir/catalog/does-not-exist-xyz'],
            'page_uniques' => ['99999999'],
        ])->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('products', []);
    }

    public function test_urls_and_uniques_with_page_still_400(): void
    {
        $this->torobPost([
            'page_urls' => ['https://charjino.ir/catalog/x'],
            'page_uniques' => ['1'],
            'page' => 1,
            'sort' => 'date_added_desc',
        ])->assertStatus(400)
            ->assertJson(['error' => 'invalid combination of parameters']);
    }

    public function test_split_sku_product_returns_ten_unique_items(): void
    {
        $other = $this->makeProduct('other-gift-card', [['price' => 99000]]);
        $product = $this->makeRewarbleUsd();
        $five = $product->skus->firstWhere('fazercards_offer_id', '5_usd');
        $ten = $product->skus->firstWhere('fazercards_offer_id', '10_usd');
        $this->assertNotNull($five);
        $this->assertNotNull($ten);

        $page = $this->torobPost(['page' => 1, 'sort' => 'date_added_desc'])->assertOk();
        $rows = collect($page->json('products'));
        $splitRows = $rows->filter(fn ($row) => ($row['product_group_id'] ?? null) === (string) $product->id)->values();
        $otherRow = $rows->first(fn ($row) => ($row['page_unique'] ?? null) === (string) $other->id);

        $this->assertSame(11, $page->json('total'));
        $this->assertCount(10, $splitRows);
        $this->assertSame(10, $splitRows->pluck('page_unique')->unique()->count());
        $this->assertSame(10, $splitRows->pluck('page_url')->unique()->count());
        $this->assertSame((string) $product->id, $splitRows->firstWhere('title', 'گیفت کارت Rewarble 5 دلار آمریکا (USD)')['page_unique']);
        $this->assertSame(
            'https://charjino.ir/catalog/giftcard-rewarble-usd?sku='.$five->id,
            $splitRows->firstWhere('page_unique', (string) $product->id)['page_url'],
        );
        $this->assertSame($product->id.'_'.$ten->id, $splitRows->firstWhere('title', 'گیفت کارت Rewarble 10 دلار آمریکا (USD)')['page_unique']);
        $this->assertNotNull($otherRow);
        $this->assertArrayNotHasKey('product_group_id', $otherRow);
        $this->assertSame('https://charjino.ir/catalog/other-gift-card', $otherRow['page_url']);
        $this->assertTrue($splitRows->every(fn ($row) => $row['availability'] === true));
        $this->assertSame(1459000, $splitRows->firstWhere('page_unique', (string) $product->id)['current_price']);
        $this->assertTrue($splitRows->every(fn ($row) => ($row['category_name'] ?? null) === 'گیفت کارت / دیجیتال'));
        $this->assertTrue($splitRows->every(fn ($row) => ($row['spec']['condition'] ?? null) === 'نو'));
    }

    public function test_split_sku_lookup_modes_are_unique(): void
    {
        $product = $this->makeRewarbleUsd();
        $five = $product->skus->firstWhere('fazercards_offer_id', '5_usd');
        $ten = $product->skus->firstWhere('fazercards_offer_id', '10_usd');
        $this->assertNotNull($five);
        $this->assertNotNull($ten);
        $baseUrl = 'https://charjino.ir/catalog/giftcard-rewarble-usd';
        $tenUnique = $product->id.'_'.$ten->id;

        $byUrls = $this->torobPost(['page_urls' => [$baseUrl]])->assertOk();
        $this->assertCount(10, $byUrls->json('products'));
        $this->assertSame(10, collect($byUrls->json('products'))->pluck('page_unique')->unique()->count());
        $this->assertTrue(collect($byUrls->json('products'))->contains(
            fn ($row) => $row['page_unique'] === (string) $product->id && $row['availability'] === true,
        ));

        $bySkuUrl = $this->torobPost(['page_urls' => [$baseUrl.'?sku='.$ten->id]])->assertOk();
        $this->assertCount(1, $bySkuUrl->json('products'));
        $this->assertSame($tenUnique, $bySkuUrl->json('products.0.page_unique'));
        $this->assertSame('گیفت کارت Rewarble 10 دلار آمریکا (USD)', $bySkuUrl->json('products.0.title'));

        $byCanonical = $this->torobPost(['page_uniques' => [(string) $product->id]])->assertOk();
        $this->assertCount(1, $byCanonical->json('products'));
        $this->assertSame((string) $product->id, $byCanonical->json('products.0.page_unique'));
        $this->assertSame('گیفت کارت Rewarble 5 دلار آمریکا (USD)', $byCanonical->json('products.0.title'));
        $this->assertTrue($byCanonical->json('products.0.availability'));

        $bySplitUnique = $this->torobPost(['page_uniques' => [$tenUnique]])->assertOk();
        $this->assertCount(1, $bySplitUnique->json('products'));
        $this->assertSame($tenUnique, $bySplitUnique->json('products.0.page_unique'));

        $allUniques = collect($product->skus)->map(
            fn ($sku) => $sku->fazercards_offer_id === '5_usd' ? (string) $product->id : $product->id.'_'.$sku->id,
        )->all();
        $byAllUniques = $this->torobPost(['page_uniques' => $allUniques])->assertOk();
        $this->assertCount(10, $byAllUniques->json('products'));
        $this->assertSame(10, collect($byAllUniques->json('products'))->pluck('page_unique')->unique()->count());

        $combined = $this->torobPost([
            'page_urls' => [$baseUrl, $baseUrl.'?sku='.$ten->id],
            'page_uniques' => [(string) $product->id, $tenUnique],
        ])->assertOk();
        $this->assertCount(10, $combined->json('products'));
        $this->assertSame(10, collect($combined->json('products'))->pluck('page_unique')->unique()->count());
        $this->assertSame(10, collect($combined->json('products'))->pluck('page_url')->unique()->count());

        $invalidSku = $this->torobPost(['page_urls' => [$baseUrl.'?sku=99999999']])->assertOk();
        $this->assertSame([], $invalidSku->json('products'));
        $this->assertSame(0, $invalidSku->json('total'));
    }

    public function test_page_has_exactly_one_hundred_products(): void
    {
        for ($i = 1; $i <= 101; $i++) {
            $this->makeProduct('torob-page-'.$i, [['price' => 10000 + $i]]);
        }
        $page1 = $this->torobPost(['page' => 1, 'sort' => 'date_added_desc']);
        $page2 = $this->torobPost(['page' => 2, 'sort' => 'date_added_desc']);
        $page1->assertOk()->assertJsonPath('total', 101)->assertJsonPath('max_pages', 2);
        $this->assertCount(100, $page1->json('products'));
        $page2->assertOk();
        $this->assertCount(1, $page2->json('products'));
    }

    public function test_cursor_two_pages_have_no_overlap(): void
    {
        for ($i = 1; $i <= 101; $i++) {
            $this->makeProduct('torob-cur-'.$i, [['price' => 10000 + $i]]);
        }
        $first = $this->torobPost(['sort' => 'product_id_desc'])->assertOk();
        $this->assertCount(100, $first->json('products'));
        $next = (string) $first->json('cursor');
        $this->assertNotSame('', $next);
        $second = $this->torobPost(['sort' => 'product_id_desc', 'cursor' => (int) $next])->assertOk();
        $this->assertCount(1, $second->json('products'));
        $ids1 = collect($first->json('products'))->pluck('page_unique');
        $ids2 = collect($second->json('products'))->pluck('page_unique');
        $this->assertCount(0, $ids1->intersect($ids2));
    }

    public function test_deleted_product_520_is_absent(): void
    {
        $this->makeProduct('placeholder-520', [['price' => 10000]]);
        $gone = new Product;
        $gone->id = 520;
        $gone->forceFill([
            'catalog_group_id' => CatalogGroup::query()->where('slug', 'torob-api-group')->value('id'),
            'handle' => 'deleted-520-api',
            'code' => 'deleted-520-api',
            'title' => 'حذف‌شده',
            'publisher' => '',
            'subtitle' => '',
            'thumbnail' => '',
            'category_name' => 'شارژینو',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 10,
            'inputs' => [],
        ]);
        $gone->save();
        Sku::query()->create([
            'product_id' => 520,
            'code' => 'deleted-520-api-sku',
            'name' => 'بسته',
            'price' => 10000,
            'status' => 'active',
            'visible' => true,
            'sort_order' => 1,
        ]);
        $gone->delete();
        $this->torobPost(['page_uniques' => ['520']])
            ->assertOk()
            ->assertJsonPath('products', []);
        $listed = $this->torobPost(['page' => 1, 'sort' => 'date_added_desc'])->json('products');
        $this->assertFalse(collect($listed)->contains(fn ($row) => ($row['page_unique'] ?? null) === '520'));
    }

    public function test_cache_keys_differ_for_two_pages(): void
    {
        for ($i = 1; $i <= 101; $i++) {
            $this->makeProduct('torob-cache-'.$i, [['price' => 10000 + $i]]);
        }
        $controller = $this->app->make(TorobProductController::class);
        $key1 = $controller->responseCacheKey(['page' => 1, 'sort' => 'date_added_desc']);
        $key2 = $controller->responseCacheKey(['page' => 2, 'sort' => 'date_added_desc']);
        $this->assertNotSame($key1, $key2);

        $first = $this->torobPost(['page' => 1, 'sort' => 'date_added_desc'])->assertOk();
        Product::query()->orderByDesc('id')->first()?->update(['title' => 'عنوان کش‌شده نیست']);
        $again = $this->torobPost(['page' => 1, 'sort' => 'date_added_desc'])->assertOk();
        $this->assertSame($first->json('products.0.title'), $again->json('products.0.title'));
        $page2 = $this->torobPost(['page' => 2, 'sort' => 'date_added_desc'])->assertOk();
        $this->assertCount(1, $page2->json('products'));
        $this->assertNotSame($first->json('products.0.page_unique'), $page2->json('products.0.page_unique'));
    }

    public function test_total_count_is_cached_across_pages(): void
    {
        for ($i = 1; $i <= 101; $i++) {
            $this->makeProduct('torob-count-'.$i, [['price' => 10000 + $i]]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->torobPost(['page' => 1, 'sort' => 'date_added_desc'])->assertOk();
        $afterFirst = collect(DB::getQueryLog())->pluck('query');
        $this->torobPost(['page' => 2, 'sort' => 'date_added_desc'])->assertOk();
        $afterSecond = collect(DB::getQueryLog())->pluck('query')->slice($afterFirst->count());
        DB::disableQueryLog();
        $countFirst = $afterFirst->filter(fn (string $sql) => (bool) preg_match('/count\(\*\)/i', $sql))->count();
        $countSecond = $afterSecond->filter(fn (string $sql) => (bool) preg_match('/count\(\*\)/i', $sql))->count();
        $this->assertGreaterThanOrEqual(1, $countFirst);
        $this->assertSame(0, $countSecond);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function torobPost(array $body)
    {
        return $this->postJson($this->path, $body, [
            'X-Torob-Token' => $this->token(),
            'X-Torob-Token-Version' => '1',
        ]);
    }

    private function token(string $aud = 'api.charjino.ir'): string
    {
        return JWT::encode([
            'aud' => $aud,
            'exp' => time() + 3600,
            'nbf' => time() - 30,
        ], $this->secret, 'EdDSA');
    }

    private function makeRewarbleUsd(): Product
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $amounts = [5, 10, 15, 20, 25, 30, 50, 100, 250, 500];
        $skus = [];
        foreach ($amounts as $index => $amount) {
            $skus[] = [
                'name' => $amount.' USD',
                'price' => $amount === 5 ? 1459000 : $amount * 291800,
                'fazercards_offer_id' => $amount.'_usd',
                'sort_order' => $index + 1,
            ];
        }
        $product = $this->makeProduct('giftcard-rewarble-usd', $skus, [
            'catalog_group_id' => $group->id,
            'title' => 'گیفت کارت Rewarble دلار آمریکا (USD)',
            'category_name' => $group->name,
        ]);
        config([
            'torob.split_by_sku_product_ids' => [$product->id],
            'torob.split_by_sku.enabled_product_ids' => [$product->id],
        ]);

        return $product->fresh(['skus', 'catalogGroup']);
    }

    /**
     * @param  list<array<string, mixed>>  $skus
     * @param  array<string, mixed>  $product
     */
    private function makeProduct(string $handle, array $skus, array $product = []): Product
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'torob-api-group'],
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

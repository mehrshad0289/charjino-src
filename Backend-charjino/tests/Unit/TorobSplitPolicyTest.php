<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobSplitCanonicalStore;
use App\Services\Torob\TorobSplitDecision;
use App\Services\Torob\TorobSplitPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorobSplitPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TorobProductMapper $mapper;

    private TorobSplitPolicy $policy;

    private TorobSplitCanonicalStore $canonical;

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
            'torob.split_by_sku.canonical_path' => storage_path('framework/testing/torob-canonical-policy.json'),
            'torob.split_by_sku.runtime_path' => storage_path('framework/testing/torob-runtime-policy.json'),
            'torob.split_by_sku.baseline_path' => storage_path('framework/testing/torob-baseline-policy.json'),
        ]);
        @unlink(storage_path('framework/testing/torob-canonical-policy.json'));
        @unlink(storage_path('framework/testing/torob-runtime-policy.json'));
        @unlink(storage_path('framework/testing/torob-baseline-policy.json'));
        $this->mapper = $this->app->make(TorobProductMapper::class);
        $this->policy = $this->app->make(TorobSplitPolicy::class);
        $this->canonical = $this->app->make(TorobSplitCanonicalStore::class);
    }

    public function test_config_default_enabled_product_is_rewarble_887(): void
    {
        $loaded = require config_path('torob.php');
        $this->assertContains(887, $loaded['split_by_sku']['enabled_product_ids']);
        $this->assertSame(30, $loaded['split_by_sku']['max_items_per_product']);
        $this->assertSame(storage_path('app/torob/rollout'), $loaded['split_by_sku']['rollout_dir']);
        $this->assertSame(500, $loaded['split_by_sku']['max_batch']);
        $this->assertSame(1.5, $loaded['split_by_sku']['max_feed_multiplier']);
        $this->assertSame('v3', $loaded['split_by_sku']['rule_version']);
        $this->assertSame([], $loaded['split_by_sku']['enabled_group_slugs']);
        $copy = require config_path('torob_split_copy.php');
        $this->assertSame(['steam-gifts', 'manual-services'], $copy['blocked_group_slugs']);
        $this->assertSame([], $copy['sku_name_labels']);
        $this->assertArrayHasKey('legacy-of-discord', $copy['brand_excluded_handle_fragments']);
        $this->assertSame(1500, $loaded['split_by_sku']['max_stage_extra_rows']);
    }

    public function test_single_sku_stays_one_row_even_when_enabled(): void
    {
        $product = $this->makeProduct('single-enabled', [
            ['name' => '10 USD', 'price' => 200000, 'fazercards_offer_id' => '10_usd'],
        ]);
        $this->enable($product->id);

        $rows = $this->mapper->toRows($product->fresh(['skus', 'catalogGroup']));
        $this->assertCount(1, $rows);
        $this->assertSame((string) $product->id, $rows[0]['page_unique']);
        $this->assertSame('https://charjino.ir/catalog/single-enabled', $rows[0]['page_url']);
        $this->assertArrayNotHasKey('product_group_id', $rows[0]);
    }

    public function test_multi_sku_split_keeps_cheapest_as_canonical_unique(): void
    {
        $product = $this->makeProduct('itunes-us', [
            ['name' => '10 USD', 'price' => 300000, 'sort_order' => 1],
            ['name' => '25 USD', 'price' => 700000, 'sort_order' => 2],
        ], ['title' => 'گیفت کارت Apple دلار آمریکا (USD)']);
        $this->enable($product->id);
        $fresh = $product->fresh(['skus', 'catalogGroup']);
        $cheap = $fresh->skus->firstWhere('name', '10 USD');
        $other = $fresh->skus->firstWhere('name', '25 USD');

        $rows = $this->mapper->toRows($fresh);
        $this->assertCount(2, $rows);
        $this->assertSame((string) $fresh->id, $rows[0]['page_unique']);
        $this->assertSame('https://charjino.ir/catalog/itunes-us?sku='.$cheap->id, $rows[0]['page_url']);
        $this->assertSame($fresh->id.'_'.$other->id, $rows[1]['page_unique']);
        $this->assertSame((string) $fresh->id, $rows[0]['product_group_id']);
        $this->assertSame('گیفت کارت اپل آیتونز آمریکا 10 دلار (US)', $rows[0]['title']);
        $this->assertSame('گیفت کارت اپل آیتونز آمریکا 25 دلار (US)', $rows[1]['title']);
        $this->assertCount(2, collect($rows)->pluck('page_unique')->unique());
        $this->assertCount(2, collect($rows)->pluck('title')->unique());
    }

    public function test_frozen_canonical_does_not_move_when_prices_change(): void
    {
        $product = $this->makeProduct('giftcard-steam-wallet-us', [
            ['name' => '10 USD', 'price' => 200000, 'sort_order' => 1],
            ['name' => '50 USD', 'price' => 900000, 'sort_order' => 2],
        ], ['title' => 'گیفت کارت Steam دلار آمریکا (USD)']);
        $this->enable($product->id);
        $fresh = $product->fresh(['skus', 'catalogGroup']);
        $cheap = $fresh->skus->firstWhere('name', '10 USD');
        $expensive = $fresh->skus->firstWhere('name', '50 USD');
        $this->canonical->put((int) $fresh->id, (int) $cheap->id);

        $expensive->update(['price' => 50000]);
        $after = $product->fresh(['skus', 'catalogGroup']);
        $rows = $this->mapper->toRows($after);
        $canonicalRow = collect($rows)->firstWhere('page_unique', (string) $after->id);

        $this->assertNotNull($canonicalRow);
        $this->assertSame('https://charjino.ir/catalog/giftcard-steam-wallet-us?sku='.$cheap->id, $canonicalRow['page_url']);
        $this->assertSame(200000, $canonicalRow['current_price']);
        $this->assertNotSame((string) $after->id, collect($rows)->firstWhere('page_unique', $after->id.'_'.$expensive->id)['page_unique'] ?? null);
        $this->assertSame($after->id.'_'.$expensive->id, collect($rows)->firstWhere('title', 'گیفت کارت استیم آمریکا 50 دلار (US)')['page_unique']);
    }

    public function test_unavailable_canonical_stays_with_availability_false(): void
    {
        $product = $this->makeProduct('giftcard-xbox-us', [
            ['name' => '10 USD', 'price' => 200000, 'sort_order' => 1],
            ['name' => '25 USD', 'price' => 500000, 'sort_order' => 2],
        ], ['title' => 'گیفت کارت Xbox دلار آمریکا (USD)']);
        $this->enable($product->id);
        $fresh = $product->fresh(['skus', 'catalogGroup']);
        $cheap = $fresh->skus->firstWhere('name', '10 USD');
        $this->canonical->put((int) $fresh->id, (int) $cheap->id);
        $cheap->update(['price' => 0]);

        $rows = $this->mapper->toRows($product->fresh(['skus', 'catalogGroup']));
        $canonicalRow = collect($rows)->firstWhere('page_unique', (string) $product->id);

        $this->assertCount(2, $rows);
        $this->assertNotNull($canonicalRow);
        $this->assertFalse($canonicalRow['availability']);
        $this->assertSame(0, $canonicalRow['current_price']);
        $this->assertTrue(collect($rows)->contains(fn (array $row): bool => $row['availability'] === true && $row['current_price'] === 500000));
    }

    public function test_empty_labels_keep_a_single_normal_row(): void
    {
        $product = $this->makeProduct('giftcard-rewarble-usd', [
            ['name' => '', 'price' => 120000, 'sort_order' => 1],
            ['name' => '', 'price' => 150000, 'sort_order' => 2],
        ], ['title' => 'محصول بدون برچسب']);
        $this->enable($product->id);
        $fresh = $product->fresh(['skus', 'catalogGroup']);

        $this->assertSame(TorobSplitDecision::REASON_EMPTY_LABELS, $this->policy->decide($fresh)->reason);
        $rows = $this->mapper->toRows($fresh);
        $this->assertCount(1, $rows);
        $this->assertSame((string) $fresh->id, $rows[0]['page_unique']);
        $this->assertSame('https://charjino.ir/catalog/giftcard-rewarble-usd', $rows[0]['page_url']);
        $this->assertArrayNotHasKey('product_group_id', $rows[0]);
    }

    public function test_unknown_brand_keeps_a_single_normal_row(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = $this->makeProduct('giftcard-obscure-usd', [
            ['name' => '10 USD', 'price' => 200000, 'sort_order' => 1],
            ['name' => '25 USD', 'price' => 400000, 'sort_order' => 2],
        ], [
            'catalog_group_id' => $group->id,
            'title' => 'Obscure Wallet (US)',
        ]);
        $this->enable($product->id);
        $fresh = $product->fresh(['skus', 'catalogGroup']);

        $this->assertSame(TorobSplitDecision::REASON_UNKNOWN_MAPPING, $this->policy->decide($fresh)->reason);
        $rows = $this->mapper->toRows($fresh);
        $this->assertCount(1, $rows);
        $this->assertSame((string) $fresh->id, $rows[0]['page_unique']);
        $this->assertSame('گیفت کارت Obscure Wallet (US)', $rows[0]['title']);
        $this->assertArrayNotHasKey('product_group_id', $rows[0]);
    }

    public function test_unmapped_topup_unit_keeps_a_single_row(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'شارژ بازی', 'title' => 'شارژ بازی', 'visible' => true, 'sort_order' => 10],
        );
        $product = $this->makeProduct('topup-steam-wallet', [
            ['name' => '10 USD', 'price' => 200000, 'sort_order' => 1],
            ['name' => '20 USD', 'price' => 400000, 'sort_order' => 2],
        ], [
            'catalog_group_id' => $group->id,
            'title' => 'شارژ استیم',
        ]);
        $this->enable($product->id);
        $fresh = $product->fresh(['skus', 'catalogGroup']);

        $this->assertSame(TorobSplitDecision::REASON_UNKNOWN_MAPPING, $this->policy->decide($fresh)->reason);
        $this->assertCount(1, $this->mapper->toRows($fresh));
        $this->assertSame((string) $fresh->id, $this->mapper->toRows($fresh)[0]['page_unique']);
    }

    public function test_over_max_items_keeps_a_single_normal_row(): void
    {
        config(['torob.split_by_sku.max_items_per_product' => 2]);
        $skus = [];
        foreach ([10, 25, 50] as $i => $amount) {
            $skus[] = ['name' => $amount.' USD', 'price' => $amount * 10000, 'sort_order' => $i + 1];
        }
        $product = $this->makeProduct('over-max', $skus, ['title' => 'گیفت کارت Test دلار آمریکا (USD)']);
        $this->enable($product->id);
        $fresh = $product->fresh(['skus', 'catalogGroup']);

        $this->assertSame(TorobSplitDecision::REASON_OVER_MAX, $this->policy->decide($fresh)->reason);
        $rows = $this->mapper->toRows($fresh);
        $this->assertCount(1, $rows);
        $this->assertSame((string) $fresh->id, $rows[0]['page_unique']);
    }

    public function test_exclude_wins_over_enabled_group(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = $this->makeProduct('excluded-in-group', [
            ['name' => '10 USD', 'price' => 200000],
            ['name' => '20 USD', 'price' => 400000],
        ], [
            'catalog_group_id' => $group->id,
            'title' => 'گیفت کارت Demo دلار آمریکا (USD)',
        ]);
        config([
            'torob.split_by_sku.enabled_group_slugs' => ['gift-card'],
            'torob.split_by_sku.exclude_product_ids' => [$product->id],
        ]);
        $fresh = $product->fresh(['skus', 'catalogGroup']);

        $this->assertSame(TorobSplitDecision::REASON_EXCLUDED, $this->policy->decide($fresh)->reason);
        $this->assertCount(1, $this->mapper->toRows($fresh));
    }

    public function test_group_slug_enables_eligible_product(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = $this->makeProduct('giftcard-google-play-us', [
            ['name' => '5 USD', 'price' => 100000],
            ['name' => '10 USD', 'price' => 200000],
        ], [
            'catalog_group_id' => $group->id,
            'title' => 'گیفت کارت Razor دلار آمریکا (USD)',
        ]);
        config(['torob.split_by_sku.enabled_group_slugs' => ['gift-card']]);
        $fresh = $product->fresh(['skus', 'catalogGroup']);

        $this->assertTrue($this->policy->decide($fresh)->split);
        $this->assertCount(2, $this->mapper->toRows($fresh));
    }

    public function test_feed_price_matches_catalog_sku_price(): void
    {
        $product = $this->makeProduct('giftcard-google-play-us', [
            [
                'name' => '10 USD',
                'price' => 257100,
                'sale_price' => 220000,
                'sale_ends_at' => now()->addDay(),
                'sort_order' => 1,
            ],
            ['name' => '25 USD', 'price' => 500000, 'sort_order' => 2],
        ], ['title' => 'گیفت کارت Match دلار آمریکا (USD)']);
        $this->enable($product->id);
        $fresh = $product->fresh(['skus', 'catalogGroup']);
        $ten = $fresh->skus->firstWhere('name', '10 USD');

        $page = $this->getJson('/api/catalog/products/giftcard-google-play-us')->assertOk();
        $feed = collect($this->mapper->toRows($fresh))->firstWhere('page_url', 'https://charjino.ir/catalog/giftcard-google-play-us?sku='.$ten->id);
        $apiSku = collect($page->json('data.skus'))->firstWhere('id', $ten->id);

        $this->assertNotNull($feed);
        $this->assertNotNull($apiSku);
        $this->assertSame($ten->sellPrice(), $feed['current_price']);
        $this->assertSame($ten->sellPrice(), (int) $apiSku['price']);
        $this->assertSame(220000, $feed['current_price']);
    }

    public function test_normalized_titles_must_be_unique(): void
    {
        $product = $this->makeProduct('giftcard-google-play-us', [
            ['name' => '10 USD', 'price' => 200000, 'sort_order' => 1],
            ['name' => '10 USD', 'price' => 210000, 'sort_order' => 2],
        ], ['title' => 'گیفت کارت Dup دلار آمریکا (USD)']);
        $this->enable($product->id);
        $fresh = $product->fresh(['skus', 'catalogGroup']);

        $this->assertSame(TorobSplitDecision::REASON_AMBIGUOUS_TITLES, $this->policy->decide($fresh)->reason);
        $this->assertCount(1, $this->mapper->toRows($fresh));
    }

    private function enable(int $id): void
    {
        config([
            'torob.split_by_sku.enabled_product_ids' => [$id],
            'torob.split_by_sku_product_ids' => [$id],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $skus
     * @param  array<string, mixed>  $product
     */
    private function makeProduct(string $handle, array $skus, array $product = []): Product
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'torob-split-group'],
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

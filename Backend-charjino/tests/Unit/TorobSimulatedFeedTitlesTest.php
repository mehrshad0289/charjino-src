<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Torob\TorobFeedCatalogCopy;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobSplitPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorobSimulatedFeedTitlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulated_split_titles_are_unique_and_persian(): void
    {
        config([
            'torob.split_by_sku.canonical_path' => storage_path('framework/testing/torob-canonical-sim.json'),
            'torob.split_by_sku.runtime_path' => storage_path('framework/testing/torob-runtime-sim.json'),
            'torob.split_by_sku.baseline_path' => storage_path('framework/testing/torob-baseline-sim.json'),
        ]);
        @unlink(storage_path('framework/testing/torob-canonical-sim.json'));
        $gift = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $telegram = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'telegram'],
            ['name' => 'تلگرام', 'title' => 'تلگرام', 'visible' => true, 'sort_order' => 30],
        );
        $products = [];
        $gp = $this->gift($gift, 'giftcard-google-play-ae', '10_usd', '10 USD');
        $it = $this->gift($gift, 'giftcard-app-store-itunes-ae', '50_aed', '50 AED');
        $tg = Product::query()->create([
            'catalog_group_id' => $telegram->id,
            'handle' => 'telegram-stars-stars',
            'code' => 'telegram-stars-stars',
            'title' => 'Stars',
            'publisher' => '',
            'subtitle' => '',
            'thumbnail' => '',
            'category_name' => $telegram->name,
            'visible' => true,
            'status' => 'active',
            'indexable' => true,
            'sort_order' => 1,
            'inputs' => [],
        ]);
        Sku::query()->create([
            'product_id' => $tg->id,
            'code' => 's50',
            'name' => '50 Stars',
            'price' => 10000,
            'status' => 'active',
            'visible' => true,
            'sort_order' => 1,
        ]);
        Sku::query()->create([
            'product_id' => $tg->id,
            'code' => 's100',
            'name' => '100 Stars',
            'price' => 20000,
            'status' => 'active',
            'visible' => true,
            'sort_order' => 2,
        ]);
        $products = [$gp->id, $it->id, $tg->id];
        config([
            'torob.split_by_sku.enabled_product_ids' => $products,
            'torob.split_by_sku_product_ids' => $products,
        ]);
        $policy = $this->app->make(TorobSplitPolicy::class)->withSimulateAll();
        $mapper = $this->app->make(TorobProductMapper::class);
        $copy = $this->app->make(TorobFeedCatalogCopy::class);
        $keys = [];
        foreach ([$gp, $it, $tg] as $product) {
            $fresh = $product->fresh(['skus', 'catalogGroup']);
            $this->assertTrue($policy->decide($fresh)->split);
            foreach ($mapper->toRows($fresh) as $row) {
                $title = (string) $row['title'];
                $body = preg_replace('/\s*\([A-Z]{2,8}\)$/', '', $title) ?? $title;
                $this->assertMatchesRegularExpression('/^[\p{Arabic}0-9\s\x{200C}\x{200D}]+$/u', $body, $title);
                $key = $copy->normalizeTitleKey($title);
                $this->assertNotContains($key, $keys, $title);
                $keys[] = $key;
            }
        }
    }

    private function gift(CatalogGroup $group, string $handle, string $offer, string $name): Product
    {
        $product = Product::query()->create([
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
            'sort_order' => 1,
            'inputs' => [],
        ]);
        Sku::query()->create([
            'product_id' => $product->id,
            'code' => $handle.'-1',
            'name' => $name,
            'price' => 100000,
            'status' => 'active',
            'visible' => true,
            'sort_order' => 1,
            'fazercards_offer_id' => $offer,
        ]);
        Sku::query()->create([
            'product_id' => $product->id,
            'code' => $handle.'-2',
            'name' => '99 USD',
            'price' => 200000,
            'status' => 'active',
            'visible' => true,
            'sort_order' => 2,
            'fazercards_offer_id' => '99_usd',
        ]);

        return $product;
    }
}

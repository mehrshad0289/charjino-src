<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Torob\TorobFeedCatalogCopy;
use App\Services\Torob\TorobProductMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorobTopupRegionTitleCollisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_regional_valorant_topups_include_region_and_stay_unique(): void
    {
        config([
            'torob.split_by_sku.canonical_path' => storage_path('framework/testing/torob-canonical-valorant.json'),
            'torob.split_by_sku.runtime_path' => storage_path('framework/testing/torob-runtime-valorant.json'),
            'torob.split_by_sku.baseline_path' => storage_path('framework/testing/torob-baseline-valorant.json'),
        ]);
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'شارژ', 'title' => 'شارژ', 'visible' => true, 'sort_order' => 10],
        );
        $ids = [];
        foreach (['id', 'ph'] as $region) {
            $handle = 'topup-valorant-'.$region;
            $product = Product::query()->create([
                'catalog_group_id' => $group->id,
                'handle' => $handle,
                'code' => $handle,
                'title' => 'Valorant ('.strtoupper($region).')',
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
                'code' => $handle.'-475',
                'name' => '475 VP',
                'price' => 100000,
                'status' => 'active',
                'visible' => true,
                'sort_order' => 1,
            ]);
            Sku::query()->create([
                'product_id' => $product->id,
                'code' => $handle.'-1000',
                'name' => '1000 VP',
                'price' => 200000,
                'status' => 'active',
                'visible' => true,
                'sort_order' => 2,
            ]);
            $ids[] = $product->id;
        }
        config([
            'torob.split_by_sku.enabled_product_ids' => $ids,
            'torob.split_by_sku_product_ids' => $ids,
        ]);
        $copy = $this->app->make(TorobFeedCatalogCopy::class);
        $mapper = $this->app->make(TorobProductMapper::class);
        $titles = [];
        foreach ($ids as $id) {
            $product = Product::query()->with(['skus', 'catalogGroup'])->findOrFail($id);
            foreach ($mapper->toRows($product) as $row) {
                $titles[] = $row['title'];
            }
        }
        $this->assertSame('شارژ ولورانت 475 ولورانت پوینت اندونزی (ID)', $titles[0]);
        $this->assertSame('شارژ ولورانت 1000 ولورانت پوینت اندونزی (ID)', $titles[1]);
        $this->assertSame('شارژ ولورانت 475 ولورانت پوینت فیلیپین (PH)', $titles[2]);
        $this->assertCount(count($titles), array_unique($titles));
        $this->assertNotSame($copy->normalizeTitleKey($titles[0]), $copy->normalizeTitleKey($titles[2]));
    }

    public function test_discord_needle_does_not_match_legacy_of_discord_handle(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'شارژ', 'title' => 'شارژ', 'visible' => true, 'sort_order' => 10],
        );
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'topup-legacy-of-discord-furiouswings',
            'code' => 'topup-legacy-of-discord-furiouswings',
            'title' => 'Legacy of Discord',
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
        $copy = $this->app->make(TorobFeedCatalogCopy::class);
        $this->assertNull($copy->brandNeedle($product->fresh(['catalogGroup'])));
    }
}

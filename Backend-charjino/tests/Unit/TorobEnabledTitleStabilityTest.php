<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Torob\TorobProductMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage-1 enabled handles must keep split titles when only non-overlapping mappings are added to config.
 */
class TorobEnabledTitleStabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, string>
     */
    private const STAGE1_HANDLES = [
        'giftcard-google-play-us' => 'گیفت کارت گوگل پلی آمریکا 10 دلار (US)',
        'giftcard-google-play-uk' => 'گیفت کارت گوگل پلی انگلیس 25 پوند (UK)',
        'giftcard-app-store-itunes-us' => 'گیفت کارت اپل آیتونز آمریکا 10 دلار (US)',
        'giftcard-playstation-us' => 'گیفت کارت پلی استیشن آمریکا 50 دلار (US)',
        'giftcard-steam-cis' => 'گیفت کارت استیم سی‌آی‌اس 5 دلار (CIS)',
        'giftcard-xbox-us' => 'گیفت کارت ایکس باکس آمریکا 10 دلار (US)',
        'giftcard-razer-gold-us' => 'گیفت کارت ریزر گلد آمریکا 10 دلار (US)',
        'giftcard-nintendo-us' => 'گیفت کارت نینتندو آمریکا 10 دلار (US)',
        'giftcard-spotify-us' => 'گیفت کارت اسپاتیفای آمریکا 10 دلار (US)',
        'giftcard-netflix-us' => 'گیفت کارت نتفلیکس آمریکا 10 دلار (US)',
    ];

    public function test_stage1_style_handles_keep_persian_split_titles(): void
    {
        config([
            'torob.split_by_sku.canonical_path' => storage_path('framework/testing/torob-canonical-stability.json'),
            'torob.split_by_sku.runtime_path' => storage_path('framework/testing/torob-runtime-stability.json'),
            'torob.split_by_sku.baseline_path' => storage_path('framework/testing/torob-baseline-stability.json'),
        ]);
        @unlink(storage_path('framework/testing/torob-canonical-stability.json'));
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $mapper = $this->app->make(TorobProductMapper::class);
        $ids = [];
        foreach (self::STAGE1_HANDLES as $handle => $expectedFirst) {
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
                'sort_order' => 10,
                'inputs' => [],
            ]);
            $offer = str_contains($expectedFirst, 'پوند') ? '25_gbp' : (str_contains($expectedFirst, 'یورو') ? '5_eur' : '10_usd');
            $name = str_contains($expectedFirst, 'پوند') ? '25 GBP' : (str_contains($handle, 'cis') ? '5 USD' : '10 USD');
            if (str_contains($expectedFirst, '50 دلار')) {
                $offer = '50_usd';
                $name = '50 USD';
            }
            if (str_contains($handle, 'cis')) {
                $offer = '5_usd';
                $name = '5 USD';
            }
            Sku::query()->create([
                'product_id' => $product->id,
                'code' => $handle.'-a',
                'name' => $name,
                'price' => 100000,
                'status' => 'active',
                'visible' => true,
                'sort_order' => 1,
                'fazercards_offer_id' => $offer,
            ]);
            Sku::query()->create([
                'product_id' => $product->id,
                'code' => $handle.'-b',
                'name' => '99 USD',
                'price' => 200000,
                'status' => 'active',
                'visible' => true,
                'sort_order' => 2,
                'fazercards_offer_id' => '99_usd',
            ]);
            $ids[] = $product->id;
        }
        config([
            'torob.split_by_sku.enabled_product_ids' => $ids,
            'torob.split_by_sku_product_ids' => $ids,
        ]);
        foreach (self::STAGE1_HANDLES as $handle => $expectedFirst) {
            $product = Product::query()->with(['skus', 'catalogGroup'])->where('handle', $handle)->firstOrFail();
            $rows = $mapper->toRows($product);
            $this->assertSame($expectedFirst, $rows[0]['title'], $handle);
            $titles = array_column($rows, 'title');
            $this->assertCount(count(array_unique($titles)), $titles, $handle);
        }
    }
}

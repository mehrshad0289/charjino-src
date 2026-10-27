<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Torob\TorobFeedCatalogCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorobFeedCatalogCopyMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapping_gap_reports_unknown_brand_for_unmapped_handle(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'giftcard-nonesuch-zz',
            'code' => 'giftcard-nonesuch-zz',
            'title' => 'Nonesuch',
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
        $sku = Sku::query()->create([
            'product_id' => $product->id,
            'code' => 'n1',
            'name' => '10 USD',
            'price' => 100000,
            'status' => 'active',
            'visible' => true,
            'sort_order' => 1,
            'fazercards_offer_id' => '10_usd',
        ]);
        $copy = $this->app->make(TorobFeedCatalogCopy::class);
        $this->assertSame('unknown_brand', $copy->mappingGap($product->fresh(['catalogGroup']), $sku));
    }

    public function test_persian_title_allows_zwnj_in_region_name(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'giftcard-playstation-hk',
            'code' => 'giftcard-playstation-hk',
            'title' => 'PlayStation HK',
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
        $sku = Sku::query()->create([
            'product_id' => $product->id,
            'code' => 'hk1',
            'name' => '50 HKD',
            'price' => 100000,
            'status' => 'active',
            'visible' => true,
            'sort_order' => 1,
            'fazercards_offer_id' => '50_hkd',
        ]);
        $copy = $this->app->make(TorobFeedCatalogCopy::class);
        $title = $copy->persianSkuTitle($product->fresh(['catalogGroup']), $sku);
        $this->assertNotNull($title);
        $this->assertStringContainsString('هنگ', $title);
        $this->assertStringContainsString('(HK)', $title);
    }

    public function test_telegram_stars_title_does_not_repeat_brand(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'telegram'],
            ['name' => 'تلگرام', 'title' => 'تلگرام', 'visible' => true, 'sort_order' => 30],
        );
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'telegram-stars-stars',
            'code' => 'telegram-stars-stars',
            'title' => 'Telegram Stars',
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
        $sku = Sku::query()->create([
            'product_id' => $product->id,
            'code' => 'stars-50',
            'name' => '50 Stars',
            'price' => 100000,
            'status' => 'active',
            'visible' => true,
            'sort_order' => 1,
        ]);
        $copy = $this->app->make(TorobFeedCatalogCopy::class);
        $title = $copy->persianSkuTitle($product->fresh(['catalogGroup']), $sku);
        $this->assertSame('شارژ تلگرام 50 ستاره', $title);
        $this->assertSame(1, mb_substr_count((string) $title, 'تلگرام'));
        $this->assertStringNotContainsString('تلگرام تلگرام', (string) $title);
    }

    public function test_airbnb_and_new_region_currency_titles_are_complete_persian(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $cases = [
            ['giftcard-airbnb-at', '50_eur', '50 EUR', 'گیفت کارت ایربی‌ان‌بی اتریش 50 یورو (AT)'],
            ['giftcard-app-store-itunes-cn', '100_cny', '100 CNY', 'گیفت کارت اپل آیتونز چین 100 یوان (CN)'],
            ['giftcard-app-store-itunes-ru', '1000_rub', '1000 RUB', 'گیفت کارت اپل آیتونز روسیه 1000 روبل (RU)'],
            ['giftcard-playstation-hu', '10_huf', '10 HUF', 'گیفت کارت پلی استیشن مجارستان 10 فورینت (HU)'],
            ['giftcard-google-play-uk', '25_gbp', '25 GBP', 'گیفت کارت گوگل پلی انگلیس 25 پوند (UK)'],
            ['giftcard-tiktok-us', '10_usd', '10 USD', 'گیفت کارت تیک‌تاک آمریکا 10 دلار (US)'],
            ['giftcard-twitch-us', '25_usd', '25 USD', 'گیفت کارت توییچ آمریکا 25 دلار (US)'],
            ['giftcard-tinder-us', '15_usd', '15 USD', 'گیفت کارت تیندر آمریکا 15 دلار (US)'],
        ];
        $copy = $this->app->make(TorobFeedCatalogCopy::class);
        foreach ($cases as $index => [$handle, $offer, $skuName, $expected]) {
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
                'sort_order' => 10 + $index,
                'inputs' => [],
            ]);
            $sku = Sku::query()->create([
                'product_id' => $product->id,
                'code' => $handle.'-1',
                'name' => $skuName,
                'price' => 100000,
                'status' => 'active',
                'visible' => true,
                'sort_order' => 1,
                'fazercards_offer_id' => $offer,
            ]);
            $title = $copy->persianSkuTitle($product->fresh(['catalogGroup']), $sku);
            $this->assertSame($expected, $title, $handle);
        }
    }
}

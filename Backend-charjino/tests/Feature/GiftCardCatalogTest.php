<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftCardCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_gift_card_filters_by_brand_then_region(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            [
                'name' => 'Gift cards',
                'title' => 'گیفت کارت',
                'visible' => true,
                'sort_order' => 20,
            ],
        );
        $this->makeCard($group, 'xbox-us', 'Xbox US', 'xbox', 'US', 40000);
        $this->makeCard($group, 'xbox-uk', 'Xbox UK', 'xbox', 'UK', 45000);
        $this->makeCard($group, 'ps-us', 'PlayStation US', 'playstation', 'US', 50000);

        $list = $this->getJson('/api/catalog/products?category=gift-card&page=1&perPage=50');
        $list->assertOk();
        $this->assertEqualsCanonicalizing(
            ['playstation', 'xbox'],
            $list->json('data.meta.platforms'),
        );
        $this->assertEqualsCanonicalizing(
            ['UK', 'US'],
            $list->json('data.meta.regions'),
        );

        $xbox = $this->getJson('/api/catalog/products?category=gift-card&page=1&perPage=50&platform=xbox');
        $xbox->assertOk();
        $this->assertEqualsCanonicalizing(
            ['xbox-us', 'xbox-uk'],
            collect($xbox->json('data.items'))->pluck('handle')->all(),
        );
        $this->assertEqualsCanonicalizing(['UK', 'US'], $xbox->json('data.meta.regions'));

        $xboxUs = $this->getJson('/api/catalog/products?category=gift-card&page=1&perPage=50&platform=xbox&region=US');
        $xboxUs->assertOk();
        $this->assertSame(['xbox-us'], collect($xboxUs->json('data.items'))->pluck('handle')->all());
    }

    public function test_gift_card_search_matches_title_and_brand_not_sku(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            [
                'name' => 'Gift cards',
                'title' => 'گیفت کارت',
                'visible' => true,
                'sort_order' => 20,
            ],
        );
        $this->makeCard($group, 'xbox-us', 'Xbox US', 'xbox', 'US', 40000);
        $this->makeCard($group, 'ps-us', 'PlayStation US', 'playstation', 'US', 50000);

        $xbox = $this->getJson('/api/catalog/products?category=gift-card&page=1&perPage=50&q=Xbox');
        $xbox->assertOk();
        $this->assertSame(['xbox-us'], collect($xbox->json('data.items'))->pluck('handle')->all());

        $skuName = $this->getJson('/api/catalog/products?category=gift-card&page=1&perPage=50&q=10');
        $skuName->assertOk();
        $this->assertSame([], collect($skuName->json('data.items'))->pluck('handle')->all());
    }

    public function test_gift_card_sorts_by_price_and_new(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            [
                'name' => 'Gift cards',
                'title' => 'گیفت کارت',
                'visible' => true,
                'sort_order' => 20,
            ],
        );
        $this->makeCard($group, 'xbox-us', 'Xbox US', 'xbox', 'US', 40000);
        $this->makeCard($group, 'ps-us', 'PlayStation US', 'playstation', 'US', 90000);

        $byPrice = $this->getJson('/api/catalog/products?category=gift-card&page=1&perPage=50&sort=price');
        $byPrice->assertOk();
        $this->assertSame(['xbox-us', 'ps-us'], collect($byPrice->json('data.items'))->pluck('handle')->all());

        $byNew = $this->getJson('/api/catalog/products?category=gift-card&page=1&perPage=50&sort=new');
        $byNew->assertOk();
        $this->assertSame(['ps-us', 'xbox-us'], collect($byNew->json('data.items'))->pluck('handle')->all());
    }

    private function makeCard(
        CatalogGroup $group,
        string $handle,
        string $title,
        string $platform,
        string $region,
        int $price,
    ): Product {
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $title,
            'publisher' => '',
            'category_name' => $group->title,
            'subtitle' => 'کد گیفت',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => FazerCardsIds::GIFTCARD,
            'platform' => $platform,
            'region' => $region,
            'inputs' => [],
            'sort_order' => 10,
        ]);
        Sku::query()->create([
            'product_id' => $product->id,
            'code' => $handle.'-10',
            'name' => '10',
            'price' => $price,
            'cost_usd' => '10',
            'status' => 'active',
            'visible' => true,
            'fulfillment_type' => FazerCardsIds::GIFTCARD,
            'region' => $region,
            'sort_order' => 10,
        ]);

        return $product;
    }
}

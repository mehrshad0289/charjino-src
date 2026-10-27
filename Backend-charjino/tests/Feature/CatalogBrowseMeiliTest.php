<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\CatalogTextIdResolver;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogBrowseMeiliTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_empty_q_does_not_restrict_catalog(): void
    {
        $lookup = $this->mock(CatalogTextIdResolver::class);
        $lookup->shouldReceive('handles')->never();

        $res = $this->getJson('/api/catalog/products?page=1&perPage=10');
        $res->assertOk();
        $this->assertNotEmpty($res->json('data.items'));
    }

    public function test_persian_and_english_q_use_meili_handles_then_mysql(): void
    {
        $group = $this->giftGroup();
        $this->makeCard($group, 'xbox-us', 'Xbox US', 'xbox', 'US', 40000);
        $this->makeCard($group, 'ps-us', 'PlayStation US', 'playstation', 'US', 50000);

        $this->mock(CatalogTextIdResolver::class, function ($mock) {
            $mock->shouldReceive('handles')->once()->with('پابجی', null)->andReturn(['xbox-us']);
            $mock->shouldReceive('handles')->once()->with('pubg', null)->andReturn(['ps-us']);
        });

        $fa = $this->getJson('/api/catalog/products?q='.rawurlencode('پابجی').'&page=1&perPage=20');
        $fa->assertOk();
        $this->assertSame(['xbox-us'], collect($fa->json('data.items'))->pluck('handle')->all());

        $en = $this->getJson('/api/catalog/products?q=pubg&page=1&perPage=20');
        $en->assertOk();
        $this->assertSame(['ps-us'], collect($en->json('data.items'))->pluck('handle')->all());
    }

    public function test_hidden_product_stays_out_even_if_meili_returns_it(): void
    {
        $group = $this->giftGroup();
        $this->makeCard($group, 'xbox-us', 'Xbox US', 'xbox', 'US', 40000);
        $hidden = $this->makeCard($group, 'xbox-hidden', 'Xbox Hidden', 'xbox', 'US', 30000);
        $hidden->visible = false;
        $hidden->save();

        $this->mock(CatalogTextIdResolver::class, function ($mock) {
            $mock->shouldReceive('handles')->andReturn(['xbox-us', 'xbox-hidden']);
        });

        $res = $this->getJson('/api/catalog/products?q=Xbox&page=1&perPage=20');
        $res->assertOk();
        $this->assertSame(['xbox-us'], collect($res->json('data.items'))->pluck('handle')->all());
    }

    public function test_q_combines_with_category_platform_and_region(): void
    {
        $group = $this->giftGroup();
        $this->makeCard($group, 'xbox-us', 'Xbox US', 'xbox', 'US', 40000);
        $this->makeCard($group, 'xbox-uk', 'Xbox UK', 'xbox', 'UK', 45000);
        $this->makeCard($group, 'ps-us', 'PlayStation US', 'playstation', 'US', 50000);

        $this->mock(CatalogTextIdResolver::class, function ($mock) {
            $mock->shouldReceive('handles')
                ->once()
                ->with('Xbox', 'gift-card')
                ->andReturn(['xbox-us', 'xbox-uk', 'ps-us']);
        });

        $res = $this->getJson('/api/catalog/products?category=gift-card&q=Xbox&platform=xbox&region=US&page=1&perPage=50');
        $res->assertOk();
        $this->assertSame(['xbox-us'], collect($res->json('data.items'))->pluck('handle')->all());
    }

    public function test_pagination_and_price_sort_stay_on_mysql(): void
    {
        $group = $this->giftGroup();
        $this->makeCard($group, 'card-a', 'Card A', 'xbox', 'US', 90000);
        $this->makeCard($group, 'card-b', 'Card B', 'xbox', 'US', 40000);
        $this->makeCard($group, 'card-c', 'Card C', 'xbox', 'US', 70000);

        $this->mock(CatalogTextIdResolver::class, function ($mock) {
            $mock->shouldReceive('handles')->andReturn(['card-a', 'card-b', 'card-c']);
        });

        $page1 = $this->getJson('/api/catalog/products?category=gift-card&q=Card&sort=price&page=1&perPage=2');
        $page1->assertOk();
        $this->assertSame(['card-b', 'card-c'], collect($page1->json('data.items'))->pluck('handle')->all());
        $this->assertSame(3, $page1->json('data.meta.total'));
        $this->assertSame(2, $page1->json('data.meta.totalPages'));

        $page2 = $this->getJson('/api/catalog/products?category=gift-card&q=Card&sort=price&page=2&perPage=2');
        $page2->assertOk();
        $this->assertSame(['card-a'], collect($page2->json('data.items'))->pluck('handle')->all());
    }

    public function test_meili_failure_falls_back_to_mysql_like(): void
    {
        $group = $this->giftGroup();
        $this->makeCard($group, 'xbox-us', 'Xbox US', 'xbox', 'US', 40000);
        $this->makeCard($group, 'ps-us', 'PlayStation US', 'playstation', 'US', 50000);

        $this->mock(CatalogTextIdResolver::class, function ($mock) {
            $mock->shouldReceive('handles')->andReturn(null);
        });

        $res = $this->getJson('/api/catalog/products?category=gift-card&q=Xbox&page=1&perPage=50');
        $res->assertOk();
        $this->assertSame(['xbox-us'], collect($res->json('data.items'))->pluck('handle')->all());
    }

    private function giftGroup(): CatalogGroup
    {
        return CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            [
                'name' => 'Gift cards',
                'title' => 'گیفت کارت',
                'visible' => true,
                'sort_order' => 20,
            ],
        );
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

<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogStorefrontExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_list_and_detail_share_is_storefront_excluded(): void
    {
        $group = $this->topupGroup();
        $this->makeProduct($group, 'c3-visible-cod', 'کالاف تست', FazerCardsIds::TOPUP);
        $this->makeProduct($group, 'manual-call-of-duty-mobile', 'COD Manual', FazerCardsIds::TOPUP);
        $this->makeProduct($group, 'c3-cod-boost', 'COD Boost', FazerCardsIds::MANUAL);
        $hidden = $this->makeProduct($group, 'c3-old-manual-boost', 'Old Manual', FazerCardsIds::TOPUP);
        $hidden->excluded_reason = 'manual';
        $hidden->save();

        $list = $this->getJson('/api/catalog/products?category=game-topup&page=1&perPage=100&sort=new');
        $list->assertOk();
        $handles = collect($list->json('data.items'))->pluck('handle')->all();
        $this->assertContains('c3-visible-cod', $handles);
        $this->assertNotContains('manual-call-of-duty-mobile', $handles);
        $this->assertNotContains('c3-cod-boost', $handles);
        $this->assertNotContains('c3-old-manual-boost', $handles);

        $this->getJson('/api/catalog/products/c3-visible-cod')->assertOk();
        $this->getJson('/api/catalog/products/manual-call-of-duty-mobile')->assertNotFound();
        $this->getJson('/api/catalog/products/c3-cod-boost')->assertNotFound();
        $this->getJson('/api/catalog/products/c3-old-manual-boost')->assertNotFound();
    }

    public function test_products_can_exclude_steam_gifts_category(): void
    {
        $topup = $this->topupGroup();
        $gifts = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
        $this->makeProduct($topup, 'c3-main-pubg', 'پابجی تست', FazerCardsIds::TOPUP);
        $this->makeProduct($gifts, 'steam-gift-9000001', 'Some Game', FazerCardsIds::STEAM_GIFT);

        $main = $this->getJson('/api/catalog/products?page=1&perPage=100&sort=new&excludeCategory=steam-gifts');
        $main->assertOk();
        $mainHandles = collect($main->json('data.items'))->pluck('handle')->all();
        $this->assertContains('c3-main-pubg', $mainHandles);
        $this->assertNotContains('steam-gift-9000001', $mainHandles);

        $steam = $this->getJson('/api/catalog/products?page=1&perPage=100&sort=new&category=steam-gifts');
        $steam->assertOk();
        $this->assertContains('steam-gift-9000001', collect($steam->json('data.items'))->pluck('handle')->all());
        $this->assertNotEmpty(
            collect($steam->json('data.items'))->firstWhere('handle', 'steam-gift-9000001')['updatedAt'] ?? null,
        );
    }

    private function topupGroup(): CatalogGroup
    {
        return CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'Game top-up', 'title' => 'شارژ بازی', 'visible' => true, 'sort_order' => 10],
        );
    }

    private function makeProduct(CatalogGroup $group, string $handle, string $title, string $family): Product
    {
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $title,
            'publisher' => '',
            'category_name' => $group->title,
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => $family,
            'fazercards_category_id' => $family === FazerCardsIds::STEAM_GIFT ? $family.':123' : $handle,
            'inputs' => [],
            'sort_order' => 10,
        ]);
        Sku::query()->create([
            'product_id' => $product->id,
            'code' => $handle.'-1',
            'name' => '1',
            'price' => 10000,
            'cost_usd' => '1',
            'status' => 'active',
            'visible' => true,
            'fulfillment_type' => $family,
            'sort_order' => 10,
        ]);

        return $product;
    }
}

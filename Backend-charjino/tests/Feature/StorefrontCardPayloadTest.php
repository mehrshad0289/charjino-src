<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCardPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_listing_omits_detail_fields(): void
    {
        $this->seed();
        Product::query()->where('handle', 'pubg-mobile')->update(['is_popular' => true]);
        $home = $this->getJson('/api/catalog/home')->assertOk();
        $card = $home->json('data.popular.0');
        $this->assertIsArray($card);
        $this->assertSame(['id', 'slug', 'name', 'image', 'price', 'badge'], array_keys($card));
        $this->assertArrayNotHasKey('descriptionHtml', $card);
        $this->assertArrayNotHasKey('purchaseSchema', $card);
        $this->assertArrayNotHasKey('searchTags', $card);
        $this->assertArrayNotHasKey('subtitle', $card);
        $this->assertArrayNotHasKey('shortDescription', $card);
        $this->assertArrayNotHasKey('handle', $card);
        $this->assertArrayNotHasKey('minPrice', $card);
        $group = $home->json('data.groups.0');
        $this->assertIsArray($group);
        $this->assertArrayNotHasKey('products', $group);
        $this->assertArrayNotHasKey('description', $group);
        $intro = (string) $home->json('data.introHtml');
        $this->assertStringContainsString('یوسی پابجی', $intro);
        $this->assertStringContainsString('سی‌پی کالاف دیوتی', $intro);
        $this->assertStringContainsString('گیفت کارت گوگل پلی', $intro);
        $this->assertStringContainsString('گیفت کارت استیم', $intro);
        $this->assertStringContainsString('الماس فری فایر', $intro);
    }

    public function test_categories_list_omits_purchase_guide(): void
    {
        $this->seed();
        $list = $this->getJson('/api/catalog/categories')->assertOk();
        $row = $list->json('data.0');
        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('purchaseGuide', $row);
        $this->assertArrayNotHasKey('purchaseGuideItems', $row);
        $this->assertArrayHasKey('slug', $row);
        $this->assertArrayHasKey('title', $row);
        $slugs = collect($list->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains('google-play'));
        $this->assertTrue($slugs->contains('call-of-duty-mobile'));
        $this->getJson('/api/catalog/categories/google-play')->assertOk();
    }

    public function test_product_prices_endpoint_returns_live_toman(): void
    {
        $this->seed();
        $detail = $this->getJson('/api/catalog/products/pubg-mobile')->assertOk();
        $prices = $this->getJson('/api/catalog/products/pubg-mobile/prices')->assertOk();
        $this->assertSame($detail->json('data.minPrice'), $prices->json('data.minPrice'));
        $this->assertNotEmpty($prices->json('data.skus'));
        $this->assertArrayHasKey('price', $prices->json('data.skus.0'));
        $this->assertArrayNotHasKey('name', $prices->json('data.skus.0'));
        $this->assertArrayHasKey('buyGuide', $detail->json('data'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\CatalogGroup;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Sku;
use App\Modules\Catalog\HomeSections;
use App\Modules\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pubg_skus_expose_toman_only(): void
    {
        $this->seed();

        $detail = $this->getJson('/api/catalog/products/pubg-mobile');
        $detail->assertOk()
            ->assertJsonPath('data.handle', 'pubg-mobile')
            ->assertJsonPath('data.title', 'پابجی موبایل')
            ->assertJsonMissingPath('data.skus.0.costUsd')
            ->assertJsonMissingPath('data.skus.0.liveCostUsd');

        $this->assertSame(99000, $detail->json('data.minPrice'));
        $this->assertNotEmpty($detail->json('data.category.slug'));
        $this->assertCount(3, $detail->json('data.skus'));
        $this->assertSame('۶۰ یوسی', $detail->json('data.skus.0.name'));
        $this->assertSame('۳۲۵ یوسی', $detail->json('data.skus.1.name'));
        $this->assertSame('۶۶۰ یوسی', $detail->json('data.skus.2.name'));
        foreach ($detail->json('data.skus') as $sku) {
            $this->assertIsInt($sku['price']);
            $this->assertGreaterThan(0, $sku['price']);
            $this->assertArrayNotHasKey('costUsd', $sku);
        }
    }

    public function test_single_product_category_uses_group_title_and_toman_min_price(): void
    {
        $this->seed();

        $group = CatalogGroup::query()->create([
            'name' => 'Telegram',
            'title' => 'Telegram',
            'slug' => 'telegram-1103',
            'description' => 'Telegram Stars and Premium topup',
            'visible' => true,
            'sort_order' => 50,
        ]);

        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'direct-topup-1736',
            'code' => 's2t-1736',
            'title' => 'Direct Topup',
            'publisher' => 'Shop2TopUp',
            'category_name' => 'شارژ بازی',
            'subtitle' => 'شارژ',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 10,
            'inputs' => ['playerId'],
        ]);

        Sku::query()->create([
            'product_id' => $product->id,
            'code' => 'stars-50',
            'name' => '50 Stars',
            'price' => 225600,
            'cost_usd' => '0.847953',
            'status' => 'active',
            'visible' => true,
            'sort_order' => 10,
        ]);

        $category = $this->getJson('/api/catalog/categories/telegram-1103');
        $category->assertOk()
            ->assertJsonPath('data.category.title', 'Telegram')
            ->assertJsonPath('data.category.productsCount', 1)
            ->assertJsonPath('data.products.0.title', 'Telegram')
            ->assertJsonPath('data.products.0.publisher', '')
            ->assertJsonPath('data.products.0.minPrice', 226000)
            ->assertJsonPath('data.meta.total', 1);

        $detail = $this->getJson('/api/catalog/products/direct-topup-1736');
        $detail->assertOk()
            ->assertJsonPath('data.title', 'Telegram')
            ->assertJsonPath('data.publisher', '')
            ->assertJsonPath('data.minPrice', 226000)
            ->assertJsonPath('data.skus.0.price', 226000)
            ->assertJsonMissingPath('data.skus.0.costUsd');
    }

    public function test_generic_charge_group_shows_original_name(): void
    {
        $this->seed();

        CatalogGroup::query()->create([
            'name' => 'Age of Empires Mobile',
            'title' => 'شارژ بازی',
            'slug' => 'age-of-empires-mobile-268',
            'visible' => true,
            'sort_order' => 80,
        ]);

        $listed = $this->getJson('/api/catalog/categories');
        $listed->assertOk();
        $row = collect($listed->json('data'))->firstWhere('slug', 'age-of-empires-mobile-268');
        $this->assertIsArray($row);
        $this->assertSame('Age of Empires Mobile', $row['title']);
    }

    public function test_expired_sku_sale_is_not_sold_on_storefront(): void
    {
        $this->seed();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->price = 99000;
        $sku->sale_price = 50000;
        $sku->sale_ends_at = now()->subDay();
        $sku->save();

        $detail = $this->getJson('/api/catalog/products/pubg-mobile');
        $detail->assertOk();
        $row = collect($detail->json('data.skus'))->firstWhere('id', $sku->id);
        $this->assertSame(99000, $row['price']);
        $this->assertNull($row['originalPrice']);
        $this->assertSame(99000, $detail->json('data.minPrice'));
    }

    public function test_fx_sell_price_rounds_to_thousands(): void
    {
        $this->seed();
        Setting::putValue('usd_toman_rate', '100000');
        Setting::putValue('margin_percent', '5');
        $pricing = $this->app->make(PricingService::class);

        $this->assertSame(220000, $pricing->sellToman('2.096'));
        $this->assertSame(127000, $pricing->sellToman('1.21'));
        $this->assertSame(105000, $pricing->sellToman('1'));
    }

    public function test_extreme_sale_is_not_sold_and_flash_item_is_hidden(): void
    {
        $this->seed();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->price = 127100;
        $sku->sale_price = 12000;
        $sku->sale_ends_at = now()->addDay();
        $sku->save();

        FlashSaleItem::query()->create([
            'sku_id' => $sku->id,
            'image' => '/image/catalog/pubg-mobile.svg',
            'original_price' => 127100,
            'price' => 12000,
            'product_code' => $sku->product->code,
            'product_handle' => $sku->product->handle,
            'product_name' => $sku->product->title,
            'product_variant_code' => $sku->code,
            'product_variant_name' => $sku->name,
            'visible' => true,
            'sort_order' => 1,
        ]);

        $detail = $this->getJson('/api/catalog/products/pubg-mobile');
        $detail->assertOk();
        $row = collect($detail->json('data.skus'))->firstWhere('id', $sku->id);
        $this->assertSame(127000, $row['price']);
        $this->assertNull($row['originalPrice']);

        $home = $this->getJson('/api/catalog/home')->assertOk();
        $handles = collect($home->json('data.flashSale'))->pluck('slug');
        $this->assertFalse($handles->contains($sku->product->handle));
    }

    public function test_reasonable_flash_sale_appears_on_home(): void
    {
        $this->seed();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->price = 257100;
        $sku->sale_price = 220000;
        $sku->sale_ends_at = now()->addDay();
        $sku->save();

        $home = $this->getJson('/api/catalog/home')->assertOk();
        $row = collect($home->json('data.flashSale'))->firstWhere('slug', $sku->product->handle);
        $this->assertIsArray($row);
        $this->assertSame(257000, $row['badge']['originalPrice']);
        $this->assertSame(220000, $row['price']);
        $this->assertNotEmpty($home->json('data.flashSaleEndsAt'));
    }

    public function test_sku_sale_still_appears_if_flash_row_was_removed(): void
    {
        $this->seed();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->price = 257100;
        $sku->sale_price = 220000;
        $sku->sale_ends_at = now()->addDay();
        $sku->save();

        FlashSaleItem::query()->where('sku_id', $sku->id)->get()->each->delete();

        $home = $this->getJson('/api/catalog/home')->assertOk();
        $row = collect($home->json('data.flashSale'))->firstWhere('slug', $sku->product->handle);
        $this->assertIsArray($row);
        $this->assertSame(220000, $row['price']);
    }

    public function test_hidden_flash_item_is_not_listed_on_home(): void
    {
        $this->seed();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->price = 257100;
        $sku->sale_price = 220000;
        $sku->sale_ends_at = now()->addDay();
        $sku->save();

        $item = FlashSaleItem::query()->where('sku_id', $sku->id)->firstOrFail();
        $item->visible = false;
        $item->save();

        $home = $this->getJson('/api/catalog/home')->assertOk();
        $handles = collect($home->json('data.flashSale'))->pluck('slug');
        $this->assertFalse($handles->contains($sku->product->handle));
    }

    public function test_storefront_thumbnail_strips_local_storage_host(): void
    {
        $this->seed();
        $product = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $product->image_url = 'http://127.0.0.1:3000/storage/catalog/demo-cover.png';
        $product->save();

        $this->getJson('/api/catalog/products/pubg-mobile')
            ->assertOk()
            ->assertJsonPath('data.thumbnail', '/storage/catalog/demo-cover.png');
    }

    public function test_steam_gift_fallback_cover_uses_steam_header(): void
    {
        $this->seed();
        $product = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $product->fulfillment_type = 'steam-gift';
        $product->fazercards_category_id = 'steam-gift:730';
        $product->thumbnail = '/image/catalog/pubg-mobile.svg';
        $product->image_url = null;
        $product->save();

        $this->getJson('/api/catalog/products/pubg-mobile')
            ->assertOk()
            ->assertJsonPath(
                'data.thumbnail',
                'https://cdn.cloudflare.steamstatic.com/steam/apps/730/header.jpg',
            );
    }

    public function test_telegram_and_relative_fazer_cover_on_storefront(): void
    {
        $this->seed();
        $product = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $product->fulfillment_type = 'telegram-stars';
        $product->fazercards_category_id = 'telegram-stars:stars';
        $product->thumbnail = '/image/catalog/pubg-mobile.svg';
        $product->image_url = null;
        $product->save();

        $this->getJson('/api/catalog/products/pubg-mobile')
            ->assertOk()
            ->assertJsonPath('data.thumbnail', '/image/catalog/telegram.svg');

        $product->fulfillment_type = 'topup';
        $product->fazercards_category_id = 'blood_strike';
        $product->thumbnail = '/covers/blood.jpg';
        $product->image_url = '/covers/blood.jpg';
        $product->save();

        $this->getJson('/api/catalog/products/pubg-mobile')
            ->assertOk()
            ->assertJsonPath('data.thumbnail', 'https://api.fzr.cards/covers/blood.jpg');
    }

    public function test_home_banner_storage_src_is_same_origin(): void
    {
        $this->seed();
        $banner = Banner::query()->create([
            'src' => 'https://api.charjino.ir/storage/banners/hero.jpg',
            'title' => 'بنر خانه',
            'href' => '/',
            'description' => 'اسلاید',
            'visible' => true,
            'sort_order' => 99,
        ]);

        $home = $this->getJson('/api/catalog/home')->assertOk();
        $src = collect($home->json('data.banners'))->firstWhere('id', $banner->id)['src'] ?? '';
        $this->assertMatchesRegularExpression('#^/storage/banners/hero\.jpg\?v=\d+$#', $src);
    }

    public function test_home_caps_products_per_group(): void
    {
        $this->seed();
        config(['charjino.supplier.fazercards_home_per_group' => 4]);
        $pubg = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $groupId = (int) $pubg->catalog_group_id;
        for ($i = 0; $i < 20; $i++) {
            $product = Product::query()->create([
                'catalog_group_id' => $groupId,
                'handle' => 'home-extra-'.$i,
                'code' => 'home-extra-'.$i,
                'title' => 'اضافه '.$i,
                'publisher' => 'Test',
                'category_name' => 'شارژ بازی',
                'subtitle' => 'تست',
                'thumbnail' => '/image/catalog/pubg-mobile.svg',
                'visible' => true,
                'status' => 'active',
                'sort_order' => 100 + $i,
            ]);
            Sku::query()->create([
                'product_id' => $product->id,
                'code' => 'home-extra-sku-'.$i,
                'name' => 'بسته',
                'price' => 10000,
                'status' => 'active',
                'visible' => true,
            ]);
        }

        $home = $this->getJson('/api/catalog/home')->assertOk();
        $group = collect($home->json('data.groups'))->firstWhere('id', $groupId);
        $this->assertIsArray($group);
        $this->assertArrayNotHasKey('products', $group);
    }

    public function test_home_sections_honor_enabled_limit_and_manual_products(): void
    {
        $this->seed();
        $pubg = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $groupId = (int) $pubg->catalog_group_id;
        $other = CatalogGroup::query()->create([
            'name' => 'Gift cards',
            'title' => 'گیفت کارت',
            'slug' => 'gift-card-home',
            'visible' => true,
            'sort_order' => 5,
        ]);
        $picked = Product::query()->create([
            'catalog_group_id' => $other->id,
            'handle' => 'home-picked-card',
            'code' => 'home-picked-card',
            'title' => 'کارت انتخابی',
            'publisher' => 'Test',
            'category_name' => 'گیفت کارت',
            'subtitle' => 'تست',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 1,
        ]);
        Sku::query()->create([
            'product_id' => $picked->id,
            'code' => 'home-picked-sku',
            'name' => 'بسته',
            'price' => 10000,
            'status' => 'active',
            'visible' => true,
        ]);

        HomeSections::store([
            ['groupId' => $groupId, 'enabled' => false, 'limit' => 12, 'productIds' => []],
            ['groupId' => $other->id, 'enabled' => true, 'limit' => 1, 'productIds' => [$picked->id]],
        ]);

        $home = $this->getJson('/api/catalog/home')->assertOk();
        $slugs = collect($home->json('data.groups'))->pluck('slug')->all();
        $this->assertNotContains(CatalogGroup::query()->find($groupId)?->slug, $slugs);
        $row = collect($home->json('data.groups'))->firstWhere('slug', 'gift-card-home');
        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('products', $row);
        $this->assertArrayNotHasKey('description', $row);
    }

    public function test_catalog_list_filters_by_toman_range(): void
    {
        $this->seed();

        $group = CatalogGroup::query()->where('slug', 'game-topup')->firstOrFail();
        $expensive = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'price-range-high',
            'code' => 'price-range-high',
            'title' => 'بسته گران',
            'publisher' => 'Test',
            'category_name' => 'شارژ بازی',
            'subtitle' => 'تست',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 1,
        ]);
        Sku::query()->create([
            'product_id' => $expensive->id,
            'code' => 'price-range-high-sku',
            'name' => 'بسته',
            'price' => 500000,
            'status' => 'active',
            'visible' => true,
        ]);

        $cheap = $this->getJson('/api/catalog/products?category=game-topup&page=1&perPage=50&maxPrice=100000')
            ->assertOk();
        $cheapHandles = collect($cheap->json('data.items'))->pluck('handle');
        $this->assertTrue($cheapHandles->contains('pubg-mobile'));
        $this->assertFalse($cheapHandles->contains('price-range-high'));

        $high = $this->getJson('/api/catalog/products?category=game-topup&page=1&perPage=50&minPrice=200000')
            ->assertOk();
        $highHandles = collect($high->json('data.items'))->pluck('handle');
        $this->assertTrue($highHandles->contains('price-range-high'));
        $this->assertFalse($highHandles->contains('pubg-mobile'));
    }

    public function test_storefront_orders_newest_steam_appid_and_listed_rank(): void
    {
        $this->seed();

        $steam = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 60],
        );
        $cards = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'Gift cards', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );

        $this->sellable($steam->id, 'steam-old', 'قدیمی', 'steam-gift', 'steam-gift:10', ['appid' => 10]);
        $this->sellable($steam->id, 'steam-new', 'جدید', 'steam-gift', 'steam-gift:900', ['appid' => 900]);
        $this->sellable($cards->id, 'card-later', 'بعدی', 'giftcard', 'giftcard:later', ['listedRank' => 4]);
        $this->sellable($cards->id, 'card-newest', 'تازه', 'giftcard', 'giftcard:new', ['listedRank' => 0]);

        $steamList = $this->getJson('/api/catalog/products?category=steam-gifts&page=1&perPage=10')->assertOk();
        $this->assertSame(
            ['steam-new', 'steam-old'],
            collect($steamList->json('data.items'))->pluck('handle')->all(),
        );

        $cardList = $this->getJson('/api/catalog/products?category=gift-card&page=1&perPage=10')->assertOk();
        $this->assertSame('card-newest', $cardList->json('data.items.0.handle'));
    }

    /**
     * @param  array<string, mixed>  $requirements
     */
    private function sellable(
        int $groupId,
        string $handle,
        string $title,
        string $family,
        string $categoryId,
        array $requirements,
    ): void {
        $product = Product::query()->create([
            'catalog_group_id' => $groupId,
            'handle' => $handle,
            'code' => $handle,
            'title' => $title,
            'publisher' => 'Test',
            'category_name' => 'تست',
            'subtitle' => 'تست',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => $family,
            'fazercards_category_id' => $categoryId,
            'requirements' => $requirements,
            'sort_order' => 1,
        ]);
        Sku::query()->create([
            'product_id' => $product->id,
            'code' => $handle.'-sku',
            'name' => $title,
            'price' => 10000,
            'status' => 'active',
            'visible' => true,
            'fulfillment_type' => $family,
        ]);
    }
}

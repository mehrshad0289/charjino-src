<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\CatalogOrigin;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobProductQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorobProductMapperTest extends TestCase
{
    use RefreshDatabase;

    private TorobProductMapper $mapper;

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
            'torob.split_by_sku.canonical_path' => storage_path('framework/testing/torob-canonical-mapper.json'),
            'torob.split_by_sku.runtime_path' => storage_path('framework/testing/torob-runtime-mapper.json'),
            'torob.split_by_sku.baseline_path' => storage_path('framework/testing/torob-baseline-mapper.json'),
        ]);
        @unlink(storage_path('framework/testing/torob-canonical-mapper.json'));
        @unlink(storage_path('framework/testing/torob-runtime-mapper.json'));
        @unlink(storage_path('framework/testing/torob-baseline-mapper.json'));
        $this->mapper = $this->app->make(TorobProductMapper::class);
    }

    public function test_in_stock_product_exposes_min_sell_price(): void
    {
        $product = $this->makeProduct([
            'title' => 'گیفت استیم',
            'handle' => 'steam-wallet-in-stock',
            'image_url' => 'https://cdn.example.com/cover.jpg',
            'platform' => 'Steam',
            'region' => 'Global',
            'seo_description' => 'شرح کوتاه',
        ], [
            ['price' => 150000, 'status' => 'active'],
            ['price' => 99000, 'status' => 'active'],
            ['price' => 50000, 'status' => 'hidden'],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));

        $this->assertSame((string) $product->id, $row['page_unique']);
        $this->assertSame('https://charjino.ir/catalog/steam-wallet-in-stock', $row['page_url']);
        $this->assertSame('گیفت استیم', $row['title']);
        $this->assertSame(99000, $row['current_price']);
        $this->assertTrue($row['availability']);
        $this->assertSame('شارژینو', $row['category_name']);
        $this->assertSame('نو', $row['spec']['condition']);
        $this->assertSame(['https://cdn.example.com/cover.jpg'], $row['image_links']);
        $this->assertSame($product->storefrontPlatform(), $row['spec']['platform']);
        $this->assertSame($product->storefrontRegion(), $row['spec']['region']);
        $this->assertSame('شرح کوتاه', $row['short_desc']);
        $this->assertArrayNotHasKey('guarantee', $row);
        $this->assertArrayNotHasKey('old_price', $row);
        $this->assertStringContainsString('T', $row['date_added']);
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $row['date_updated']);
    }

    public function test_unavailable_product_has_zero_price(): void
    {
        $product = $this->makeProduct([
            'handle' => 'oos-product',
            'visible' => false,
            'unavailable_at' => now(),
        ], [
            ['price' => 88000, 'status' => 'active'],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));

        $this->assertFalse($product->isOrderable());
        $this->assertFalse($row['availability']);
        $this->assertSame(0, $row['current_price']);
        $this->assertArrayNotHasKey('old_price', $row);
    }

    public function test_valid_sale_sets_old_price(): void
    {
        $product = $this->makeProduct([
            'handle' => 'sale-valid',
        ], [
            [
                'price' => 257100,
                'sale_price' => 220000,
                'sale_ends_at' => now()->addDay(),
                'status' => 'active',
            ],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));
        $sku = $product->skus->first();

        $this->assertTrue($sku->hasActiveSale());
        $this->assertSame($sku->sellPrice(), $row['current_price']);
        $this->assertSame(220000, $row['current_price']);
        $this->assertSame($sku->listPrice(), $row['old_price']);
        $this->assertTrue($row['availability']);
    }

    public function test_invalid_sale_uses_list_price(): void
    {
        $product = $this->makeProduct([
            'handle' => 'sale-invalid',
        ], [
            [
                'price' => 127100,
                'sale_price' => 12000,
                'sale_ends_at' => now()->addDay(),
                'status' => 'active',
            ],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));
        $sku = $product->skus->first();

        $this->assertFalse($sku->hasActiveSale());
        $this->assertSame($sku->listPrice(), $row['current_price']);
        $this->assertSame(127000, $row['current_price']);
        $this->assertArrayNotHasKey('old_price', $row);
        $this->assertTrue($row['availability']);
    }

    public function test_charjino_own_product_is_mapped(): void
    {
        $product = $this->makeProduct([
            'handle' => 'charjino-own-wallet',
            'title' => 'شارژ اختصاصی شارژینو',
            'fazercards_category_id' => null,
            'shop2_category_id' => null,
        ], [
            ['price' => 50000, 'status' => 'active'],
        ]);

        $this->assertSame(CatalogOrigin::MANUAL, CatalogOrigin::product($product));
        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));
        $this->assertSame('شارژ اختصاصی شارژینو', $row['title']);
        $this->assertSame('https://charjino.ir/catalog/charjino-own-wallet', $row['page_url']);
        $this->assertSame(50000, $row['current_price']);
        $this->assertTrue($row['availability']);
    }

    public function test_deleted_product_520_is_absent_from_uniques(): void
    {
        $this->makeProduct([
            'handle' => 'placeholder-for-group',
        ], [
            ['price' => 10000, 'status' => 'active'],
        ]);
        $gone = new Product;
        $gone->id = 520;
        $gone->forceFill([
            'catalog_group_id' => CatalogGroup::query()->where('slug', 'torob-group')->value('id'),
            'handle' => 'deleted-520-id',
            'code' => 'deleted-520-id',
            'title' => 'حذف‌شده',
            'publisher' => '',
            'subtitle' => '',
            'thumbnail' => '',
            'category_name' => 'شارژینو',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 10,
            'inputs' => [],
        ]);
        $gone->save();
        $this->assertSame(520, $gone->id);
        Sku::query()->create([
            'product_id' => 520,
            'code' => 'deleted-520-sku',
            'name' => 'بسته',
            'price' => 10000,
            'status' => 'active',
            'visible' => true,
            'sort_order' => 1,
        ]);
        $gone->delete();

        $payload = $this->app->make(TorobProductQueryService::class)->byUniques(['520']);

        $this->assertFalse(Product::query()->where('id', 520)->exists());
        $this->assertSame(0, $payload['total']);
        $this->assertSame([], $payload['products']);
    }

    public function test_torob_price_and_availability_match_product_page(): void
    {
        $product = $this->makeProduct([
            'handle' => 'match-product-page',
            'title' => 'محصول تطبیق صفحه',
        ], [
            [
                'price' => 257100,
                'sale_price' => 220000,
                'sale_ends_at' => now()->addDay(),
                'status' => 'active',
                'sort_order' => 2,
            ],
            [
                'price' => 99000,
                'status' => 'active',
                'sort_order' => 1,
            ],
        ]);

        $page = $this->getJson('/api/catalog/products/match-product-page')->assertOk();
        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));

        $minPrice = (int) $page->json('data.minPrice');
        $orderable = (bool) $page->json('data.isOrderable');
        $skuPrices = collect($page->json('data.skus'))
            ->pluck('price')
            ->map(fn ($price) => (int) $price)
            ->filter(fn (int $price) => $price > 0)
            ->values();

        $this->assertTrue($orderable);
        $this->assertSame($minPrice, $skuPrices->min());
        $this->assertSame($orderable && $minPrice > 0, $row['availability']);
        $this->assertSame($minPrice, $row['current_price']);
        $this->assertSame($minPrice, 99000);
    }

    public function test_missing_image_and_spec_are_empty(): void
    {
        $product = $this->makeProduct([
            'handle' => 'no-image',
            'image_url' => null,
            'platform' => null,
            'region' => null,
            'seo_description' => null,
        ], [
            ['price' => 11000, 'status' => 'active'],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));
        $this->assertSame([], $row['image_links']);
        $this->assertSame('نو', $row['spec']['condition']);
        $this->assertArrayNotHasKey('platform', $row['spec']);
        $this->assertArrayNotHasKey('short_desc', $row);
    }

    public function test_steam_gift_raw_title_gets_prefix(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
        $product = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'The Bench',
            'name_en' => 'The Bench',
            'handle' => 'steam-gift-999001',
        ], [
            ['price' => 120000, 'status' => 'active'],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));

        $this->assertSame('گیفت استیم The Bench', $row['title']);
        $this->assertSame('گیفت کارت / استیم / بازی کامپیوتری', $row['subtitle']);
        $this->assertStringContainsString('گیفت کارت / استیم', (string) $row['category_name']);
    }

    public function test_steam_gift_prefixed_title_is_not_doubled(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
        $product = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'گیفت استیم Fantasy Aquarium',
            'handle' => 'steam-gift-999002',
        ], [
            ['price' => 120000, 'status' => 'active'],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));

        $this->assertSame('گیفت استیم Fantasy Aquarium', $row['title']);
    }

    public function test_steam_gift_dlc_and_soundtrack_titles(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
        $dlc = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'Cities: Skylines DLC',
            'handle' => 'steam-gift-dlc-1',
        ], [
            ['price' => 90000, 'status' => 'active'],
        ]);
        $ost = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'Hollow Knight Soundtrack',
            'handle' => 'steam-gift-ost-1',
        ], [
            ['price' => 80000, 'status' => 'active'],
        ]);

        $dlcRow = $this->mapper->toArray($dlc->fresh(['skus', 'catalogGroup']));
        $ostRow = $this->mapper->toArray($ost->fresh(['skus', 'catalogGroup']));

        $this->assertSame('گیفت استیم DLC Cities: Skylines', $dlcRow['title']);
        $this->assertStringContainsString('افزودنی', (string) $dlcRow['subtitle']);
        $this->assertSame('گیفت استیم موسیقی متن Hollow Knight', $ostRow['title']);
        $this->assertStringContainsString('موسیقی متن', (string) $ostRow['subtitle']);
    }

    public function test_steam_gift_doubled_persian_prefix_is_collapsed(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
        $product = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'گیفت استیم   گیفت استیم  Dota 2',
            'handle' => 'steam-gift-dota-dup',
        ], [
            ['price' => 120000, 'status' => 'active'],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));

        $this->assertSame('گیفت استیم Dota 2', $row['title']);
        $this->assertSame('گیفت استیم   گیفت استیم  Dota 2', $product->fresh()->title);
    }

    public function test_steam_gift_prefixed_dota_title_stays_single_prefix(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
        $product = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'گیفت استیم Dota 2',
            'handle' => 'steam-gift-dota-ok',
        ], [
            ['price' => 120000, 'status' => 'active'],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));

        $this->assertSame('گیفت استیم Dota 2', $row['title']);
    }

    public function test_torob_mapping_does_not_mutate_product_record(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
        $product = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'Raw English Game',
            'handle' => 'steam-gift-mutation-check',
        ], [
            ['price' => 50000, 'status' => 'active'],
        ]);
        $beforeTitle = $product->title;
        $beforeId = (string) $product->id;

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));

        $this->assertSame('گیفت استیم Raw English Game', $row['title']);
        $this->assertSame($beforeId, $row['page_unique']);
        $this->assertSame('https://charjino.ir/catalog/steam-gift-mutation-check', $row['page_url']);
        $this->assertSame($beforeTitle, $product->fresh()->title);
    }

    public function test_split_sku_rows_keep_canonical_unique_for_five_usd(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $skus = [];
        foreach ([5, 10, 15, 20, 25, 30, 50, 100, 250, 500] as $index => $amount) {
            $skus[] = [
                'name' => $amount.' USD',
                'price' => $amount === 5 ? 1459000 : $amount * 100000,
                'fazercards_offer_id' => $amount.'_usd',
                'status' => 'active',
                'sort_order' => $index + 1,
            ];
        }
        $product = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'گیفت کارت Rewarble دلار آمریکا (USD)',
            'handle' => 'giftcard-rewarble-usd',
        ], $skus);
        config([
            'torob.split_by_sku_product_ids' => [$product->id],
            'torob.split_by_sku.enabled_product_ids' => [$product->id],
        ]);
        $fresh = $product->fresh(['skus', 'catalogGroup']);
        $five = $fresh->skus->firstWhere('fazercards_offer_id', '5_usd');
        $this->assertNotNull($five);

        $rows = $this->mapper->toRows($fresh);
        $this->assertCount(10, $rows);
        $this->assertSame((string) $fresh->id, $rows[0]['page_unique']);
        $this->assertSame('گیفت کارت Rewarble 5 دلار آمریکا (USD)', $rows[0]['title']);
        $this->assertSame('https://charjino.ir/catalog/giftcard-rewarble-usd?sku='.$five->id, $rows[0]['page_url']);
        $this->assertSame((string) $fresh->id, $rows[0]['product_group_id']);
        $this->assertSame(1459000, $rows[0]['current_price']);
        $this->assertTrue($rows[0]['availability']);
        $this->assertSame($fresh->id.'_'.$fresh->skus->firstWhere('fazercards_offer_id', '10_usd')->id, $rows[1]['page_unique']);
        $this->assertSame('گیفت کارت Rewarble 10 دلار آمریکا (USD)', $rows[1]['title']);
        $this->assertSame('گیفت کارت / دیجیتال', $rows[0]['category_name']);
        $this->assertSame('نو', $rows[0]['spec']['condition']);
        $this->assertCount(10, collect($rows)->pluck('page_unique')->unique());
        $this->assertCount(10, collect($rows)->pluck('page_url')->unique());

        $plain = $this->makeProduct(['handle' => 'plain-other', 'title' => 'گیفت استیم'], [
            ['price' => 88000, 'status' => 'active'],
        ]);
        $plainRows = $this->mapper->toRows($plain->fresh(['skus', 'catalogGroup']));
        $this->assertCount(1, $plainRows);
        $this->assertArrayNotHasKey('product_group_id', $plainRows[0]);
        $this->assertSame((string) $plain->id, $plainRows[0]['page_unique']);
        $this->assertSame('https://charjino.ir/catalog/plain-other', $plainRows[0]['page_url']);
    }

    public function test_gift_card_raw_title_gets_prefix(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'Gift cards', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'Google Play (US)',
            'handle' => 'giftcard-google-play-us-torob',
        ], [
            ['price' => 500000, 'status' => 'active'],
        ]);

        $row = $this->mapper->toArray($product->fresh(['skus', 'catalogGroup']));

        $this->assertSame('گیفت کارت Google Play (US)', $row['title']);
        $this->assertSame('گیفت کارت / دیجیتال', $row['subtitle']);
    }

    public function test_split_gift_card_title_uses_persian_brand_region_and_amount(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'Google Play (US)',
            'handle' => 'giftcard-google-play-us',
        ], [
            ['name' => '10 USD', 'price' => 200000, 'fazercards_offer_id' => '10_usd', 'status' => 'active', 'sort_order' => 1],
            ['name' => '25 USD', 'price' => 400000, 'fazercards_offer_id' => '25_usd', 'status' => 'active', 'sort_order' => 2],
        ]);
        config([
            'torob.split_by_sku.enabled_product_ids' => [$product->id],
            'torob.split_by_sku_product_ids' => [$product->id],
        ]);
        $fresh = $product->fresh(['skus', 'catalogGroup']);
        $rows = $this->mapper->toRows($fresh);

        $this->assertSame('گیفت کارت گوگل پلی آمریکا 10 دلار (US)', $rows[0]['title']);
        $this->assertSame('گیفت کارت گوگل پلی آمریکا 25 دلار (US)', $rows[1]['title']);
        $this->assertTrue(str_starts_with($rows[0]['title'], 'گیفت کارت'));
        $this->assertNotSame('گیفت کارت Google Play (US)', $rows[0]['title']);
    }

    public function test_unsplit_and_rewarble_usd_split_titles_stay_legacy(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $unsplit = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'Google Play (US)',
            'handle' => 'giftcard-google-play-us-unsplit',
        ], [
            ['name' => '10 USD', 'price' => 200000, 'status' => 'active'],
        ]);
        $this->assertSame(
            'گیفت کارت Google Play (US)',
            $this->mapper->toRows($unsplit->fresh(['skus', 'catalogGroup']))[0]['title'],
        );

        $rewarble = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'گیفت کارت Rewarble دلار آمریکا (USD)',
            'handle' => 'giftcard-rewarble-usd',
        ], [
            ['name' => '5 USD', 'price' => 1459000, 'fazercards_offer_id' => '5_usd', 'status' => 'active', 'sort_order' => 1],
            ['name' => '10 USD', 'price' => 2919000, 'fazercards_offer_id' => '10_usd', 'status' => 'active', 'sort_order' => 2],
        ]);
        config([
            'torob.split_by_sku.enabled_product_ids' => [$rewarble->id],
            'torob.split_by_sku_product_ids' => [$rewarble->id],
        ]);
        $rows = $this->mapper->toRows($rewarble->fresh(['skus', 'catalogGroup']));
        $this->assertSame('گیفت کارت Rewarble 5 دلار آمریکا (USD)', $rows[0]['title']);
        $this->assertSame('گیفت کارت Rewarble 10 دلار آمریکا (USD)', $rows[1]['title']);
    }

    public function test_split_titles_cover_persian_brands_regions_and_currencies(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $cases = [
            ['giftcard-rewarble-eur', 'Rewarble (EUR)', '5 EUR', '5_eur', 'گیفت کارت ریواربل اروپا 5 یورو (EU)'],
            ['giftcard-razer-gold-tr', 'Razer Gold (TR)', '5 TRY', '5_try', 'گیفت کارت ریزر گلد ترکیه 5 لیر (TR)'],
            ['giftcard-google-play-uk', 'Google Play (UK)', '25 GBP', '25_gbp', 'گیفت کارت گوگل پلی انگلیس 25 پوند (UK)'],
            ['giftcard-app-store-itunes-us', 'App Store & iTunes (US)', '10 USD', '10_usd', 'گیفت کارت اپل آیتونز آمریکا 10 دلار (US)'],
            ['giftcard-playstation-us', 'PlayStation (US)', '50 USD', '50_usd', 'گیفت کارت پلی استیشن آمریکا 50 دلار (US)'],
            ['giftcard-xbox-us', 'Xbox (US)', '25 USD', '25_usd', 'گیفت کارت ایکس باکس آمریکا 25 دلار (US)'],
            ['giftcard-steam-wallet-us', 'Steam Wallet (US)', '20 USD', '20_usd', 'گیفت کارت استیم آمریکا 20 دلار (US)'],
            ['giftcard-amazon-de', 'Amazon (DE)', '15 EUR', '15_eur', 'گیفت کارت آمازون آلمان 15 یورو (DE)'],
            ['giftcard-netflix-eu', 'Netflix (EU)', '25 EUR', '25_eur', 'گیفت کارت نتفلیکس اروپا 25 یورو (EU)'],
            ['giftcard-razer-gold-ca', 'Razer Gold (CA)', '10 CAD', '10_cad', 'گیفت کارت ریزر گلد کانادا 10 دلار (CA)'],
            ['giftcard-xbox-za', 'Xbox (ZA)', '50 ZAR', '50_zar', 'گیفت کارت ایکس باکس آفریقای جنوبی 50 راند (ZA)'],
            ['giftcard-playstation-hk', 'PlayStation (HK)', '50 HKD', '50_hkd', 'گیفت کارت پلی استیشن هنگ‌کنگ 50 دلار (HK)'],
            ['giftcard-steam-cis', 'Steam (CIS)', '5 USD', '5_usd', 'گیفت کارت استیم سی‌آی‌اس 5 دلار (CIS)'],
        ];
        $ids = [];
        foreach ($cases as $index => [$handle, $title, $skuName, $offer, $expected]) {
            $product = $this->makeProduct([
                'catalog_group_id' => $group->id,
                'title' => $title,
                'handle' => $handle,
            ], [
                ['name' => $skuName, 'price' => 100000, 'fazercards_offer_id' => $offer, 'status' => 'active', 'sort_order' => 1],
                ['name' => '99 USD', 'price' => 200000, 'fazercards_offer_id' => '99_usd', 'status' => 'active', 'sort_order' => 2],
            ]);
            $ids[] = $product->id;
            $cases[$index][] = $product->id;
        }
        config([
            'torob.split_by_sku.enabled_product_ids' => $ids,
            'torob.split_by_sku_product_ids' => $ids,
        ]);
        foreach ($cases as [$handle, $title, $skuName, $offer, $expected, $id]) {
            $product = Product::query()->with(['skus', 'catalogGroup'])->findOrFail($id);
            $rows = $this->mapper->toRows($product);
            $this->assertSame($expected, $rows[0]['title'], $handle);
            $this->assertTrue(str_starts_with($rows[0]['title'], 'گیفت کارت'), $handle);
            $this->assertMatchesRegularExpression('/^[\p{Arabic}0-9\s\x{200C}\x{200D}]+(?:\s\([A-Z]{2,8}\))?$/u', $rows[0]['title'], $handle);
        }
    }

    public function test_split_row_titles_are_unique_and_keep_trailing_region_code(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = $this->makeProduct([
            'catalog_group_id' => $group->id,
            'title' => 'Google Play (US)',
            'handle' => 'giftcard-google-play-us',
        ], [
            ['name' => '10 USD', 'price' => 200000, 'fazercards_offer_id' => '10_usd', 'status' => 'active', 'sort_order' => 1],
            ['name' => '25 USD', 'price' => 400000, 'fazercards_offer_id' => '25_usd', 'status' => 'active', 'sort_order' => 2],
            ['name' => '50 USD', 'price' => 700000, 'fazercards_offer_id' => '50_usd', 'status' => 'active', 'sort_order' => 3],
        ]);
        config([
            'torob.split_by_sku.enabled_product_ids' => [$product->id],
            'torob.split_by_sku_product_ids' => [$product->id],
        ]);
        $rows = $this->mapper->toRows($product->fresh(['skus', 'catalogGroup']));
        $titles = array_column($rows, 'title');
        $this->assertCount(3, $titles);
        $this->assertCount(3, array_unique($titles));
        foreach ($titles as $title) {
            $this->assertMatchesRegularExpression('/^[\p{Arabic}0-9\s\x{200C}\x{200D}]+ \(US\)$/u', $title);
        }
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  list<array<string, mixed>>  $skus
     */
    private function makeProduct(array $product, array $skus): Product
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'torob-group'],
            ['name' => 'شارژینو', 'title' => 'شارژینو', 'visible' => true, 'sort_order' => 1],
        );

        $created = Product::query()->create(array_merge([
            'catalog_group_id' => $group->id,
            'handle' => 'torob-product',
            'code' => $product['handle'] ?? 'torob-product',
            'title' => 'محصول ترب',
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
                'code' => ($created->handle ?? 'sku').'-'.$index,
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

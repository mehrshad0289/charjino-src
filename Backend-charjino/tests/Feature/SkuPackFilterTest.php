<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkuPackFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_steam_gift_exposes_region_and_edition_filters(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'گیفت استیم', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 40],
        );
        $product = $this->makeProduct($group, 'pack-filter-cs2', 'Counter-Strike 2', FazerCardsIds::STEAM_GIFT, [
            'type' => FazerCardsIds::STEAM_GIFT,
        ]);
        $this->addSku($product, 'std-us', 'Standard (US)', 'Standard', 'US', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'std-eu', 'Standard (EU)', 'Standard', 'EU', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'std-ru', 'Standard (RU)', 'Standard', 'RU', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'dlx-us', 'Deluxe (US)', 'Deluxe', 'US', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'dlx-eu', 'Deluxe (EU)', 'Deluxe', 'EU', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'dlx-ru', 'Deluxe (RU)', 'Deluxe', 'RU', FazerCardsIds::STEAM_GIFT);

        $detail = $this->getJson('/api/catalog/products/pack-filter-cs2');
        $detail->assertOk();
        $keys = collect($detail->json('data.packFilters'))->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['region', 'edition'], $keys);
        $regions = collect($detail->json('data.packFilters'))->firstWhere('key', 'region')['options'];
        $this->assertEqualsCanonicalizing(['EU', 'RU', 'US'], collect($regions)->pluck('value')->all());
        $editions = collect($detail->json('data.packFilters'))->firstWhere('key', 'edition')['options'];
        $this->assertEqualsCanonicalizing(['Deluxe', 'Standard'], collect($editions)->pluck('value')->all());
        $this->assertSame('US', $detail->json('data.skus.0.filters.region'));
        $this->assertSame('Standard', $detail->json('data.skus.0.filters.edition'));
    }

    public function test_gift_card_filters_by_denomination_not_single_region(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = $this->makeProduct($group, 'pack-filter-ps-us', 'PlayStation US', FazerCardsIds::GIFTCARD, [
            'type' => FazerCardsIds::GIFTCARD,
        ], 'playstation', 'US');
        foreach (['۱۰ دلار' => '10 USD', '۲۵ دلار' => '25 USD', '۵۰ دلار' => '50 USD', '۱۰۰ دلار' => '100 USD'] as $fa => $en) {
            $this->addSku($product, 'ps-'.preg_replace('/\D/', '', $en), $fa, $en, 'US', FazerCardsIds::GIFTCARD);
        }

        $detail = $this->getJson('/api/catalog/products/pack-filter-ps-us');
        $detail->assertOk();
        $keys = collect($detail->json('data.packFilters'))->pluck('key')->all();
        $this->assertSame(['denomination'], $keys);
        $packs = collect($detail->json('data.packFilters.0.options'))->pluck('value')->all();
        $this->assertSame(['10-usd', '25-usd', '50-usd', '100-usd'], $packs);
        $this->assertSame('۱۰ دلار', $detail->json('data.packFilters.0.options.0.label'));
    }

    public function test_trademark_in_edition_does_not_break_json(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'گیفت استیم', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 40],
        );
        $product = $this->makeProduct($group, 'steam-gift-3244220', 'A Game About Digging A Hole', FazerCardsIds::STEAM_GIFT, [
            'type' => FazerCardsIds::STEAM_GIFT,
        ]);
        $this->addSku($product, 'hole-us', 'A Game About Digging A Hole™ (US)', 'A Game About Digging A Hole™', 'US', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'hole-eu', 'A Game About Digging A Hole™ (EU)', 'A Game About Digging A Hole™', 'EU', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'hole-ru', 'A Game About Digging A Hole™ (RU)', 'A Game About Digging A Hole™', 'RU', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'hole-dlx-us', 'A Game About Digging A Hole™ Deluxe (US)', 'A Game About Digging A Hole™ Deluxe', 'US', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'hole-dlx-eu', 'A Game About Digging A Hole™ Deluxe (EU)', 'A Game About Digging A Hole™ Deluxe', 'EU', FazerCardsIds::STEAM_GIFT);
        $this->addSku($product, 'hole-dlx-ru', 'A Game About Digging A Hole™ Deluxe (RU)', 'A Game About Digging A Hole™ Deluxe', 'RU', FazerCardsIds::STEAM_GIFT);

        $detail = $this->getJson('/api/catalog/products/steam-gift-3244220');
        $detail->assertOk();
        $this->assertTrue(mb_check_encoding((string) $detail->getContent(), 'UTF-8'));
        $this->assertSame('A Game About Digging A Hole', $detail->json('data.title'));
        $editions = collect($detail->json('data.skus'))
            ->pluck('filters.edition')
            ->filter(fn ($edition) => is_string($edition) && $edition !== '')
            ->unique()
            ->values()
            ->all();
        foreach ($editions as $edition) {
            $this->assertTrue(mb_check_encoding($edition, 'UTF-8'), bin2hex($edition));
        }
        $this->assertContains('Deluxe', $editions);
    }

    public function test_trailing_dot_and_trademark_sku_json_is_valid(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'گیفت استیم', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 40],
        );
        $product = $this->makeProduct($group, 'steam-gift-3354750', 'skate.', FazerCardsIds::STEAM_GIFT, [
            'type' => FazerCardsIds::STEAM_GIFT,
        ]);
        foreach (['US', 'EU', 'RU', 'AR'] as $region) {
            $this->addSku(
                $product,
                'skate-'.$region,
                'skate.™ - Welcome Pack ('.$region.')',
                'skate.™ - Welcome Pack',
                $region,
                FazerCardsIds::STEAM_GIFT,
            );
        }
        foreach (['US', 'EU'] as $region) {
            $this->addSku(
                $product,
                'skate-dlx-'.$region,
                'skate.™ - Welcome Deluxe Pack ('.$region.')',
                'skate.™ - Welcome Deluxe Pack',
                $region,
                FazerCardsIds::STEAM_GIFT,
            );
        }

        $detail = $this->getJson('/api/catalog/products/steam-gift-3354750');
        $detail->assertOk();
        $this->assertTrue(mb_check_encoding((string) $detail->getContent(), 'UTF-8'));
        $this->assertSame('skate.', $detail->json('data.title'));
        $editions = collect($detail->json('data.skus'))->pluck('filters.edition')->filter()->unique()->values()->all();
        foreach ($editions as $edition) {
            $this->assertTrue(mb_check_encoding($edition, 'UTF-8'), bin2hex($edition));
        }
        $this->assertContains('Welcome Pack', $editions);
        $this->assertContains('Welcome Deluxe Pack', $editions);
    }

    public function test_few_skus_do_not_expose_pack_filters(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'telegram'],
            ['name' => 'تلگرام', 'title' => 'تلگرام', 'visible' => true, 'sort_order' => 50],
        );
        $product = $this->makeProduct($group, 'pack-filter-tg-premium', 'Telegram Premium', FazerCardsIds::TELEGRAM_PREMIUM, [
            'type' => FazerCardsIds::TELEGRAM_PREMIUM,
        ]);
        $this->addSku($product, 'tg-3', '3 months', '3 months', null, FazerCardsIds::TELEGRAM_PREMIUM);
        $this->addSku($product, 'tg-6', '6 months', '6 months', null, FazerCardsIds::TELEGRAM_PREMIUM);
        $this->addSku($product, 'tg-12', '12 months', '12 months', null, FazerCardsIds::TELEGRAM_PREMIUM);

        $detail = $this->getJson('/api/catalog/products/pack-filter-tg-premium');
        $detail->assertOk();
        $this->assertSame([], $detail->json('data.packFilters'));
        $this->assertArrayNotHasKey('filters', $detail->json('data.skus.0'));
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function makeProduct(
        CatalogGroup $group,
        string $handle,
        string $title,
        string $family,
        array $schema,
        string $platform = '',
        ?string $region = null,
    ): Product {
        return Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $title,
            'name_en' => $title,
            'publisher' => '',
            'category_name' => $group->title,
            'subtitle' => 'بسته',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => $family,
            'platform' => $platform,
            'region' => $region,
            'inputs' => [],
            'purchase_schema' => $schema,
            'sort_order' => 10,
        ]);
    }

    private function addSku(
        Product $product,
        string $code,
        string $name,
        string $nameEn,
        ?string $region,
        string $family,
    ): Sku {
        return Sku::query()->create([
            'product_id' => $product->id,
            'code' => $code,
            'name' => $name,
            'name_en' => $nameEn,
            'price' => 10000,
            'cost_usd' => '1',
            'status' => 'active',
            'visible' => true,
            'fulfillment_type' => $family,
            'region' => $region,
            'sort_order' => 10,
        ]);
    }
}

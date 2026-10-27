<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\ProductSupplierIdentity;
use App\Models\Sku;
use App\Modules\Catalog\FazerCardsCatalogSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FazerCardsListedShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);
        config(['charjino.supplier.fazercards_listed_shop_max' => 1]);
    }

    public function test_giftcards_sync_stores_every_fazer_category(): void
    {
        config(['charjino.supplier.fazercards_listed_shop_max' => 1]);
        $this->fakeListed([
            'giftcards' => [
                ['category_id' => 'steam', 'name' => 'Steam', 'imageurl' => 'https://cdn.example/steam.jpg'],
                ['category_id' => 'xbox', 'name' => 'Xbox', 'imageurl' => 'https://cdn.example/xbox.jpg'],
                ['category_id' => 'playstation', 'name' => 'PlayStation', 'imageurl' => 'https://cdn.example/ps.jpg'],
            ],
            'giftcards/cards' => [
                'steam' => [
                    ['card_id' => 'steam-10', 'name' => 'Steam 10', 'price_usd' => '10', 'stock' => 4],
                ],
                'xbox' => [
                    ['card_id' => 'xbox-10', 'name' => 'Xbox 10', 'price_usd' => '10', 'stock' => 3],
                ],
                'playstation' => [
                    ['card_id' => 'ps-10', 'name' => 'PS 10', 'price_usd' => '10', 'stock' => 2],
                ],
            ],
        ]);

        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('giftcards');
        $this->assertSame('completed', $result['status']);
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'giftcard:steam')->first());
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'giftcard:xbox')->first());
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'giftcard:playstation')->first());
        $this->assertSame(3, Product::query()->where('fulfillment_type', 'giftcard')->count());
        $this->assertSame(3, Sku::query()->where('fulfillment_type', 'giftcard')->where('status', 'active')->count());
    }

    public function test_giftcards_sync_ingests_newest_and_keeps_unsynced_visible(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'Gift cards', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $kept = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'itunes-old',
            'code' => 'itunes-old',
            'title' => 'آیتونز',
            'publisher' => 'Apple',
            'category_name' => 'گیفت کارت',
            'subtitle' => 'کد گیفت',
            'fazercards_category_id' => 'giftcard:itunes',
            'fulfillment_type' => 'giftcard',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 10,
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
        ]);
        $keptSku = Sku::query()->create([
            'product_id' => $kept->id,
            'code' => 'giftcard-itunes-10',
            'name' => 'iTunes 10',
            'fazercards_category_id' => 'giftcard:itunes',
            'fazercards_offer_id' => 'itunes-10',
            'fulfillment_type' => 'giftcard',
            'price' => 100000,
            'cost_usd' => '10',
            'status' => 'active',
            'visible' => true,
            'sort_order' => 10,
        ]);

        $this->fakeListed([
            'giftcards' => [
                ['category_id' => 'itunes', 'name' => 'iTunes', 'imageurl' => 'https://cdn.example/itunes.jpg'],
                ['category_id' => 'xbox', 'name' => 'Xbox', 'imageurl' => 'https://cdn.example/xbox.jpg'],
            ],
            'giftcards/cards' => [
                'itunes' => [
                    ['card_id' => 'itunes-10', 'name' => 'iTunes 10', 'price_usd' => '10', 'stock' => 3],
                ],
                'xbox' => [
                    ['card_id' => 'xbox-10', 'name' => 'Xbox 10', 'price_usd' => '10', 'stock' => 2],
                    ['card_id' => 'xbox-gone', 'name' => 'Gone', 'price_usd' => '25', 'stock' => 0],
                ],
            ],
        ]);

        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('giftcards');
        $this->assertSame('completed', $result['status']);
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'giftcard:xbox')->first());
        $this->assertSame('xbox', Product::query()->where('fazercards_category_id', 'giftcard:xbox')->value('platform'));
        $this->assertNull(Product::query()->where('fazercards_category_id', 'giftcard:itunes')->where('id', '!=', $kept->id)->first());
        $kept->refresh();
        $keptSku->refresh();
        $this->assertTrue((bool) $kept->visible);
        $this->assertSame('آیتونز', $kept->title);
        $this->assertSame('active', $keptSku->status);
        $this->assertTrue((bool) $keptSku->visible);
        $this->assertNull(Sku::query()->where('fazercards_offer_id', 'xbox-gone')->first());
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/giftcards/cards') && str_contains((string) $request->url(), 'category_id=xbox'));
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/giftcards/cards') && str_contains((string) $request->url(), 'category_id=itunes'));
    }

    public function test_gamekeys_sync_picks_highest_appid_and_home_shows_it_first(): void
    {
        config(['charjino.supplier.fazercards_listed_shop_max' => 2]);
        config(['charjino.supplier.fazercards_home_per_group' => 12]);
        $this->fakeListed([
            'gamekeys' => [
                ['game_id' => 'old-key', 'name' => 'Old Key', 'appid' => 10, 'imageurl' => 'https://cdn.example/old.jpg'],
                ['game_id' => 'new-key', 'name' => 'New Key', 'appid' => 99999, 'imageurl' => 'https://cdn.example/new.jpg'],
            ],
            'gamekeys/keys' => [
                'old-key' => [
                    ['key_id' => 'old-std', 'name' => 'Standard', 'price_usd' => '20', 'stock' => 4],
                ],
                'new-key' => [
                    ['key_id' => 'new-std', 'name' => 'Standard', 'price_usd' => '30', 'stock' => 4],
                ],
            ],
        ]);

        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('gamekeys');
        $this->assertSame('completed', $result['status']);
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'gamekey:new-key')->first());
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'gamekey:old-key')->first());

        config(['charjino.supplier.fazercards_listed_shop_max' => 1]);
        $this->app->make(FazerCardsCatalogSync::class)->sync('gamekeys');
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'gamekey:old-key')->first());

        $home = $this->getJson('/api/catalog/home');
        $home->assertOk();
        $row = collect($home->json('data.groups'))->firstWhere('slug', 'game-keys');
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('products', $row);
    }

    public function test_gamekeys_sync_takes_newest_per_platform_and_hides_the_rest(): void
    {
        config(['charjino.supplier.fazercards_gamekeys_per_platform' => 1]);
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-keys'],
            ['name' => 'کلید بازی', 'title' => 'کلید بازی', 'visible' => true, 'sort_order' => 30],
        );
        $stale = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'stale-steam',
            'code' => 'stale-steam',
            'title' => 'Stale Steam',
            'publisher' => '',
            'category_name' => 'کلید بازی',
            'subtitle' => 'کلید',
            'fazercards_category_id' => 'gamekey:stale-steam',
            'fulfillment_type' => 'gamekey',
            'platform' => 'steam',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 10,
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
        ]);
        $this->fakeListed([
            'gamekeys' => [
                ['game_id' => 'steam-old', 'name' => 'Steam Old', 'appid' => 10, 'platform' => 'Steam'],
                ['game_id' => 'steam-new', 'name' => 'Steam New', 'appid' => 900, 'platform' => 'Steam'],
                ['game_id' => 'ea-old', 'name' => 'EA Old', 'appid' => 20, 'platform' => 'EA App'],
                ['game_id' => 'ea-new', 'name' => 'EA New', 'appid' => 80, 'platform' => 'EA'],
                ['game_id' => 'xbox-1', 'name' => 'Xbox One', 'platform' => 'Xbox'],
                ['game_id' => 'rockstar-1', 'name' => 'GTA Key', 'platform' => 'Rockstar'],
                ['game_id' => 'ps5-new', 'name' => 'PS5 New', 'platform' => 'PlayStation 5', 'region_restriction' => true],
                ['game_id' => 'ps4-old', 'name' => 'PS4 Old', 'platform' => 'PS4'],
            ],
            'gamekeys/keys' => [
                'steam-old' => [['key_id' => 'so', 'name' => 'Standard', 'price_usd' => '10', 'stock' => 4]],
                'steam-new' => [['key_id' => 'sn', 'name' => 'Standard', 'price_usd' => '11', 'stock' => 4]],
                'ea-old' => [['key_id' => 'eo', 'name' => 'Standard', 'price_usd' => '12', 'stock' => 4]],
                'ea-new' => [['key_id' => 'en', 'name' => 'Standard', 'price_usd' => '13', 'stock' => 4]],
                'xbox-1' => [['key_id' => 'xo', 'name' => 'Standard', 'price_usd' => '14', 'stock' => 4]],
                'rockstar-1' => [['key_id' => 'ro', 'name' => 'Standard', 'price_usd' => '15', 'stock' => 4]],
                'ps5-new' => [['key_id' => 'psn', 'name' => 'Standard', 'price_usd' => '16', 'stock' => 4]],
                'ps4-old' => [['key_id' => 'pso', 'name' => 'Standard', 'price_usd' => '9', 'stock' => 4]],
            ],
        ]);

        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('gamekeys');
        $this->assertSame('completed', $result['status']);

        $steamNew = Product::query()->where('fazercards_category_id', 'gamekey:steam-new')->first();
        $eaNew = Product::query()->where('fazercards_category_id', 'gamekey:ea-new')->first();
        $xbox = Product::query()->where('fazercards_category_id', 'gamekey:xbox-1')->first();
        $rockstar = Product::query()->where('fazercards_category_id', 'gamekey:rockstar-1')->first();
        $playstation = Product::query()->where('fazercards_category_id', 'gamekey:ps5-new')->first();
        $this->assertNotNull($steamNew);
        $this->assertNotNull($eaNew);
        $this->assertNotNull($xbox);
        $this->assertNotNull($rockstar);
        $this->assertNotNull($playstation);
        $this->assertSame('steam', $steamNew->platform);
        $this->assertSame('ea', $eaNew->platform);
        $this->assertSame('xbox', $xbox->platform);
        $this->assertSame('rockstar', $rockstar->platform);
        $this->assertSame('playstation', $playstation->platform);
        $this->assertTrue((bool) ($playstation->requirements['regionRestriction'] ?? false));
        $this->assertTrue((bool) $steamNew->visible);
        $this->assertTrue((bool) $eaNew->visible);
        $this->assertTrue((bool) $playstation->visible);
        $this->assertNull(Product::query()->where('fazercards_category_id', 'gamekey:steam-old')->first());
        $this->assertNull(Product::query()->where('fazercards_category_id', 'gamekey:ea-old')->first());
        $this->assertNull(Product::query()->where('fazercards_category_id', 'gamekey:ps4-old')->first());
        $stale->refresh();
        $this->assertFalse((bool) $stale->visible);
    }

    public function test_gamekeys_sync_keeps_shop_customization_and_updates_fazer_offers(): void
    {
        config(['charjino.supplier.fazercards_gamekeys_per_platform' => 1]);
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-keys'],
            ['name' => 'کلید بازی', 'title' => 'کلید بازی', 'visible' => true, 'sort_order' => 30],
        );
        $custom = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'steam-old-custom',
            'code' => 'steam-old-custom',
            'title' => 'عنوان فارسی ما',
            'name_en' => 'Our English Title',
            'publisher' => 'ناشر چارژینو',
            'category_name' => 'کلید بازی',
            'subtitle' => 'زیرعنوان ما',
            'description' => "توضیح سفارشی\nخط دوم",
            'fazercards_category_id' => 'gamekey:steam-old',
            'fulfillment_type' => 'gamekey',
            'platform' => 'steam',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 40,
            'is_popular' => true,
            'thumbnail' => '/storage/catalog/our-cover.png',
            'image_url' => '/storage/catalog/our-cover.png',
        ]);
        $customSku = Sku::query()->create([
            'product_id' => $custom->id,
            'code' => 'gamekey-steam-old-old-std',
            'name' => 'بسته سفارشی',
            'name_en' => 'Custom pack',
            'fazercards_category_id' => 'gamekey:steam-old',
            'fazercards_offer_id' => 'old-std',
            'fulfillment_type' => 'gamekey',
            'price' => 111000,
            'price_manual' => true,
            'cost_usd' => '20',
            'live_cost_usd' => '20',
            'status' => 'active',
            'visible' => true,
            'sort_order' => 7,
        ]);
        $hidden = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'ea-hidden',
            'code' => 'ea-hidden',
            'title' => 'مخفی ادمین',
            'publisher' => '',
            'category_name' => 'کلید بازی',
            'subtitle' => 'کلید',
            'fazercards_category_id' => 'gamekey:ea-keep',
            'fulfillment_type' => 'gamekey',
            'platform' => 'ea',
            'visible' => false,
            'status' => 'hidden',
            'sort_order' => 50,
            'thumbnail' => '/storage/catalog/hidden.png',
            'image_url' => '/storage/catalog/hidden.png',
        ]);
        $gone = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'gone-key',
            'code' => 'gone-key',
            'title' => 'حذف‌شده فیزر',
            'publisher' => '',
            'category_name' => 'کلید بازی',
            'subtitle' => 'کلید',
            'description' => 'این توضیح باید بماند',
            'fazercards_category_id' => 'gamekey:removed-id',
            'fulfillment_type' => 'gamekey',
            'platform' => 'steam',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 60,
            'thumbnail' => '/storage/catalog/gone.png',
            'image_url' => '/storage/catalog/gone.png',
        ]);

        $this->fakeListed([
            'gamekeys' => [
                ['game_id' => 'steam-old', 'name' => 'Steam Old From Fazer', 'appid' => 10, 'platform' => 'Steam', 'imageurl' => 'https://cdn.example/fazer-old.jpg'],
                ['game_id' => 'steam-new', 'name' => 'Steam New', 'appid' => 900, 'platform' => 'Steam', 'imageurl' => 'https://cdn.example/fazer-new.jpg'],
                ['game_id' => 'ea-keep', 'name' => 'EA Keep', 'appid' => 80, 'platform' => 'EA', 'imageurl' => 'https://cdn.example/ea.jpg'],
            ],
            'gamekeys/keys' => [
                'steam-old' => [
                    ['key_id' => 'old-std', 'name' => 'Standard', 'price_usd' => '28', 'stock' => 4],
                    ['key_id' => 'old-deluxe', 'name' => 'Deluxe', 'price_usd' => '40', 'stock' => 2],
                ],
                'steam-new' => [
                    ['key_id' => 'new-std', 'name' => 'Standard', 'price_usd' => '30', 'stock' => 4],
                ],
                'ea-keep' => [
                    ['key_id' => 'ea-std', 'name' => 'Standard', 'price_usd' => '12', 'stock' => 3],
                ],
            ],
        ]);
        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('gamekeys');
        $this->assertSame('completed', $result['status']);

        $custom->refresh();
        $customSku->refresh();
        $hidden->refresh();
        $gone->refresh();
        $this->assertTrue((bool) $custom->visible);
        $this->assertSame($custom->id, Product::query()->where('fazercards_category_id', 'gamekey:steam-old')->value('id'));
        $this->assertSame('عنوان فارسی ما', $custom->title);
        $this->assertSame('Our English Title', $custom->name_en);
        $this->assertSame('ناشر چارژینو', $custom->publisher);
        $this->assertSame('زیرعنوان ما', $custom->subtitle);
        $this->assertSame("توضیح سفارشی\nخط دوم", $custom->description);
        $this->assertSame('/storage/catalog/our-cover.png', $custom->thumbnail);
        $this->assertSame('/storage/catalog/our-cover.png', $custom->image_url);
        $this->assertTrue((bool) $custom->is_popular);
        $this->assertSame(40, (int) $custom->sort_order);
        $this->assertSame('بسته سفارشی', $customSku->name);
        $this->assertSame('Custom pack', $customSku->name_en);
        $this->assertSame(111000, (int) $customSku->price);
        $this->assertSame('28', (string) $customSku->live_cost_usd);
        $this->assertSame(7, (int) $customSku->sort_order);
        $this->assertNotNull(Sku::query()->where('fazercards_offer_id', 'old-deluxe')->first());
        $this->assertSame('hidden', $hidden->status);
        $this->assertFalse((bool) $hidden->visible);
        $this->assertSame('مخفی ادمین', $hidden->title);
        $this->assertSame('/storage/catalog/hidden.png', $hidden->image_url);
        $this->assertFalse((bool) $gone->visible);
        $this->assertSame('حذف‌شده فیزر', $gone->title);
        $this->assertSame('این توضیح باید بماند', $gone->description);
        $this->assertSame('/storage/catalog/gone.png', $gone->image_url);
        $added = Product::query()->where('fazercards_category_id', 'gamekey:steam-new')->first();
        $this->assertNotNull($added);
        $this->assertTrue((bool) $added->visible);
        $this->assertSame('https://cdn.example/fazer-new.jpg', $added->thumbnail);
    }

    public function test_alias_remote_id_updates_existing_normalized_key_instead_of_duplicating(): void
    {
        config(['charjino.supplier.fazercards_listed_shop_max' => 8]);
        config(['charjino.supplier.fazercards_gamekeys_per_platform' => 20]);
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-keys'],
            ['name' => 'کلید بازی', 'title' => 'کلید بازی', 'visible' => true, 'sort_order' => 30],
        );
        $existing = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'gamekey-warhammer-40000-space-marine-2-global',
            'code' => 'gamekey-warhammer-40000-space-marine-2-global',
            'title' => 'Warhammer 40000 Space Marine 2',
            'publisher' => '',
            'category_name' => 'کلید بازی',
            'subtitle' => 'کلید',
            'fazercards_category_id' => 'gamekey:warhammer-40000-space-marine-2-global',
            'fulfillment_type' => 'gamekey',
            'platform' => 'steam',
            'region' => 'global',
            'visible' => true,
            'status' => 'active',
            'sort_order' => 10,
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
        ]);
        $this->fakeListed([
            'gamekeys' => [
                [
                    'game_id' => 'warhammer-40-000-space-marine-2-global',
                    'name' => 'Warhammer 40000 Space Marine 2',
                    'platform' => 'Steam',
                    'region' => 'global',
                    'imageurl' => 'https://cdn.example/wh.jpg',
                ],
            ],
            'gamekeys/keys' => [
                'warhammer-40-000-space-marine-2-global' => [
                    ['key_id' => 'std', 'name' => 'Standard', 'price_usd' => '10', 'stock' => 4],
                ],
            ],
        ]);
        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('gamekeys');
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, Product::query()->where('handle', 'like', 'gamekey-warhammer-%space-marine-2-global')->count());
        $existing->refresh();
        $this->assertSame('gamekey-warhammer-40000-space-marine-2-global', $existing->handle);
        $this->assertSame('active', $existing->status);
        $this->assertTrue((bool) $existing->visible);
        $this->assertNotNull(
            ProductSupplierIdentity::query()
                ->where('product_id', $existing->id)
                ->where('remote_id', 'gamekey:warhammer-40-000-space-marine-2-global')
                ->first()
        );
    }

    public function test_synced_giftcard_still_hides_missing_offer(): void
    {
        config(['charjino.supplier.fazercards_listed_shop_max' => 8]);
        $this->fakeListed([
            'giftcards' => [
                ['category_id' => 'steam', 'name' => 'Steam', 'imageurl' => 'https://cdn.example/steam-card.jpg'],
            ],
            'giftcards/cards' => [
                'steam' => [
                    ['card_id' => 'steam-10', 'name' => 'Steam 10', 'price_usd' => '10', 'stock' => 4],
                    ['card_id' => 'steam-25', 'name' => 'Steam 25', 'price_usd' => '25', 'stock' => 2],
                ],
            ],
        ]);
        $this->app->make(FazerCardsCatalogSync::class)->sync('giftcards');
        $gone = Sku::query()->where('fazercards_offer_id', 'steam-25')->firstOrFail();
        $this->assertSame('active', $gone->status);

        $this->fakeListed([
            'giftcards' => [
                ['category_id' => 'steam', 'name' => 'Steam', 'imageurl' => 'https://cdn.example/steam-card.jpg'],
            ],
            'giftcards/cards' => [
                'steam' => [
                    ['card_id' => 'steam-10', 'name' => 'Steam 10', 'price_usd' => '10', 'stock' => 4],
                ],
            ],
        ]);
        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('giftcards');
        $this->assertGreaterThanOrEqual(1, $result['hiddenSkus']);
        $gone->refresh();
        $this->assertSame('hidden', $gone->status);
        $this->assertFalse((bool) $gone->visible);
    }

    /** @var array<string, mixed> */
    private array $listedCatalog = [];

    /**
     * @param  array<string, mixed>  $catalog
     */
    private function fakeListed(array $catalog): void
    {
        $this->listedCatalog = $catalog;
        Http::fake(function (Request $request) {
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($request->url(), PHP_URL_PATH)), '/');
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $catalog = $this->listedCatalog;
            if ($path === 'me') {
                return Http::response(['ok' => true, 'email' => 'ops@example.com', 'login' => 'lab', 'plan' => 'gold'], 200);
            }
            if ($path === 'balance') {
                return Http::response(['ok' => true, 'balance' => '12.0000'], 200);
            }
            if ($path === 'giftcards') {
                return Http::response([
                    'ok' => true,
                    'items' => $catalog['giftcards'] ?? [],
                    'meta' => ['has_more' => false],
                ], 200);
            }
            if ($path === 'giftcards/cards') {
                $id = (string) ($query['category_id'] ?? '');
                $offers = is_array($catalog['giftcards/cards'][$id] ?? null) ? $catalog['giftcards/cards'][$id] : [];

                return Http::response(['ok' => true, 'category_id' => $id, 'offers' => $offers], 200);
            }
            if ($path === 'gamekeys') {
                return Http::response([
                    'ok' => true,
                    'items' => $catalog['gamekeys'] ?? [],
                    'meta' => ['has_more' => false],
                ], 200);
            }
            if ($path === 'gamekeys/keys') {
                $id = (string) ($query['game_id'] ?? '');
                $keys = is_array($catalog['gamekeys/keys'][$id] ?? null) ? $catalog['gamekeys/keys'][$id] : [];

                return Http::response(['ok' => true, 'game_id' => $id, 'keys' => $keys], 200);
            }

            return Http::response(['ok' => true], 200);
        });
    }
}

<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameKeyCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_game_key_filters_facets_and_hides_iran_and_need_no_player_field(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-keys'],
            [
                'name' => 'کلید بازی',
                'title' => 'کلید بازی',
                'visible' => true,
                'sort_order' => 30,
            ],
        );
        $steamGlobal = $this->makeKey($group, 'elden-ring', 'Elden Ring', 'Steam', 'Global', 40000);
        $xboxEu = $this->makeKey($group, 'forza', 'Forza', 'Xbox', 'EU', 50000);
        $this->makeKey($group, 'iran-only', 'Local Title', 'Steam', 'Iran', 30000);
        $this->makeKey($group, 'fc-ea', 'FC 27 EA', 'EA', 'Global', 60000);
        $this->makeKey($group, 'gta-rgl', 'GTA V', 'Rockstar', 'Global', 70000);

        $list = $this->getJson('/api/catalog/products?category=game-keys&page=1&perPage=50');
        $list->assertOk();
        $handles = collect($list->json('data.items'))->pluck('handle')->all();
        $this->assertContains('elden-ring', $handles);
        $this->assertContains('forza', $handles);
        $this->assertContains('iran-only', $handles);
        $this->assertContains('fc-ea', $handles);
        $this->assertContains('gta-rgl', $handles);
        $this->assertSame(['xbox', 'pc'], $list->json('data.meta.platforms'));
        $this->assertSame(['EU', 'Global'], $list->json('data.meta.regions'));
        $iranRow = collect($list->json('data.items'))->firstWhere('handle', 'iran-only');
        $this->assertNull($iranRow['region'] ?? null);
        $this->assertSame('steam', $iranRow['platform'] ?? null);

        $pc = $this->getJson('/api/catalog/products?category=game-keys&page=1&perPage=50&platform=pc');
        $pc->assertOk();
        $this->assertEqualsCanonicalizing(
            ['elden-ring', 'iran-only', 'fc-ea', 'gta-rgl'],
            collect($pc->json('data.items'))->pluck('handle')->all(),
        );
        $this->assertSame(['xbox', 'pc'], $pc->json('data.meta.platforms'));

        $steam = $this->getJson('/api/catalog/products?category=game-keys&page=1&perPage=50&platform=Steam');
        $steam->assertOk();
        $this->assertEqualsCanonicalizing(
            ['elden-ring', 'iran-only'],
            collect($steam->json('data.items'))->pluck('handle')->all(),
        );
        $this->assertContains('xbox', $steam->json('data.meta.platforms'));
        $this->assertContains('pc', $steam->json('data.meta.platforms'));

        $eu = $this->getJson('/api/catalog/products?category=game-keys&page=1&perPage=50&region=EU');
        $eu->assertOk();
        $this->assertSame(['forza'], collect($eu->json('data.items'))->pluck('handle')->all());

        $iranFilter = $this->getJson('/api/catalog/products?category=game-keys&page=1&perPage=50&region=Iran');
        $iranFilter->assertOk();
        $this->assertGreaterThanOrEqual(3, count($iranFilter->json('data.items')));

        $detail = $this->getJson('/api/catalog/products/elden-ring');
        $detail->assertOk()
            ->assertJsonPath('data.platform', 'steam')
            ->assertJsonPath('data.region', 'Global')
            ->assertJsonPath('data.fulfillmentType', FazerCardsIds::GAMEKEY)
            ->assertJsonPath('data.skus.0.region', 'Global')
            ->assertJsonPath('data.skus.0.minOrderQuantity', 1)
            ->assertJsonPath('data.skus.0.maxOrderQuantity', 20);
        $this->assertSame([], $detail->json('data.inputFields'));
        $this->assertSame([], $detail->json('data.inputs'));
        $this->assertSame('game-keys', $detail->json('data.category.slug'));
        $this->assertNull($detail->json('data.category.purchaseGuide'));

        $admin = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $admin->assertOk();
        $token = (string) $admin->json('data.access_token');
        $adminList = $this->withToken($token)
            ->getJson('/api/admin/catalog/products?category='.$group->id.'&platform=Xbox&perPage=50');
        $adminList->assertOk();
        $this->assertSame(['forza'], collect($adminList->json('data.items'))->pluck('handle')->all());
        $this->assertContains('steam', $adminList->json('data.meta.platforms'));
        $this->assertContains('EU', $adminList->json('data.meta.regions'));
        $this->assertNotContains('Iran', $adminList->json('data.meta.regions') ?? []);

        $this->assertSame($steamGlobal->id, Product::query()->where('handle', 'elden-ring')->value('id'));
        $this->assertSame($xboxEu->id, Product::query()->where('handle', 'forza')->value('id'));
    }

    public function test_purchase_guide_is_saved_per_category_with_line_breaks(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-keys'],
            [
                'name' => 'کلید بازی',
                'title' => 'کلید بازی',
                'visible' => true,
                'sort_order' => 30,
            ],
        );
        $this->makeKey($group, 'guide-key', 'Guide Key', 'Steam', 'Global', 12000);
        Product::query()->where('handle', 'guide-key')->update([
            'description' => "خط اول توضیح\nخط دوم توضیح",
        ]);

        $admin = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $admin->assertOk();
        $token = (string) $admin->json('data.access_token');
        $save = $this->withToken($token)->putJson('/api/admin/catalog/groups/'.$group->id, [
            'purchaseGuide' => "۱. بازی را انتخاب کن\r\n۲. پرداخت کن\n۳. کلید می‌آید",
        ]);
        $save->assertOk()
            ->assertJsonPath('data.purchaseGuide', "۱. بازی را انتخاب کن\n۲. پرداخت کن\n۳. کلید می‌آید")
            ->assertJsonPath('data.purchaseGuideItems.0.type', 'text')
            ->assertJsonPath('data.purchaseGuideItems.0.body', "۱. بازی را انتخاب کن\n۲. پرداخت کن\n۳. کلید می‌آید");

        $detail = $this->getJson('/api/catalog/products/guide-key');
        $detail->assertOk()
            ->assertJsonPath('data.category.purchaseGuide', "۱. بازی را انتخاب کن\n۲. پرداخت کن\n۳. کلید می‌آید")
            ->assertJsonPath('data.category.purchaseGuideItems.0.type', 'text')
            ->assertJsonPath('data.description', "خط اول توضیح\nخط دوم توضیح");
    }

    public function test_purchase_guide_items_are_slides_not_a_dump(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-keys'],
            [
                'name' => 'کلید بازی',
                'title' => 'کلید بازی',
                'visible' => true,
                'sort_order' => 30,
            ],
        );
        $this->makeKey($group, 'guide-slides', 'Guide Slides', 'Steam', 'Global', 12000);

        $admin = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $admin->assertOk();
        $token = (string) $admin->json('data.access_token');
        $this->withToken($token)->putJson('/api/admin/catalog/groups/'.$group->id, [
            'purchaseGuideItems' => [
                ['type' => 'text', 'title' => 'قدم اول', 'body' => 'بسته را انتخاب کن.'],
                ['type' => 'image', 'src' => '/storage/guides/step.jpg', 'caption' => 'نمونه تصویر'],
                ['type' => 'video', 'src' => '/storage/guides/step.mp4'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.purchaseGuideItems.0.type', 'text')
            ->assertJsonPath('data.purchaseGuideItems.1.type', 'image')
            ->assertJsonPath('data.purchaseGuideItems.2.type', 'video')
            ->assertJsonPath('data.purchaseGuide', "قدم اول\nبسته را انتخاب کن.");

        $this->getJson('/api/catalog/products/guide-slides')
            ->assertOk()
            ->assertJsonPath('data.category.purchaseGuideItems.1.src', '/storage/guides/step.jpg')
            ->assertJsonPath('data.category.purchaseGuideItems.2.src', '/storage/guides/step.mp4');
    }

    public function test_game_key_order_uses_quantity_without_player_id(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-keys'],
            [
                'name' => 'کلید بازی',
                'title' => 'کلید بازی',
                'visible' => true,
                'sort_order' => 30,
            ],
        );
        $product = $this->makeKey($group, 'key-qty', 'Qty Key', 'Steam', 'Global', 10000, 2, 5);
        $sku = $product->skus()->firstOrFail();

        $this->postJson('/api/auth/request-otp', ['phoneNumber' => '09123456789'])->assertOk();
        $verify = $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => '09123456789',
            'code' => '123456',
        ]);
        $verify->assertOk();
        $token = (string) $verify->json('data.access_token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $ok = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 3,
            'requirements' => [],
        ], $headers + ['Idempotency-Key' => 'gk-qty-11111111-1111-1111-1111-111111111111']);
        $ok->assertOk()
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.amountToman', 30000);

        $low = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => [],
        ], $headers + ['Idempotency-Key' => 'gk-qty-22222222-2222-2222-2222-222222222222']);
        $low->assertStatus(400)->assertJsonPath('error.code', 'INVALID_QUANTITY');

        $high = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 6,
            'requirements' => [],
        ], $headers + ['Idempotency-Key' => 'gk-qty-33333333-3333-3333-3333-333333333333']);
        $high->assertStatus(400)->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }

    private function makeKey(
        CatalogGroup $group,
        string $handle,
        string $title,
        string $platform,
        string $region,
        int $price,
        int $minQty = 1,
        int $maxQty = 20,
    ): Product {
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $title,
            'publisher' => '',
            'category_name' => $group->title,
            'subtitle' => 'کلید',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => FazerCardsIds::GAMEKEY,
            'platform' => $platform,
            'region' => $region,
            'inputs' => [],
            'sort_order' => 10,
        ]);
        Sku::query()->create([
            'product_id' => $product->id,
            'code' => $handle.'-std',
            'name' => 'Standard',
            'price' => $price,
            'cost_usd' => '1',
            'status' => 'active',
            'visible' => true,
            'fulfillment_type' => FazerCardsIds::GAMEKEY,
            'region' => $region,
            'min_order_quantity' => $minQty,
            'max_order_quantity' => $maxQty,
            'sort_order' => 10,
        ]);

        return $product->load('skus');
    }
}

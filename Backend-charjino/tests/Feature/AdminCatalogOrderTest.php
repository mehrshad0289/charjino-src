<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCatalogOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_manage_groups_products_skus_and_orders_from_api(): void
    {
        $token = $this->adminToken();

        $create = $this->withToken($token)->postJson('/api/admin/catalog/groups', [
            'title' => 'دسته دستی',
            'slug' => 'manual-cat',
            'visible' => true,
            'sortOrder' => 90,
        ]);
        $create->assertOk()->assertJsonPath('data.slug', 'manual-cat')->assertJsonPath('data.synced', false);
        $groupId = (int) $create->json('data.id');

        $this->withToken($token)
            ->getJson('/api/admin/catalog/groups/'.$groupId)
            ->assertOk()
            ->assertJsonPath('data.title', 'دسته دستی');

        $this->withToken($token)
            ->putJson('/api/admin/catalog/groups/'.$groupId, ['slug' => 'manual-cat-2', 'title' => 'دسته دستی دو'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'manual-cat-2');

        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, [
                'name' => '۶۰ یوسی ویژه',
                'visible' => true,
                'status' => 'active',
                'price' => 123450,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '۶۰ یوسی ویژه')
            ->assertJsonPath('data.price', 123000);

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, ['name' => '۶۰ یوسی'])
            ->assertOk()
            ->assertJsonPath('data.name', '۶۰ یوسی')
            ->assertJsonPath('data.price', 123000);

        $product = $sku->product;
        $this->withToken($token)
            ->putJson('/api/admin/catalog/products/'.$product->id, [
                'catalogGroupId' => $groupId,
                'title' => $product->title,
                'seoTitle' => 'خرید یوسی پابجی موبایل ارزان | تحویل فوری',
                'seoDescription' => 'خرید یوسی پابجی موبایل از شارژینو با قیمت تومانی و تحویل فوری. پشتیبانی پاسخگوست؛ پرداخت ریالی بدون رمز اکانت.',
            ])
            ->assertOk()
            ->assertJsonPath('data.catalogGroupId', $groupId)
            ->assertJsonPath('data.seoTitle', 'خرید یوسی پابجی موبایل ارزان | تحویل فوری');

        $this->getJson('/api/catalog/products/'.$product->handle)
            ->assertOk()
            ->assertJsonPath('data.seoTitle', 'خرید یوسی پابجی موبایل ارزان | تحویل فوری');

        $paged = $this->withToken($token)->getJson('/api/admin/catalog/products?category='.$groupId.'&page=1&perPage=10&q=پابجی');
        $paged->assertOk()->assertJsonPath('data.meta.page', 1);
        $this->assertNotEmpty($paged->json('data.items'));

        $this->withToken($token)
            ->deleteJson('/api/admin/catalog/groups/'.$groupId)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CATEGORY_HAS_PRODUCTS');

        $empty = $this->withToken($token)->postJson('/api/admin/catalog/groups', [
            'title' => 'خالی',
            'slug' => 'empty-cat',
        ]);
        $emptyId = (int) $empty->json('data.id');
        $this->withToken($token)
            ->deleteJson('/api/admin/catalog/groups/'.$emptyId)
            ->assertOk()
            ->assertJsonPath('data.id', $emptyId);

        $synced = CatalogGroup::query()->whereNotNull('shop2_big_category_id')->first()
            ?: tap(CatalogGroup::query()->where('slug', 'game-topup')->firstOrFail(), function (CatalogGroup $g) {
                $g->shop2_big_category_id = 1;
                $g->save();
            });
        $this->withToken($token)
            ->deleteJson('/api/admin/catalog/groups/'.$synced->id)
            ->assertOk()
            ->assertJsonPath('data.visible', false);

        $this->withToken($token)
            ->getJson('/api/admin/supplier')
            ->assertOk()
            ->assertJsonMissingPath('data.account.raw')
            ->assertJsonPath('data.configured', false);

        $order = $this->failedOrder();
        $list = $this->withToken($token)->getJson('/api/admin/orders?status=failed');
        $list->assertOk();
        $this->assertSame($order->number, $list->json('data.items.0.number'));

        $this->withToken($token)
            ->getJson('/api/admin/orders/'.$order->number)
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_FAILED)
            ->assertJsonPath('data.userPhoneNumber', '09120001111');

        $this->withToken($token)
            ->postJson('/api/admin/orders/'.$order->id.'/mark-refunded', ['reason' => 'پشتیبانی'])
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_REFUNDED);

        $draft = $this->draftOrder();
        $this->withToken($token)
            ->postJson('/api/admin/orders/'.$draft->id.'/mark-refunded')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ORDER_STATE_INVALID');

        $this->withToken($token)
            ->postJson('/api/admin/orders/'.$draft->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED);

        $summary = $this->withToken($token)->getJson('/api/admin/dashboard/summary');
        $summary->assertOk()->assertJsonStructure([
            'data' => ['ordersToday', 'revenueTodayToman', 'pendingFulfillment', 'failedToday', 'supplierWallet', 'supplierWalletLow'],
        ]);
    }

    public function test_hide_product_sets_hidden_status(): void
    {
        $token = $this->adminToken();
        $product = Product::query()->firstOrFail();
        $this->withToken($token)
            ->deleteJson('/api/admin/catalog/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'hidden')
            ->assertJsonPath('data.visible', false);
    }

    public function test_purge_shop2_deletes_shop2_only_catalog(): void
    {
        $token = $this->adminToken();
        $seed = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $group = CatalogGroup::query()->create([
            'shop2_big_category_id' => 88002,
            'name' => 'Free Fire',
            'slug' => 'free-fire-2',
            'title' => 'Free Fire',
            'visible' => true,
            'sort_order' => 99,
        ]);
        $shop2 = Product::query()->create([
            'catalog_group_id' => $group->id,
            'shop2_category_id' => 88002,
            'handle' => 'shop2-free-fire-test',
            'code' => 'shop2-ff',
            'title' => 'Free Fire Shop2',
            'name_en' => 'Free Fire',
            'publisher' => 'Garena',
            'category_name' => 'Free Fire',
            'subtitle' => 'test',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
        ]);
        Sku::query()->create([
            'product_id' => $shop2->id,
            'shop2_item_id' => 99001,
            'code' => 'ff-100',
            'name' => '100',
            'price' => 10000,
            'status' => 'active',
            'visible' => true,
        ]);

        $this->withToken($token)
            ->deleteJson('/api/admin/catalog/shop2')
            ->assertOk()
            ->assertJsonPath('data.products', 1)
            ->assertJsonPath('data.groups', 1);

        $this->assertNull(Product::query()->find($shop2->id));
        $this->assertNull(CatalogGroup::query()->find($group->id));
        $this->assertNotNull(Product::query()->find($seed->id));
    }

    public function test_admin_saves_persian_and_english_names(): void
    {
        $token = $this->adminToken();
        $group = CatalogGroup::query()->where('slug', 'game-topup')->first()
            ?? CatalogGroup::query()->firstOrFail();
        $this->withToken($token)
            ->putJson('/api/admin/catalog/groups/'.$group->id, [
                'title' => 'شارژ بازی',
                'nameEn' => 'Game top-up',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'شارژ بازی')
            ->assertJsonPath('data.nameEn', 'Game top-up');

        $product = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $this->withToken($token)
            ->putJson('/api/admin/catalog/products/'.$product->id, [
                'title' => 'پابجی موبایل',
                'nameEn' => 'PUBG Mobile',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'پابجی موبایل')
            ->assertJsonPath('data.nameEn', 'PUBG Mobile');

        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, [
                'name' => '۶۰ یوسی',
                'nameEn' => '60 UC',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '۶۰ یوسی')
            ->assertJsonPath('data.nameEn', '60 UC');

        $this->getJson('/api/catalog/products/pubg-mobile')
            ->assertOk()
            ->assertJsonPath('data.titleEn', 'PUBG Mobile');

        $search = $this->getJson('/api/catalog/products?q=PUBG');
        $search->assertOk();
        $rows = $search->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
    }

    public function test_admin_product_skus_include_purchase_cost_not_public(): void
    {
        $token = $this->adminToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->cost_usd = '0.84';
        $sku->live_cost_usd = '0.84';
        $sku->save();
        Setting::putValue('usd_toman_rate', '100000');

        $detail = $this->withToken($token)->getJson('/api/admin/catalog/products/'.$sku->product_id);
        $detail->assertOk();
        $row = collect($detail->json('data.skus'))->firstWhere('id', $sku->id);
        $this->assertIsArray($row);
        $this->assertSame('0.84', $row['purchaseUsd']);
        $this->assertSame(84000, $row['purchaseToman']);

        $this->getJson('/api/catalog/products/'.$sku->product->handle)
            ->assertOk()
            ->assertJsonMissingPath('data.skus.0.purchaseUsd')
            ->assertJsonMissingPath('data.skus.0.purchaseToman')
            ->assertJsonMissingPath('data.skus.0.costUsd');
    }

    public function test_admin_can_set_manual_price_and_dated_sale(): void
    {
        $token = $this->adminToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->cost_usd = '0.84';
        $sku->live_cost_usd = '0.84';
        $sku->save();
        Setting::putValue('usd_toman_rate', '100000');
        Setting::putValue('margin_percent', '5');
        $ends = now()->addDays(3)->toDateString();

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, [
                'price' => 150000,
                'salePrice' => 100000,
                'saleEndsAt' => $ends,
            ])
            ->assertOk()
            ->assertJsonPath('data.price', 150000)
            ->assertJsonPath('data.sellPrice', 100000)
            ->assertJsonPath('data.salePrice', 100000)
            ->assertJsonPath('data.saleActive', true)
            ->assertJsonPath('data.priceManual', true);

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, [
                'price' => 150000,
                'salePrice' => 200000,
                'saleEndsAt' => $ends,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SALE_TOO_HIGH');

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, [
                'salePrice' => 90000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SALE_INCOMPLETE');

        $this->app->make(PricingService::class)->repriceAllSkus();
        $sku->refresh();
        $this->assertSame(150000, (int) $sku->price);

        $public = $this->getJson('/api/catalog/products/pubg-mobile');
        $public->assertOk();
        $row = collect($public->json('data.skus'))->firstWhere('id', $sku->id);
        $this->assertSame(100000, $row['price']);
        $this->assertSame(150000, $row['originalPrice']);
        $this->assertSame(100000, $public->json('data.minPrice'));

        $home = $this->getJson('/api/catalog/home')->assertOk();
        $this->assertTrue(
            collect($home->json('data.flashSale'))->pluck('slug')->contains('pubg-mobile')
        );

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, [
                'salePrice' => null,
                'saleEndsAt' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.saleActive', false)
            ->assertJsonPath('data.sellPrice', 150000);

        $homeAfter = $this->getJson('/api/catalog/home')->assertOk();
        $this->assertFalse(
            collect($homeAfter->json('data.flashSale'))->pluck('slug')->contains('pubg-mobile')
        );
    }

    public function test_admin_cannot_set_extreme_sale_discount(): void
    {
        $token = $this->adminToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, [
                'price' => 127100,
                'salePrice' => 12000,
                'saleEndsAt' => now()->addDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SALE_DISCOUNT_TOO_DEEP');
    }

    public function test_purge_non_fazer_removes_seed_handles(): void
    {
        $this->assertNotNull(Product::query()->where('handle', 'pubg-mobile')->first());

        $this->artisan('charjino:purge-non-fazer')->assertSuccessful();

        $this->assertNull(Product::query()->where('handle', 'pubg-mobile')->first());
        $this->assertNull(Sku::query()->where('code', 'uc-60')->first());
    }

    private function adminToken(): string
    {
        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $login->assertOk();

        return (string) $login->json('data.access_token');
    }

    private function failedOrder(): Order
    {
        $user = User::query()->create([
            'phone' => '09120001111',
            'role' => 'user',
            'status' => 'active',
        ]);
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $order = new Order;
        $order->user_id = $user->id;
        $order->sku_id = $sku->id;
        $order->product_id = $sku->product_id;
        $order->quantity = 1;
        $order->requirements = ['player_id' => '5123456789'];
        $order->amount_toman = $sku->price;
        $order->cost_usd_snapshot = '0.10';
        $order->fx_rate_snapshot = '100000';
        $order->status = Order::STATUS_FAILED;
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', 'admin-test');
        $order->save();

        return $order;
    }

    private function draftOrder(): Order
    {
        $user = User::query()->where('phone', '09120001111')->firstOrFail();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $order = new Order;
        $order->user_id = $user->id;
        $order->sku_id = $sku->id;
        $order->product_id = $sku->product_id;
        $order->quantity = 1;
        $order->requirements = ['player_id' => '5123456789'];
        $order->amount_toman = $sku->price;
        $order->cost_usd_snapshot = '0.10';
        $order->fx_rate_snapshot = '100000';
        $order->status = Order::STATUS_DRAFT;
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', 'admin-draft');
        $order->save();

        return $order;
    }
}

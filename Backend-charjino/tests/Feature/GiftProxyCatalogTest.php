<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftProxyCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Setting::putValue('usd_toman_rate', '100000');
        Setting::putValue('margin_percent', '0');
    }

    public function test_admin_can_list_a_game_that_sells_an_equivalent_gift_card(): void
    {
        $token = $this->adminToken();
        $group = CatalogGroup::query()->create([
            'title' => 'کلید بازی',
            'name' => 'Game keys proxy',
            'slug' => 'game-keys-proxy',
            'visible' => true,
            'sort_order' => 3,
        ]);
        $giftProduct = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'xbox-gift-us',
            'code' => 'xbox-gift-us',
            'title' => 'گیفت ایکس‌باکس آمریکا',
            'name_en' => 'Xbox Gift US',
            'publisher' => 'Xbox',
            'category_name' => 'گیفت کارت',
            'subtitle' => 'گیفت',
            'thumbnail' => '/image/catalog/gift.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => 'giftcard',
            'platform' => 'xbox',
            'region' => 'US',
        ]);
        $gift = Sku::query()->create([
            'product_id' => $giftProduct->id,
            'code' => 'xbox-60',
            'name' => '۶۰ دلار',
            'name_en' => '60 USD',
            'fulfillment_type' => 'giftcard',
            'fazercards_category_id' => 'giftcard:xbox',
            'fazercards_offer_id' => 'card-60',
            'price' => 1000,
            'cost_usd' => '60',
            'region' => 'US',
            'status' => 'active',
            'visible' => true,
        ]);

        $created = $this->withToken($token)->postJson('/api/admin/catalog/products', [
            'title' => 'اف‌سی ۲۷ ایکس‌باکس',
            'nameEn' => 'FC 27 Xbox',
            'catalogGroupId' => $group->id,
            'platform' => 'xbox',
            'region' => 'US',
            'description' => 'نسخهٔ ایکس‌باکس',
        ]);
        $created->assertOk()
            ->assertJsonPath('data.giftProxy', true)
            ->assertJsonPath('data.platform', 'xbox');
        $this->assertFalse((bool) Product::query()->find($created->json('data.id'))?->needsBuyerIdentity());

        $productId = (int) $created->json('data.id');
        $pack = $this->withToken($token)->postJson('/api/admin/catalog/products/'.$productId.'/skus', [
            'name' => 'نسخه استاندارد',
            'nameEn' => 'Standard',
            'costUsd' => '60',
            'giftSkuId' => $gift->id,
        ]);
        $pack->assertOk()
            ->assertJsonPath('data.giftSkuId', $gift->id)
            ->assertJsonPath('data.costUsd', '60')
            ->assertJsonPath('data.price', 6000000);

        $display = Sku::query()->findOrFail($pack->json('data.id'));
        $this->assertSame($gift->id, $display->supplierSku()?->id);
        $this->assertFalse($display->isFazerCards());

        $this->withToken($token)
            ->postJson('/api/admin/catalog/products/'.$productId.'/skus', [
                'name' => 'اشتباه',
                'nameEn' => 'Wrong',
                'costUsd' => '10',
                'giftSkuId' => $display->id,
            ])
            ->assertStatus(422);

        $shop = $this->getJson('/api/catalog/products/fc-27-xbox');
        $shop->assertOk()
            ->assertJsonPath('data.giftProxy', true)
            ->assertJsonPath('data.inputFields', [])
            ->assertJsonPath('data.skus.0.name', 'نسخه استاندارد');

        $cards = $this->withToken($token)->getJson('/api/admin/catalog/gift-cards?platform=xbox&region=US');
        $cards->assertOk();
        $this->assertSame($gift->id, $cards->json('data.items.0.id'));
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
}

<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdentityCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_check_confirms_username_when_supplier_unavailable(): void
    {
        $this->seed();
        config(['charjino.supplier.s2t_key' => 'test.key-id.secret']);
        config(['charjino.supplier.s2t_base' => 'https://shop2topup.com/api/endpoints/v1']);
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'error' => ['code' => 'PLAYER_CHECK_UNAVAILABLE', 'message' => 'busy'],
            ], 503),
        ]);

        $group = CatalogGroup::query()->create([
            'name' => 'Telegram',
            'title' => 'Telegram',
            'slug' => 'telegram-check-1103',
            'visible' => true,
            'sort_order' => 40,
        ]);
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => 'telegram-stars-check',
            'code' => 'tg-stars-check',
            'title' => 'Telegram',
            'publisher' => 'Telegram',
            'category_name' => 'Telegram',
            'subtitle' => 'ستاره',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'inputs' => ['username'],
        ]);
        $sku = Sku::query()->create([
            'product_id' => $product->id,
            'code' => 'stars-50-check',
            'name' => '50 Stars',
            'price' => 225600,
            'shop2_item_id' => 3441,
            'status' => 'active',
            'visible' => true,
        ]);

        $token = $this->shopToken();
        $check = $this->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => ['username' => 'charjino_user'],
        ], ['Authorization' => 'Bearer '.$token]);

        $check->assertOk()
            ->assertJsonPath('data.playerName', '@charjino_user')
            ->assertJsonPath('data.identityKind', 'user')
            ->assertJsonPath('data.validation', 'skipped')
            ->assertJsonPath('data.validateMode', 'manual');

        $this->getJson('/api/catalog/products/telegram-stars-check')
            ->assertOk()
            ->assertJsonPath('data.identityKind', 'user')
            ->assertJsonPath('data.inputFields.0.label', 'نام کاربری');
    }

    public function test_invalid_telegram_username_is_rejected(): void
    {
        $this->seed();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $token = $this->shopToken();

        $this->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => ['playerId' => 'ab'],
        ], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'PLAYER_NOT_FOUND');
    }

    private function shopToken(): string
    {
        $this->postJson('/api/auth/request-otp', ['phoneNumber' => '09123456789'])->assertOk();
        $verify = $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => '09123456789',
            'code' => '123456',
        ]);
        $verify->assertOk();

        return (string) $verify->json('data.access_token');
    }
}

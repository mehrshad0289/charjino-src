<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\CatalogExclusion;
use App\Modules\Catalog\CatalogUrlLifecycle;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogUrlLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_hide_missing_stays_200_until_expiry_then_redirects(): void
    {
        $group = $this->topupGroup();
        $live = $this->makeProduct($group, 'topup-c3-pubg-mobile-reserve', 'PUBG Reserve', FazerCardsIds::TOPUP);
        $gone = $this->makeProduct($group, 'topup-c3-pubg-mobile-auto', 'PUBG Mobile', FazerCardsIds::TOPUP);
        $gone->visible = false;
        $gone->save();

        $this->assertTrue(app(CatalogUrlLifecycle::class)->isTemporaryUnavailable($gone->fresh()));
        $this->getJson('/api/catalog/products/topup-c3-pubg-mobile-auto')->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.temporarilyUnavailable', true)
            ->assertJsonPath('data.isOrderable', false)
            ->assertJsonPath('data.alternate.handle', $live->handle);
        $this->getJson('/api/catalog/resolve?path=/catalog/topup-c3-pubg-mobile-auto')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
        $this->assertTrue(app(CatalogUrlLifecycle::class)->departureCovered($gone));

        $this->travel(15)->days();
        app(CatalogUrlLifecycle::class)->expireTemporaryUnavailable();
        $this->getJson('/api/catalog/products/topup-c3-pubg-mobile-auto')->assertStatus(301);
        $this->getJson('/api/catalog/resolve?path=/catalog/topup-c3-pubg-mobile-auto')
            ->assertOk()
            ->assertJsonPath('data.status', 'redirect')
            ->assertJsonPath('data.target', '/catalog/'.$live->handle);
    }

    public function test_login_exclusion_is_gone_without_sibling_and_redirect_with_sibling(): void
    {
        $group = $this->topupGroup();
        $this->makeProduct($group, 'topup-genshin-impact-global', 'Genshin Global', FazerCardsIds::TOPUP);
        $login = $this->makeProduct($group, 'topup-genshin-impact-login', 'Genshin Via Login', FazerCardsIds::TOPUP);
        $login->excluded_reason = 'login_required';
        $login->visible = false;
        $login->save();

        $this->getJson('/api/catalog/products/topup-genshin-impact-login')->assertStatus(301);
        $this->getJson('/api/catalog/resolve?path=/catalog/topup-genshin-impact-login')
            ->assertOk()
            ->assertJsonPath('data.status', 'redirect')
            ->assertJsonPath('data.target', '/catalog/topup-genshin-impact-global');
        $this->assertTrue(app(CatalogUrlLifecycle::class)->departureCovered($login));

        $lonely = $this->makeProduct($group, 'topup-neverness-to-everness-login', 'Neverness Via Login', FazerCardsIds::TOPUP);
        $lonely->excluded_reason = 'login_required';
        $lonely->visible = false;
        $lonely->save();
        $this->getJson('/api/catalog/resolve?path=/catalog/topup-neverness-to-everness-login')
            ->assertOk()
            ->assertJsonPath('data.status', 'gone');
    }

    public function test_admin_hide_writes_redirect_and_uncovered_hidden_fails_guard(): void
    {
        $group = $this->topupGroup();
        $this->makeProduct($group, 'keep-me', 'Keep', FazerCardsIds::TOPUP);
        $hidden = $this->makeProduct($group, 'hide-me', 'Hide', FazerCardsIds::TOPUP);
        $hidden->status = 'hidden';
        $hidden->visible = false;
        $hidden->save();
        $this->assertTrue(app(CatalogUrlLifecycle::class)->departureCovered($hidden));
        $this->getJson('/api/catalog/resolve?path=/catalog/hide-me')
            ->assertOk()
            ->assertJsonPath('data.status', 'redirect');
    }

    public function test_restore_clears_false_login_on_the_last_login_title(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
        $gift = $this->makeProduct($group, 'steam-gift-4773940', 'THE LAST LOGIN', FazerCardsIds::STEAM_GIFT);
        Product::withoutEvents(function () use ($gift): void {
            $gift->excluded_reason = 'login_required';
            $gift->visible = false;
            $gift->save();
        });
        $restored = app(CatalogUrlLifecycle::class)->restoreFalseLoginExclusions();
        $this->assertSame(1, $restored);
        $gift->refresh();
        $this->assertNull($gift->excluded_reason);
        $this->assertTrue((bool) $gift->visible);
        $this->getJson('/api/catalog/products/steam-gift-4773940')->assertOk();
    }

    public function test_adult_steam_gift_redirects_to_category(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'steam-gifts'],
            ['name' => 'Steam gifts', 'title' => 'گیفت استیم', 'visible' => true, 'sort_order' => 80],
        );
        $gift = $this->makeProduct($group, 'steam-gift-5041000', 'FEMBOY FUTA BEACH HOUSE', FazerCardsIds::STEAM_GIFT);
        $gift->excluded_reason = CatalogExclusion::ADULT_CONTENT;
        $gift->visible = false;
        $gift->indexable = false;
        $gift->save();
        $this->getJson('/api/catalog/resolve?path=/catalog/steam-gift-5041000')
            ->assertOk()
            ->assertJsonPath('data.status', 'redirect')
            ->assertJsonPath('data.target', '/catalog/category/steam-gifts');
    }

    private function topupGroup(): CatalogGroup
    {
        return CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'Game top-up', 'title' => 'شارژ بازی', 'visible' => true, 'sort_order' => 10],
        );
    }

    public function test_missing_handle_aliases_to_unique_live_prefix(): void
    {
        $group = $this->topupGroup();
        $this->makeProduct($group, 'topup-valorant-vn', 'Valorant VN', FazerCardsIds::TOPUP);
        $this->getJson('/api/catalog/resolve?path=/catalog/valorant-vn')
            ->assertOk()
            ->assertJsonPath('data.status', 'redirect')
            ->assertJsonPath('data.target', '/catalog/topup-valorant-vn');
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
            'fazercards_category_id' => $family === FazerCardsIds::STEAM_GIFT
                ? $family.':'.(preg_replace('/\D+/', '', $handle) ?: $handle)
                : $handle,
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

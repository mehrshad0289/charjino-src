<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\ProductSupplierIdentity;
use App\Models\Sku;
use App\Models\User;
use App\Models\UserFavorite;
use App\Modules\Catalog\ProductDuplicateMerger;
use App\Modules\Catalog\ProductSupplierCatalog;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_find_duplicates_reports_normalized_key_group(): void
    {
        $this->pair();
        $code = Artisan::call('products:find-duplicates');
        $this->assertSame(0, $code);
        $out = Artisan::output();
        $this->assertStringContainsString('topup-division-resurgence', $out);
        $this->assertStringContainsString('topup-the-division-resurgence', $out);
        $this->assertStringContainsString('topupdivisionresurgence', $out);
    }

    public function test_merge_deactivates_duplicate_and_returns_301(): void
    {
        [$keeper, $loser] = $this->pair();
        $user = User::query()->create([
            'phone' => '09123334455',
            'role' => 'user',
            'status' => 'active',
        ]);
        ProductComment::query()->create([
            'product_id' => $loser->id,
            'user_id' => $user->id,
            'body' => 'نظر روی تکراری',
            'status' => ProductComment::STATUS_APPROVED,
        ]);
        UserFavorite::query()->create([
            'user_id' => $user->id,
            'product_id' => $loser->id,
        ]);

        $result = app(ProductDuplicateMerger::class)->mergeGroupsContaining([
            'topup-the-division-resurgence',
        ], true);
        $this->assertTrue($result[0]['applied'] ?? false);
        $this->assertSame($keeper->id, $result[0]['winner']['id']);

        $loser->refresh();
        $keeper->refresh();
        $this->assertSame('hidden', $loser->status);
        $this->assertFalse((bool) $loser->visible);
        $this->assertFalse((bool) $loser->indexable);
        $this->assertSame($keeper->id, $loser->substitute_product_id);
        $this->assertSame($keeper->id, (int) ProductComment::query()->where('body', 'نظر روی تکراری')->value('product_id'));
        $this->assertSame($keeper->id, (int) UserFavorite::query()->where('user_id', $user->id)->value('product_id'));

        $this->getJson('/api/catalog/resolve?path=/catalog/topup-the-division-resurgence')
            ->assertOk()
            ->assertJsonPath('data.status', 'redirect')
            ->assertJsonPath('data.target', '/catalog/topup-division-resurgence');
        $redirected = $this->getJson('/api/catalog/products/topup-the-division-resurgence');
        $redirected->assertStatus(301);
        $this->assertStringContainsString('/catalog/topup-division-resurgence', (string) $redirected->headers->get('Location'));
        $this->getJson('/api/catalog/products/topup-division-resurgence')->assertOk();
    }

    public function test_supplier_lookup_reuses_normalized_key(): void
    {
        [$keeper] = $this->pair();
        $found = app(ProductSupplierCatalog::class)->findLiveOrAnyByKey(
            'topup-the-division-resurgence',
            FazerCardsIds::TOPUP,
            null,
        );
        $this->assertNotNull($found);
        $this->assertSame($keeper->id, $found->id);
        app(ProductSupplierCatalog::class)->remember($keeper, ProductSupplierIdentity::FAZERCARDS, 'the-division-resurgence');
        $this->assertSame(
            $keeper->id,
            app(ProductSupplierCatalog::class)->findByRemote(ProductSupplierIdentity::FAZERCARDS, 'the-division-resurgence')?->id,
        );
    }

    /**
     * @return array{0: Product, 1: Product}
     */
    private function pair(): array
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'شارژ بازی', 'title' => 'شارژ بازی', 'visible' => true, 'sort_order' => 10],
        );
        $keeper = $this->makeProduct($group, 'topup-division-resurgence', 'The Division Resurgence', 'division-resurgence');
        $loser = $this->makeProduct($group, 'topup-the-division-resurgence', 'The Division Resurgence', 'the-division-resurgence');
        $loser->seo_description = 'متای یکسان برای تست';
        $keeper->seo_description = 'متای یکسان برای تست';
        $keeper->save();
        $loser->save();

        return [$keeper, $loser];
    }

    private function makeProduct(CatalogGroup $group, string $handle, string $title, string $remote): Product
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
            'indexable' => true,
            'fulfillment_type' => FazerCardsIds::TOPUP,
            'fazercards_category_id' => $remote,
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
            'fulfillment_type' => FazerCardsIds::TOPUP,
            'sort_order' => 10,
        ]);

        return $product;
    }
}

<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\SearchTag;
use App\Models\Sku;
use App\Modules\Catalog\SearchTagBank;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_query_shorter_than_two_chars_is_empty(): void
    {
        $res = $this->getJson('/api/catalog/smart-search?q=ف');
        $res->assertOk()->assertJsonPath('data.mode', 'empty');
        $this->assertSame([], $res->json('data.items'));
    }

    public function test_category_prefix_wins_over_product_titles(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            [
                'name' => 'Gift cards',
                'title' => 'گیفت کارت',
                'visible' => true,
                'sort_order' => 20,
            ],
        );
        $this->makeProduct('steam-wallet-us', 'استیم والت آمریکا', 'Steam Wallet US', $group);

        $res = $this->getJson('/api/catalog/smart-search?q='.rawurlencode('گیف'));
        $res->assertOk()->assertJsonPath('data.mode', 'entities');
        $types = collect($res->json('data.items'))->pluck('result_type')->all();
        $this->assertContains('category', $types);
        $this->assertNotContains('product', $types);
    }

    public function test_football_tag_finds_fc_even_when_word_is_missing_from_titles(): void
    {
        $product = $this->makeProduct('ea-fc-27', 'EA Sports FC 27', 'EA Sports FC 27');
        SearchTagBank::sync();
        $this->assertTrue($product->fresh()->searchTags()->where('slug', 'football')->exists());

        $tagHit = $this->getJson('/api/catalog/smart-search?q='.rawurlencode('فوتبال'));
        $tagHit->assertOk()->assertJsonPath('data.mode', 'entities');
        $this->assertSame('tag', $tagHit->json('data.items.0.result_type'));
        $this->assertSame('فوتبال', $tagHit->json('data.items.0.name'));

        $listed = $this->getJson('/api/catalog/products?q='.rawurlencode('فوتبال').'&page=1&perPage=20');
        $listed->assertOk();
        $this->assertContains('ea-fc-27', collect($listed->json('data.items'))->pluck('handle')->all());

        $detail = $this->getJson('/api/catalog/products/ea-fc-27');
        $detail->assertOk();
        $this->assertContains('فوتبال', $detail->json('data.searchTags'));

        $typedFc = $this->getJson('/api/catalog/smart-search?q=fc');
        $typedFc->assertOk()->assertJsonPath('data.mode', 'products');
        $this->assertContains('ea-fc-27', collect($typedFc->json('data.items'))->pluck('handle')->all());
    }

    public function test_two_tokens_require_both_and_three_tokens_need_two(): void
    {
        $this->makeProduct('steam-gift-iran', 'گیفت کارت استیم', 'Steam Gift');
        $this->makeProduct('other-card', 'کارت گوگل پلی', 'Google Play');

        $andMiss = $this->getJson('/api/catalog/smart-search?q='.rawurlencode('استیم یزد').'&mode=products');
        $andMiss->assertOk();
        $this->assertNotContains('steam-gift-iran', collect($andMiss->json('data.items'))->pluck('handle')->all());

        $orHit = $this->getJson('/api/catalog/smart-search?q='.rawurlencode('گیفت سنتی استیم').'&mode=products');
        $orHit->assertOk();
        $this->assertContains('steam-gift-iran', collect($orHit->json('data.items'))->pluck('handle')->all());
        $this->assertNotContains('other-card', collect($orHit->json('data.items'))->pluck('handle')->all());
    }

    public function test_brand_prefix_suggests_platform(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            [
                'name' => 'Gift cards',
                'title' => 'گیفت کارت',
                'visible' => true,
                'sort_order' => 20,
            ],
        );
        $product = $this->makeProduct('xbox-us', 'Xbox US', 'Xbox US', $group);
        $product->platform = 'xbox';
        $product->save();

        $res = $this->getJson('/api/catalog/smart-search?q='.rawurlencode('ایکس'));
        $res->assertOk()->assertJsonPath('data.mode', 'entities');
        $this->assertContains(
            'xbox',
            collect($res->json('data.items'))->where('result_type', 'brand')->pluck('slug')->all(),
        );
    }

    public function test_persian_digits_normalize_in_query(): void
    {
        $this->makeProduct('pubg-uc-60', 'پابجی ۶۰ یوسی', 'PUBG 60 UC');
        $res = $this->getJson('/api/catalog/smart-search?q='.rawurlencode('پابجي ۶۰').'&mode=products');
        $res->assertOk();
        $this->assertContains('pubg-uc-60', collect($res->json('data.items'))->pluck('handle')->all());
    }

    public function test_keyboard_layout_fallback_finds_persian_title(): void
    {
        $this->makeProduct('keyboard-now', 'خب الان', 'Now');
        $res = $this->getJson('/api/catalog/smart-search?q=of+hghk&mode=products');
        $res->assertOk();
        $this->assertContains('keyboard-now', collect($res->json('data.items'))->pluck('handle')->all());
    }

    public function test_admin_can_assign_search_tags(): void
    {
        $product = $this->makeProduct('custom-game', 'بازی سفارشی', 'Custom Game');
        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $login->assertOk();
        $token = (string) $login->json('data.access_token');

        $save = $this->withToken($token)->putJson('/api/admin/catalog/products/'.$product->id, [
            'title' => $product->title,
            'searchTags' => ['فوتبال', 'فیفا'],
        ]);
        $save->assertOk();
        $this->assertEqualsCanonicalizing(['فوتبال', 'فیفا'], $save->json('data.searchTags'));
        $this->assertSame(2, SearchTag::query()->count());
    }

    private function makeProduct(string $handle, string $title, string $nameEn, ?CatalogGroup $group = null): Product
    {
        $group ??= CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-keys'],
            [
                'name' => 'Game keys',
                'title' => 'کلید بازی',
                'visible' => true,
                'sort_order' => 30,
            ],
        );
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $title,
            'name_en' => $nameEn,
            'publisher' => '',
            'category_name' => $group->title,
            'subtitle' => 'کلید دیجیتال',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => FazerCardsIds::GAMEKEY,
            'platform' => 'steam',
            'inputs' => [],
            'sort_order' => 10,
        ]);
        Sku::query()->create([
            'product_id' => $product->id,
            'code' => $handle.'-key',
            'name' => 'کلید',
            'price' => 80000,
            'cost_usd' => '10',
            'status' => 'active',
            'visible' => true,
            'fulfillment_type' => FazerCardsIds::GAMEKEY,
            'sort_order' => 10,
        ]);

        return $product;
    }
}

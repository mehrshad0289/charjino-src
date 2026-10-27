<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\PurchaseSchema;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FazerCardsOrderPayloadTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $posted = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd',
            'charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2',
            'charjino.supplier.fazercards_allow_live_order' => true,
        ]);
        $this->posted = [];
        Http::fake(function (Request $request) {
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($request->url(), PHP_URL_PATH)), '/');
            if ($path === 'balance') {
                return Http::response(['ok' => true, 'balance' => '1000.0000', 'currency' => 'USD'], 200);
            }
            $this->posted[] = [
                'path' => $path,
                'body' => $request->data(),
                'idempotency' => $request->header('Idempotency-Key')[0] ?? null,
            ];

            return Http::response(['ok' => true, 'order' => ['id' => 'ord-1', 'status' => 'created']], 201);
        });
    }

    public function test_topup_sends_riot_id_without_renaming(): void
    {
        $sku = $this->sku(FazerCardsIds::TOPUP, 'valorant', 'vp-100', [['key' => 'riot_id']]);
        $this->adapter()->placeOrder($sku, ['riot_id' => 'Spinner#EUW'], '1.00', 'idem-riot', true, 1);
        $this->assertSame('topups/order', $this->last()['path']);
        $this->assertSame('valorant', $this->last()['body']['category_id']);
        $this->assertSame('vp-100', $this->last()['body']['offer_id']);
        $this->assertSame(['riot_id' => 'Spinner#EUW'], $this->last()['body']['fields']);
    }

    public function test_topup_sends_user_id_and_server_select(): void
    {
        $sku = $this->sku(FazerCardsIds::TOPUP, 'genshin_global', '60', [
            ['key' => 'user_id'],
            ['key' => 'server', 'options' => [
                ['label' => 'America', 'value' => 'america'],
                ['label' => 'Asia', 'value' => 'asia'],
            ]],
        ]);
        $this->adapter()->placeOrder($sku, ['user_id' => '800000001', 'server' => 'asia'], '1.00', 'idem-gs', true, 1);
        $this->assertSame([
            'user_id' => '800000001',
            'server' => 'asia',
        ], $this->last()['body']['fields']);
    }

    public function test_topup_sends_player_id_and_server_id(): void
    {
        $sku = $this->sku(FazerCardsIds::TOPUP, 'mobile_legends', '86', [
            ['key' => 'player_id'],
            ['key' => 'server_id'],
        ]);
        $this->adapter()->placeOrder($sku, ['player_id' => '12345678', 'server_id' => '2001'], '1.00', 'idem-ml', true, 1);
        $this->assertSame([
            'player_id' => '12345678',
            'server_id' => '2001',
        ], $this->last()['body']['fields']);
    }

    public function test_giftcard_sends_quantity(): void
    {
        $sku = $this->sku(FazerCardsIds::GIFTCARD, 'steam', 'steam-10', []);
        $this->adapter()->placeOrder($sku, [], '3.00', 'idem-gift', true, 3);
        $this->assertSame('giftcards/order', $this->last()['path']);
        $this->assertSame([
            'category_id' => 'steam',
            'card_id' => 'steam-10',
            'quantity' => 3,
        ], $this->last()['body']);
        $this->assertNotEmpty($this->last()['idempotency']);
    }

    public function test_gamekey_sends_min_quantity(): void
    {
        $sku = $this->sku(FazerCardsIds::GAMEKEY, 'elden', 'std', []);
        $sku->min_order_quantity = 1;
        $sku->max_order_quantity = 5;
        $this->adapter()->placeOrder($sku, [], '4.00', 'idem-key', true, 2);
        $this->assertSame('gamekeys/order', $this->last()['path']);
        $this->assertSame([
            'game_id' => 'elden',
            'key_id' => 'std',
            'quantity' => 2,
        ], $this->last()['body']);

        $this->adapter()->placeOrder($sku, [], '4.00', 'idem-key-cap', true, 150);
        $this->assertSame(100, $this->last()['body']['quantity']);
    }

    public function test_steam_topup_uses_requirements_not_sku(): void
    {
        $sku = $this->sku(FazerCardsIds::STEAM_TOPUP, 'wallet', 'steam-topup:free', [['key' => 'steamLogin']]);
        $this->adapter()->placeOrder($sku, [
            'steamLogin' => 'pinkcat',
            'currency' => 'RUB',
            'amount' => '500.5',
        ], '6.26', 'idem-steam', true, 1);
        $this->assertSame('steam-topup/order', $this->last()['path']);
        $this->assertSame([
            'steamLogin' => 'pinkcat',
            'currency' => 'RUB',
            'amount' => '500.5',
        ], $this->last()['body']);
    }

    public function test_steam_gift_normalizes_invite_and_requires_region(): void
    {
        $sku = $this->sku(FazerCardsIds::STEAM_GIFT, '730', '42|EU', [['key' => 'invite_url']]);
        $sku->region = 'EU';
        $this->adapter()->placeOrder($sku, ['invite_url' => 's.team/p/abcd-efgh'], '9.99', 'idem-sg', true, 1);
        $this->assertSame('steam-gifts/order', $this->last()['path']);
        $this->assertSame([
            'invite_url' => 'https://s.team/p/abcd-efgh',
            'sub_id' => 42,
            'app_id' => 730,
            'region' => 'EU',
        ], $this->last()['body']);
    }

    public function test_telegram_stars_and_premium(): void
    {
        $stars = $this->sku(FazerCardsIds::TELEGRAM_STARS, 'stars', '50', [['key' => 'telegram_username']]);
        $this->adapter()->placeOrder($stars, ['telegram_username' => '@pinkcat'], '0.80', 'idem-stars', true, 1);
        $this->assertSame('telegram/stars/buy', $this->last()['path']);
        $this->assertSame([
            'telegram_username' => 'pinkcat',
            'quantity' => 50,
        ], $this->last()['body']);
        $this->assertNull($this->last()['idempotency']);

        $prem = $this->sku(FazerCardsIds::TELEGRAM_PREMIUM, 'premium', '3', [['key' => 'telegram_username']]);
        $this->adapter()->placeOrder($prem, ['telegram_username' => 'pinkcat'], '12.00', 'idem-prem', true, 1);
        $this->assertSame('telegram/premium/buy', $this->last()['path']);
        $this->assertSame([
            'telegram_username' => 'pinkcat',
            'months' => 3,
        ], $this->last()['body']);
    }

    private function adapter(): FazerCardsAdapter
    {
        return $this->app->make(FazerCardsAdapter::class);
    }

    /**
     * @return array{path: string, body: array<string, mixed>, idempotency: ?string}
     */
    private function last(): array
    {
        $this->assertNotEmpty($this->posted);

        return $this->posted[array_key_last($this->posted)];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function sku(string $family, string $category, string $offer, array $fields): Sku
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'payload-'.$family],
            ['name' => $family, 'title' => $family, 'visible' => true, 'sort_order' => 1],
        );
        $storedCategory = $family === FazerCardsIds::TOPUP
            ? $category
            : ($family === FazerCardsIds::STEAM_GIFT ? $category : FazerCardsIds::productKey($family, $category));
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $family.'-'.$category.'-'.substr(md5($offer), 0, 6),
            'code' => $family.'-'.$offer,
            'title' => $family,
            'name_en' => $family,
            'publisher' => '',
            'category_name' => $family,
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fazercards_category_id' => $storedCategory,
            'fulfillment_type' => $family,
            'visible' => true,
            'status' => 'active',
            'inputs' => [],
            'purchase_schema' => PurchaseSchema::build($family, $fields),
        ]);

        return Sku::query()->create([
            'product_id' => $product->id,
            'code' => $offer,
            'name' => $offer,
            'price' => 10000,
            'cost_usd' => '1.00',
            'fazercards_category_id' => $storedCategory,
            'fazercards_offer_id' => $offer,
            'fulfillment_type' => $family,
            'status' => 'active',
            'visible' => true,
        ]);
    }
}

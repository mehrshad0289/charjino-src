<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\FazerCardsCatalogSync;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsClient;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsSteamTopupQuotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FazerCardsCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{offer_id: string, name: string, price_usd: string}> */
    private array $pubgOffers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);
        $this->pubgOffers = [
            ['offer_id' => '60_uc', 'name' => '60 UC', 'price_usd' => '0.84'],
            ['offer_id' => '325_uc', 'name' => '325 UC', 'price_usd' => '4.20'],
        ];
        $this->fakeFazer();
    }

    public function test_sync_ingests_all_families_and_keeps_persian_title(): void
    {
        $pubg = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $persian = $pubg->title;
        $pubg->name_en = 'PUBG Mobile';
        $pubg->save();

        $result = $this->app->make(FazerCardsCatalogSync::class)->sync();

        $this->assertSame('completed', $result['status']);
        $this->assertGreaterThanOrEqual(3, $result['products']);
        $this->assertGreaterThanOrEqual(4, $result['skus']);

        $pubg->refresh();
        $this->assertSame($persian, $pubg->title);
        $this->assertSame('PUBG Mobile', $pubg->name_en);
        $this->assertSame('https://cdn.example/pubg.jpg', $pubg->thumbnail);
        $this->assertSame('https://cdn.example/pubg.jpg', $pubg->image_url);
        $topupGroup = CatalogGroup::query()->where('slug', 'game-topup')->first();
        $this->assertNotNull($topupGroup);
        $this->assertContains($topupGroup->image_url, [
            'https://cdn.example/pubg.jpg',
            'https://api.fzr.cards/covers/blood.jpg',
        ]);
        $this->assertSame('pubg_mobile_auto', $pubg->fazercards_category_id);
        $this->assertContains('player_id', collect($pubg->inputs)->map(fn ($row) => is_array($row) ? ($row['name'] ?? '') : $row)->all());

        $this->assertNotNull(Sku::query()->where('fazercards_category_id', 'pubg_mobile_auto')->where('fazercards_offer_id', '60_uc')->first());
        $blood = Product::query()->where('fazercards_category_id', 'blood_strike')->first();
        $this->assertNotNull($blood);
        $this->assertSame('https://api.fzr.cards/covers/blood.jpg', $blood->thumbnail);
        $this->assertSame('https://api.fzr.cards/covers/blood.jpg', $blood->image_url);
        $this->assertSame('شناسه بازیکن لازم است', $blood->subtitle);
        $wild = Product::query()->where('fazercards_category_id', 'wild_rift_vn')->first();
        $this->assertNotNull($wild);
        $this->assertSame('شارژ با شناسه بازیکن', $wild->subtitle);
        $this->assertStringContainsString('Region: Vietnam', (string) $wild->description);
        $bloodSku = Sku::query()->where('fazercards_category_id', 'blood_strike')->where('fazercards_offer_id', '60_uc')->first();
        $this->assertNotNull($bloodSku);
        $this->assertSame('topup-blood_strike-60_uc', $bloodSku->code);
        $this->assertNull(Sku::query()->where('fazercards_offer_id', '')->first());
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/topups?') && str_contains((string) $request->url(), 'include_ui=1'));
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/topups/offers') && str_contains((string) $request->url(), 'include_ui=1'));
        $gift = Product::query()->where('fazercards_category_id', 'giftcard:steam')->first();
        $this->assertNotNull($gift);
        $this->assertSame('steam', $gift->platform);
        $this->assertNotNull($gift);
        $this->assertSame('https://cdn.example/steam-card.jpg', $gift->thumbnail);
        $this->assertSame('https://cdn.example/steam-card.jpg', $gift->image_url);
        $this->assertSame('https://cdn.example/steam-card.jpg', $gift->image_url);
        $this->assertNotNull(Sku::query()->where('fazercards_category_id', 'giftcard:steam')->where('fazercards_offer_id', 'steam-10')->first());
        $this->assertNull(Sku::query()->where('fazercards_offer_id', 'steam-gone')->first());
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'telegram-stars:stars')->first());
        $this->assertSame('/image/catalog/telegram.svg', Product::query()->where('fazercards_category_id', 'telegram-stars:stars')->value('thumbnail'));
        $this->assertNotNull(Sku::query()->where('fazercards_category_id', 'telegram-stars:stars')->where('fazercards_offer_id', '50')->first());
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'telegram-premium:premium')->first());
        $this->assertNotNull(Sku::query()->where('fazercards_offer_id', '3')->where('fulfillment_type', 'telegram-premium')->first());
        $this->assertNull(Sku::query()->where('fazercards_offer_id', 'RUB|1000')->where('status', 'active')->first());
        $steam = Sku::query()->where('fazercards_offer_id', FazerCardsSteamTopupQuotes::FREE_OFFER_ID)->first();
        $this->assertNotNull($steam);
        $this->assertSame('0.15', (string) $steam->live_cost_usd);
        $wallet = Product::query()->where('fazercards_category_id', 'steam-topup:wallet')->first();
        $this->assertNotNull($wallet);
        $this->assertSame('/image/catalog/steam.svg', $wallet->thumbnail);
        $this->assertSame('steamLogin', $wallet->purchase_schema['fields'][0]['key'] ?? null);
        $giftInputs = collect($gift->inputs)->map(fn ($row) => is_array($row) ? ($row['name'] ?? $row['key'] ?? '') : $row)->all();
        $this->assertNotContains('playerId', $giftInputs);
        $this->assertSame([], $gift->purchase_schema['fields'] ?? ['x']);
        $this->assertSame(1, (int) Sku::query()->where('fazercards_category_id', 'pubg_mobile_auto')->where('fazercards_offer_id', '60_uc')->value('max_order_quantity'));
        $this->app->make(FazerCardsCatalogSync::class)->sync('steam-gifts');
        $dota = Product::query()->where('fazercards_category_id', 'steam-gift:570')->first();
        $this->assertNotNull($dota);
        $this->assertSame('https://cdn.cloudflare.steamstatic.com/steam/apps/570/header.jpg', $dota->thumbnail);
        $this->assertSame('https://cdn.cloudflare.steamstatic.com/steam/apps/570/header.jpg', $dota->image_url);
        $dotaUs = Sku::query()->where('fazercards_category_id', 'steam-gift:570')->where('fazercards_offer_id', '123|US')->first();
        $dotaEu = Sku::query()->where('fazercards_category_id', 'steam-gift:570')->where('fazercards_offer_id', '123|EU')->first();
        $this->assertNotNull($dotaUs);
        $this->assertNotNull($dotaEu);
        $this->assertSame('4.99', (string) $dotaUs->live_cost_usd);
        $this->assertSame('US', $dotaUs->region);
        $this->assertSame('steam-gift-570-123-US', $dotaUs->code);
        Http::assertSent(function (Request $request) {
            $url = (string) $request->url();
            $path = (string) parse_url($url, PHP_URL_PATH);

            return str_ends_with($path, '/steam-gifts/games') && ! str_contains($url, 'limit=');
        });
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/steam-gifts/games/570'));
        $key = Product::query()->where('fazercards_category_id', 'gamekey:elden')->first();
        $this->assertNotNull($key);
        $this->assertSame('https://cdn.example/elden.jpg', $key->thumbnail);
        $this->assertSame('https://cdn.example/elden.jpg', $key->image_url);
        $this->assertSame('steam', $key->platform);
        $this->assertSame('Global', $key->region);
        $this->assertSame([], $key->inputs);
        $std = Sku::query()->where('fazercards_category_id', 'gamekey:elden')->where('fazercards_offer_id', 'er-std')->first();
        $this->assertNotNull($std);
        $this->assertSame(1, (int) $std->min_order_quantity);
        $this->assertSame(5, (int) $std->max_order_quantity);
        $this->assertSame(5, (int) $std->stock);
        $bulk = Sku::query()->where('fazercards_offer_id', 'er-bulk')->first();
        $this->assertNotNull($bulk);
        $this->assertSame(5, (int) $bulk->min_order_quantity);
        $this->assertNull(Sku::query()->where('fazercards_offer_id', 'er-oos')->first());
        $manual = Product::query()->where('fazercards_category_id', 'manual:ms-boost')->first();
        $this->assertNotNull($manual);
        $this->assertSame('manual', $manual->excluded_reason);
        $this->assertFalse((bool) $manual->visible);
        $this->assertSame('https://cdn.example/manual.jpg', $manual->thumbnail);
        $this->assertSame('https://cdn.example/manual.jpg', $manual->image_url);
        $this->assertSame('متن مشتری', $manual->subtitle);
        $this->assertContains('player_id', collect($manual->inputs)->map(fn ($row) => is_array($row) ? ($row['name'] ?? '') : $row)->all());
        $manualSku = Sku::query()->where('fazercards_category_id', 'manual:ms-boost')->where('fazercards_offer_id', 'boost-1')->first();
        $this->assertNotNull($manualSku);
        $this->assertSame('manual-ms-boost-boost-1', $manualSku->code);
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/manual-services?') && str_contains((string) $request->url(), 'include_ui=1'));
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/manual-services/ms-boost/offers') && str_contains((string) $request->url(), 'include_ui=1'));
        $this->assertNull(Product::query()->where('name_en', 'like', '%VPN%')->first());
    }

    public function test_steam_gifts_http_tick_requests_full_catalog(): void
    {
        $sync = $this->app->make(FazerCardsCatalogSync::class);
        $first = $sync->tick(20_000, 2, 'steam-gifts');
        $this->assertSame('steam-gifts', $first['scope']);
        $this->assertContains($first['status'], ['running', 'completed']);
        $this->assertGreaterThanOrEqual(1, (int) $first['processedItems']);
        Http::assertSent(function (Request $request) {
            $url = (string) $request->url();
            $path = (string) parse_url($url, PHP_URL_PATH);

            return str_ends_with((string) $path, '/steam-gifts/games') && ! str_contains($url, 'limit=');
        });
    }

    public function test_funpay_sync_checks_panel_and_adds_no_shop_products(): void
    {
        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('funpay');
        $this->assertSame('completed', $result['status']);
        $this->assertSame('funpay', $result['scope']);
        $this->assertSame(0, (int) $result['products']);
        $this->assertStringContainsString('FunPay', (string) ($result['message'] ?? ''));
        Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/funpay/me'));
        $this->assertNull(Product::query()->where('fulfillment_type', 'funpay')->first());
    }

    public function test_family_sync_does_not_hide_other_families(): void
    {
        $this->app->make(FazerCardsCatalogSync::class)->sync();
        $uc = Sku::query()->where('fazercards_category_id', 'pubg_mobile_auto')->where('fazercards_offer_id', '60_uc')->firstOrFail();
        $this->assertSame('active', $uc->status);

        $result = $this->app->make(FazerCardsCatalogSync::class)->sync('giftcards');
        $this->assertSame('completed', $result['status']);
        $this->assertSame('giftcards', $result['scope']);

        $uc->refresh();
        $this->assertSame('active', $uc->status);
        $this->assertTrue((bool) $uc->visible);
        $this->assertNotNull(Product::query()->where('fazercards_category_id', 'giftcard:steam')->first());
    }

    public function test_family_tick_stays_on_that_catalog_and_waits_for_other_family(): void
    {
        $sync = $this->app->make(FazerCardsCatalogSync::class);
        $first = $sync->tick(20_000, 1, 'telegram');
        $this->assertSame('telegram', $first['scope']);
        $this->assertContains($first['status'], ['running', 'completed']);
        Http::assertNotSent(fn (Request $request) => str_contains((string) $request->url(), '/topups'));

        if (($first['status'] ?? '') === 'running') {
            $wait = $sync->tick(20_000, 1, 'giftcards');
            $this->assertSame('running', $wait['status']);
            $this->assertStringContainsString('اول همگام', (string) ($wait['message'] ?? ''));
            $this->assertSame('telegram', $wait['scope']);
        }
    }

    public function test_single_http_tick_stays_partial_until_loop_completes(): void
    {
        $sync = $this->app->make(FazerCardsCatalogSync::class);
        $first = $sync->tick(20_000, 1);
        $this->assertContains($first['status'], ['running', 'completed']);
        $result = $sync->sync();
        $this->assertSame('completed', $result['status']);
        $this->assertNotNull(Sku::query()->where('fazercards_category_id', 'pubg_mobile_auto')->where('fazercards_offer_id', '60_uc')->first());
    }

    public function test_cancel_stops_running_and_next_family_starts_fresh(): void
    {
        $sync = $this->app->make(FazerCardsCatalogSync::class);
        $first = $sync->tick(20_000, 1, 'steam-gifts');
        $this->assertContains($first['status'], ['running', 'completed']);

        $cancelled = $sync->cancel();
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertStringContainsString('لغو', (string) ($cancelled['message'] ?? ''));

        $next = $sync->tick(20_000, 20, 'telegram');
        $this->assertSame('telegram', $next['scope']);
        $this->assertNotSame('cancelled', $next['status']);
        $this->assertNotSame('steam-gifts', $next['scope']);
    }

    public function test_unreachable_tick_keeps_checkpoint_running(): void
    {
        $sync = $this->app->make(FazerCardsCatalogSync::class);
        $first = $sync->tick(20_000, 1);
        $this->assertSame('running', $first['status']);

        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $paused = $sync->tick(20_000, 1);
        $this->assertSame('running', $paused['status']);
        $this->assertNotSame('', (string) ($paused['phase'] ?? ''));
        $this->assertGreaterThanOrEqual((int) $first['products'], (int) $paused['products']);
    }

    public function test_missing_fazer_offer_is_hidden_and_not_sold(): void
    {
        $this->app->make(FazerCardsCatalogSync::class)->sync();
        $gone = Sku::query()->where('fazercards_category_id', 'pubg_mobile_auto')->where('fazercards_offer_id', '325_uc')->firstOrFail();
        $this->assertSame('active', $gone->status);

        $this->pubgOffers = [
            ['offer_id' => '60_uc', 'name' => '60 UC', 'price_usd' => '0.90'],
        ];
        $this->fakeFazer();
        $result = $this->app->make(FazerCardsCatalogSync::class)->sync();
        $this->assertGreaterThanOrEqual(1, $result['hiddenSkus']);

        $gone->refresh();
        $this->assertSame('hidden', $gone->status);
        $this->assertFalse((bool) $gone->visible);

        $alive = Sku::query()->where('fazercards_category_id', 'pubg_mobile_auto')->where('fazercards_offer_id', '60_uc')->firstOrFail();
        $this->assertSame('active', $alive->status);
        $this->assertSame('0.90', (string) $alive->live_cost_usd);

        $detail = $this->getJson('/api/catalog/products/'.$alive->product->handle);
        $detail->assertOk();
        $ids = collect($detail->json('data.skus'))->pluck('id')->all();
        $this->assertContains($alive->id, $ids);
        $this->assertNotContains($gone->id, $ids);
    }

    public function test_steam_login_check_requires_can_refill(): void
    {
        $this->app->make(FazerCardsCatalogSync::class)->sync('steam-topup');
        $sku = Sku::query()->where('fulfillment_type', 'steam-topup')->firstOrFail();
        $adapter = $this->app->make(FazerCardsAdapter::class);
        Http::fake(fn () => Http::response([
            'ok' => true,
            'can_refill' => false,
            'unverified' => false,
        ], 200));
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('این لاگین استیم قابل شارژ نیست.');
        $adapter->validatePlayer($sku, ['steamLogin' => 'steamuser123']);
    }

    public function test_topup_order_requires_category_and_offer(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('شناسهٔ دسته و بسته لازم است.');
        $this->app->make(FazerCardsClient::class)->topupOrder([
            'category_id' => 'pubg_mobile_auto',
            'offer_id' => '',
            'fields' => ['player_id' => '1'],
            'confirm' => true,
        ]);
    }

    public function test_manual_order_requires_service_and_product(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('شناسهٔ سرویس و محصول لازم است.');
        $this->app->make(FazerCardsClient::class)->manualServiceOrder([
            'manual_service_id' => 'ms-boost',
            'product_id' => '',
            'fields' => [],
        ], ['confirm' => true]);
    }

    public function test_manual_chat_posts_multipart_body(): void
    {
        Http::fake([
            'https://api.fzr.cards/api/v2/manual-services/orders/ord-1/chat' => Http::response([
                'ok' => true,
                'message' => ['sender' => 'me', 'body' => 'سلام', 'created_at' => '2026-09-30T00:00:00.000Z'],
            ], 200),
        ]);
        $this->app->make(FazerCardsClient::class)->manualChatPost('ord-1', 'سلام');
        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_contains((string) $request->url(), '/manual-services/orders/ord-1/chat')
                && $request->isMultipart();
        });
    }

    public function test_steam_gift_order_requires_invite_and_ids(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('لینک دعوت استیم نامعتبر است.');
        $this->app->make(FazerCardsClient::class)->steamGiftOrder([
            'invite_url' => 'https://steamcommunity.com/id/x',
            'sub_id' => 123,
            'app_id' => 570,
            'region' => 'US',
        ], ['confirm' => true]);
    }

    public function test_light_refresh_counts_api_exception_and_continues_chunk(): void
    {
        Product::query()
            ->whereIn('fulfillment_type', [FazerCardsIds::TOPUP, FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY])
            ->update(['visible' => false]);

        $fail = Product::query()->create([
            'handle' => 'light-fail-topup',
            'code' => 'light-fail-topup',
            'title' => 'Light Fail',
            'name_en' => 'Light Fail',
            'publisher' => '',
            'category_name' => 'شارژ بازی',
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fulfillment_type' => FazerCardsIds::TOPUP,
            'fazercards_category_id' => 'light_fail_cat',
            'visible' => true,
            'status' => 'active',
        ]);
        $ok = Product::query()->create([
            'handle' => 'light-ok-topup',
            'code' => 'light-ok-topup',
            'title' => 'Light Ok',
            'name_en' => 'Light Ok',
            'publisher' => '',
            'category_name' => 'شارژ بازی',
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fulfillment_type' => FazerCardsIds::TOPUP,
            'fazercards_category_id' => 'light_ok_cat',
            'visible' => true,
            'status' => 'active',
        ]);

        $this->mock(FazerCardsClient::class, function ($mock): void {
            $mock->shouldReceive('fetch')->andReturnUsing(function (string $path, string $method = 'GET', array $query = []) {
                if ($path === 'topups/offers' && ($query['category_id'] ?? '') === 'light_fail_cat') {
                    throw new ApiException('Fazer down', 503, 'SUPPLIER_UNAVAILABLE');
                }
                if ($path === 'steam-topup/rates') {
                    return [
                        'status' => 200,
                        'latencyMs' => 1,
                        'data' => [
                            'ok' => true,
                            'base' => 'USD',
                            'rates' => ['USD' => 1, 'RUB' => 80, 'UAH' => 40, 'KZT' => 400],
                        ],
                    ];
                }
                if ($path === 'topups/offers') {
                    return [
                        'status' => 200,
                        'latencyMs' => 1,
                        'data' => [
                            'ok' => true,
                            'kind' => 'topup',
                            'category_id' => $query['category_id'] ?? '',
                            'name' => 'Light Ok',
                            'imageurl' => 'https://cdn.example/ok.jpg',
                            'offers' => [
                                ['offer_id' => 'ok_pack', 'name' => 'OK Pack', 'price_usd' => '1.25'],
                            ],
                            'fields' => [
                                ['key' => 'player_id', 'label' => 'Player ID', 'type' => 'text'],
                            ],
                        ],
                    ];
                }

                return ['status' => 200, 'latencyMs' => 1, 'data' => ['ok' => true]];
            });
        });

        $result = $this->app->make(FazerCardsCatalogSync::class)->lightRefresh();

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['failed']);
        $this->assertGreaterThanOrEqual(1, $result['products']);
        $this->assertGreaterThanOrEqual(1, $result['skus']);

        $okSku = Sku::query()
            ->where('product_id', $ok->id)
            ->where('fazercards_offer_id', 'ok_pack')
            ->first();
        $this->assertNotNull($okSku);
        $this->assertSame('1.25', (string) $okSku->live_cost_usd);
        $this->assertNotNull($okSku->synced_at);

        $this->assertSame(0, Sku::query()->where('product_id', $fail->id)->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function topupOffersPayload(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $categoryId = (string) ($query['category_id'] ?? '');
        $blood = $categoryId === 'blood_strike';
        $wild = $categoryId === 'wild_rift_vn';
        $note = $blood
            ? 'شناسه بازیکن لازم است'
            : ($wild
                ? "Region: Vietnam\nLeague of Legends: Wild Rift top-up.\nEnter your Riot ID in the Player ID field."
                : 'UC');

        return [
            'ok' => true,
            'kind' => 'topup',
            'category_id' => $categoryId !== '' ? $categoryId : 'pubg_mobile_auto',
            'name' => $blood ? 'Blood Strike' : ($wild ? 'Wild Rift VN' : 'PUBG Mobile Auto'),
            'note' => $note,
            'imageurl' => $blood ? '/covers/blood.jpg' : 'https://cdn.example/pubg.jpg',
            'offers' => [
                ...$this->pubgOffers,
                ['offer_id' => '', 'name' => 'Broken', 'price_usd' => '1'],
            ],
            'fields' => [
                ['key' => 'player_id', 'label' => 'Player ID', 'type' => 'text'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manualOffersPayload(string $path): array
    {
        $parts = explode('/', $path);
        $serviceId = $parts[1] ?? 'ms-boost';

        return [
            'ok' => true,
            'manual_service_id' => $serviceId,
            'category' => [
                'id' => $serviceId,
                'name' => 'Boost Service',
                'kind' => 'replenishment',
                'chat' => true,
            ],
            'items' => [
                ['id' => 'boost-1', 'name' => 'Boost', 'price_usd' => '5', 'delivery_minutes' => 60, 'note_product' => ''],
                ['id' => '', 'name' => 'Broken', 'price_usd' => '1'],
            ],
            'imageurl' => 'https://cdn.example/manual.jpg',
            'info' => 'متن مشتری',
            'fields' => [
                ['code' => 'player_id', 'name' => 'Player ID'],
            ],
        ];
    }

    private function fakeFazer(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', $path), '/');

            return match (true) {
                $path === 'me' => Http::response([
                    'ok' => true,
                    'email' => 'ops@example.com',
                    'login' => 'lab',
                    'plan' => 'gold',
                    'planExpiresAt' => '2026-10-03',
                    'subscriptionActive' => true,
                ], 200),
                $path === 'balance' => Http::response(['ok' => true, 'balance' => '12.0000'], 200),
                $path === 'topups' => Http::response([
                    'ok' => true,
                    'kind' => 'topup',
                    'items' => [
                        [
                            'category_id' => 'pubg_mobile_auto',
                            'name' => 'PUBG Mobile Auto',
                            'note' => 'UC',
                            'imageurl' => 'https://cdn.example/pubg.jpg',
                        ],
                        [
                            'category_id' => 'blood_strike',
                            'name' => 'Blood Strike',
                            'note' => 'شناسه بازیکن لازم است',
                            'imageurl' => '/covers/blood.jpg',
                        ],
                        [
                            'category_id' => 'wild_rift_vn',
                            'name' => 'Wild Rift VN',
                            'note' => "Region: Vietnam\nLeague of Legends: Wild Rift top-up.\nEnter your Riot ID in the Player ID field.",
                            'imageurl' => 'https://cdn.example/wild.jpg',
                        ],
                        ['category_id' => 'vpn_proxy', 'name' => 'VPN Proxy'],
                    ],
                    'meta' => ['has_more' => false],
                ], 200),
                $path === 'topups/offers' => Http::response($this->topupOffersPayload($request), 200),
                $path === 'giftcards' => Http::response([
                    'ok' => true,
                    'kind' => 'gift_card',
                    'items' => [
                        [
                            'category_id' => 'steam',
                            'name' => 'Steam',
                            'note' => 'کد به حساب می‌آید',
                            'imageurl' => 'https://cdn.example/steam-card.jpg',
                        ],
                    ],
                    'meta' => ['has_more' => false],
                ], 200),
                $path === 'giftcards/cards' => Http::response([
                    'ok' => true,
                    'kind' => 'gift_card',
                    'category_id' => 'steam',
                    'name' => 'Steam',
                    'imageurl' => 'https://cdn.example/steam-card.jpg',
                    'offers' => [
                        ['card_id' => 'steam-10', 'name' => 'Steam 10', 'price_usd' => '10', 'stock' => 4],
                        ['card_id' => 'steam-gone', 'name' => 'Gone', 'price_usd' => '25', 'stock' => 0],
                    ],
                ], 200),
                $path === 'gamekeys' => Http::response([
                    'ok' => true,
                    'kind' => 'game_key',
                    'items' => [
                        [
                            'game_id' => 'elden',
                            'name' => 'Elden Ring',
                            'region' => 'Global',
                            'platform' => 'Steam',
                            'appid' => 1245620,
                            'imageurl' => 'https://cdn.example/elden.jpg',
                        ],
                    ],
                    'meta' => ['has_more' => false],
                ], 200),
                $path === 'gamekeys/keys' => Http::response([
                    'ok' => true,
                    'kind' => 'game_key',
                    'game_id' => 'elden',
                    'GameName' => 'Elden Ring',
                    'platform' => 'Steam',
                    'region' => 'Global',
                    'imageurl' => 'https://cdn.example/elden.jpg',
                    'keys' => [
                        ['key_id' => 'er-std', 'name' => 'Standard', 'price_usd' => '40', 'stock' => 5, 'min_order_quantity' => 1, 'max_order_quantity' => 8],
                        ['key_id' => 'er-bulk', 'name' => 'Bulk', 'price_usd' => '35', 'stock' => 8, 'min_order_quantity' => 5, 'max_order_quantity' => 20],
                        ['key_id' => 'er-oos', 'name' => 'Out of stock', 'price_usd' => '40', 'stock' => 0],
                        ['key_id' => null, 'name' => 'Broken', 'price_usd' => '10', 'stock' => 3],
                    ],
                ], 200),
                $path === 'steam-topup/rates' => Http::response([
                    'ok' => true,
                    'base' => 'USD',
                    'rates' => [
                        'USD' => 1,
                        'RUB' => 80,
                        'UAH' => 40,
                        'KZT' => 400,
                    ],
                    'updated_at' => '2026-09-30T00:00:00.000Z',
                ], 200),
                $path === 'telegram/stars' => Http::response([
                    'ok' => true,
                    'kind' => 'telegram_stars',
                    'price_per_star' => '0.016',
                    'min_amount' => 50,
                    'max_amount' => 10000,
                    'rates_updated_at' => '2026-09-30T00:00:00.000Z',
                ], 200),
                $path === 'telegram/premium' => Http::response([
                    'ok' => true,
                    'kind' => 'telegram_premium',
                    'plans' => [
                        ['months' => 3, 'price_usd' => '12'],
                        ['months' => 6, 'price_usd' => '16'],
                        ['months' => 12, 'price_usd' => '29'],
                    ],
                    'rates_updated_at' => '2026-09-30T00:00:00.000Z',
                ], 200),
                $path === 'funpay/me' => Http::response([
                    'ok' => true,
                    'user' => [
                        'email' => 'ops@example.com',
                        'login' => 'lab',
                        'plan' => 'gold',
                        'subscriptionActive' => true,
                        'balanceUsd' => '12.0000',
                    ],
                    'addon' => [
                        'active' => true,
                        'status' => 'active',
                    ],
                ], 200),
                $path === 'steam-gifts/games' => Http::response([
                    'ok' => true,
                    'games' => [
                        ['name' => 'Dota 2', 'appid' => 570],
                    ],
                    'meta' => ['total' => 1, 'returned' => 1, 'truncated' => false],
                ], 200),
                preg_match('#^steam-gifts/games/\d+$#', $path) === 1 => Http::response([
                    'ok' => true,
                    'appid' => 570,
                    'offers' => [
                        [
                            'sub_id' => 123,
                            'name' => 'Dota 2',
                            'regions' => [
                                ['region' => 'US', 'price' => '4.99'],
                                ['region' => 'EU', 'price' => '5.49'],
                            ],
                        ],
                        ['sub_id' => 0, 'name' => 'Broken', 'regions' => [['region' => 'US', 'price' => '1']]],
                    ],
                ], 200),
                str_starts_with($path, 'manual-services/') && str_ends_with($path, '/offers') => Http::response($this->manualOffersPayload($path), 200),
                $path === 'manual-services' => Http::response([
                    'ok' => true,
                    'items' => [
                        [
                            'id' => 'ms-boost',
                            'name' => 'Boost Service',
                            'kind' => 'replenishment',
                            'chat' => true,
                            'info' => 'متن لیست',
                            'imageurl' => 'https://cdn.example/manual.jpg',
                        ],
                    ],
                ], 200),
                default => Http::response(['ok' => true, 'items' => [], 'meta' => ['has_more' => false]], 200),
            };
        });
    }
}

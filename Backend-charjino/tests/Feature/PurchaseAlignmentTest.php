<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\AdminAlert;
use App\Models\CatalogGroup;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Sku;
use App\Models\User;
use App\Modules\Catalog\PurchaseSchema;
use App\Modules\Fulfillment\FazerOrderOutcome;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsValidateId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseAlignmentTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $posted = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Setting::putValue('usd_toman_rate', '100000');
        config([
            'charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd',
            'charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2',
            'charjino.supplier.fazercards_allow_live_order' => true,
            'charjino.orders.mock_payments' => true,
        ]);
        $this->posted = [];
        Http::fake(function (Request $request) {
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($request->url(), PHP_URL_PATH)), '/');
            $this->posted[] = [
                'path' => $path,
                'method' => $request->method(),
                'body' => $request->data(),
                'idempotency' => $request->header('Idempotency-Key')[0] ?? null,
            ];
            if ($path === 'topups/validate-id' && $request->method() === 'GET') {
                return Http::response([
                    'ok' => true,
                    'items' => [
                        ['category_id' => 'pubg_mobile', 'fields' => [['key' => 'player_id']]],
                    ],
                ], 200);
            }
            if ($path === 'topups/validate-id' && $request->method() === 'POST') {
                $id = (string) data_get($request->data(), 'fields.player_id', '');
                if ($id === 'bad') {
                    return Http::response(['ok' => false, 'error' => ['code' => 'ID_NOT_CONFIRMED']], 422);
                }
                if ($id === 'down') {
                    return Http::response(['ok' => false, 'error' => ['message' => 'busy']], 503);
                }

                return Http::response([
                    'ok' => true,
                    'valid' => true,
                    'player_name' => 'PUBG Hero',
                    'player_id' => $id,
                ], 200);
            }
            if ($path === 'steam-topup/check-login') {
                $login = (string) ($request->data()['steamLogin'] ?? '');
                if ($login === 'norefill') {
                    return Http::response(['ok' => true, 'can_refill' => false], 200);
                }
                if ($login === 'pending') {
                    return Http::response(['ok' => true, 'can_refill' => false, 'unverified' => true], 200);
                }

                return Http::response(['ok' => true, 'can_refill' => true, 'persona' => 'Pink'], 200);
            }
            if ($path === 'balance') {
                return Http::response(['ok' => true, 'balance' => '1000.0000', 'currency' => 'USD'], 200);
            }
            if ($path === 'topups/offers') {
                return Http::response(['ok' => true, 'offers' => [['offer_id' => 'uc-60', 'price_usd' => '1.00']]], 200);
            }
            if ($path === 'telegram/stars/buy') {
                throw new ConnectionException('timeout');
            }

            return Http::response(['ok' => true, 'order' => ['id' => 'ord-1', 'status' => 'created']], 201);
        });
    }

    public function test_pubg_valid_stores_player_name_and_422_blocks_draft(): void
    {
        Cache::put('fazer:validate-id:catalog', [
            ['category_id' => 'pubg_mobile', 'fields' => [['key' => 'player_id']]],
        ], 900);
        $sku = $this->topupSku('pubg_mobile', 'player_id', 'uc-60');
        $token = $this->shopToken();

        $ok = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['player_id' => '5123456789'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()]);
        $ok->assertOk();
        $this->assertSame('PUBG Hero', $ok->json('data.playerName'));

        $bad = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['player_id' => 'bad'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()]);
        $bad->assertStatus(422)->assertJsonPath('error.code', 'ID_NOT_CONFIRMED');
    }

    public function test_pubg_503_requires_ack_and_does_not_store_player_name(): void
    {
        Cache::put('fazer:validate-id:catalog', [
            ['category_id' => 'pubg_mobile', 'fields' => [['key' => 'player_id']]],
        ], 900);
        $sku = $this->topupSku('pubg_mobile', 'player_id', 'uc-60');
        $token = $this->shopToken();

        $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['player_id' => 'down'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ID_ACK_REQUIRED');

        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['player_id' => 'down', 'id_ack' => '1'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()]);
        $create->assertOk();
        $this->assertNull($create->json('data.playerName'));
        $order = Order::query()->where('number', $create->json('data.number'))->firstOrFail();
        $this->assertSame('1', $order->requirements['id_unchecked'] ?? null);
    }

    public function test_validate_id_list_add_and_remove_changes_mode(): void
    {
        $this->topupSku('pubg_mobile', 'player_id', 'uc-60', 'pubg-align');
        Cache::put('fazer:validate-id:catalog', [
            ['category_id' => 'free_fire', 'fields' => [['key' => 'player_id']]],
        ], 900);
        $this->assertTrue(FazerCardsValidateId::needsAck('pubg_mobile'));
        $this->getJson('/api/catalog/products/pubg-align')
            ->assertOk()
            ->assertJsonPath('data.purchaseSchema.validation.mode', 'ack');

        Cache::put('fazer:validate-id:catalog', [
            ['category_id' => 'pubg_mobile', 'fields' => [['key' => 'player_id']]],
        ], 900);
        $this->assertFalse(FazerCardsValidateId::needsAck('pubg_mobile'));
        $this->getJson('/api/catalog/products/pubg-align')
            ->assertOk()
            ->assertJsonPath('data.purchaseSchema.validation.mode', 'fazer');
    }

    public function test_validate_id_result_is_cached_on_create_draft(): void
    {
        Cache::put('fazer:validate-id:catalog', [
            ['category_id' => 'pubg_mobile', 'fields' => [['key' => 'player_id']]],
        ], 900);
        $sku = $this->topupSku('pubg_mobile', 'player_id', 'uc-60', 'pubg-cache');
        $token = $this->shopToken('09123456781');
        $payload = [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['player_id' => '5123456789'],
        ];
        $this->postJson('/api/orders', $payload, [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();
        $this->postJson('/api/orders', $payload, [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();
        $posts = array_values(array_filter(
            $this->posted,
            fn ($row) => $row['path'] === 'topups/validate-id' && $row['method'] === 'POST',
        ));
        $this->assertCount(1, $posts);
    }

    public function test_steam_cannot_refill_and_unverified_allow(): void
    {
        $sku = $this->familySku(FazerCardsIds::STEAM_TOPUP, 'wallet', 'steam-topup:free', [['key' => 'steamLogin']]);
        $token = $this->shopToken();

        $this->withToken($token)->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => ['steamLogin' => 'norefill'],
        ])->assertStatus(400)->assertJsonPath('error.code', 'STEAM_LOGIN_CANNOT_REFILL');

        $check = $this->withToken($token)->postJson('/api/checkout/validate-player', [
            'skuId' => $sku->id,
            'requirements' => ['steamLogin' => 'pending'],
        ]);
        $check->assertOk()->assertJsonPath('data.unverified', true);
    }

    public function test_steam_gift_requires_region_ack(): void
    {
        $sku = $this->familySku(FazerCardsIds::STEAM_GIFT, '730', '42|US', [['key' => 'invite_url']]);
        $sku->region = 'US';
        $sku->save();
        $token = $this->shopToken();

        $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['invite_url' => 'https://s.team/p/abcd-efgh'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'REGION_ACK_REQUIRED');
    }

    public function test_giftcard_completed_without_codes_needs_review(): void
    {
        $order = $this->paidOrder(FazerCardsIds::GIFTCARD);
        $attempt = $this->attempt($order, FazerCardsIds::GIFTCARD);
        $this->app->make(FazerOrderOutcome::class)->applyParsed($attempt, [
            'orderId' => 'fz-gc',
            'status' => 'completed',
            'cards' => [],
            'keys' => [],
            'raw' => ['status' => 'completed'],
        ]);
        $this->assertSame(Order::STATUS_NEEDS_REVIEW, $order->fresh()->status);
    }

    public function test_steam_refund_marks_order_failed_for_customer(): void
    {
        $order = $this->paidOrder(FazerCardsIds::STEAM_TOPUP);
        $attempt = $this->attempt($order, FazerCardsIds::STEAM_TOPUP);
        $this->app->make(FazerOrderOutcome::class)->applyParsed($attempt, [
            'orderId' => 'fz-steam-refund',
            'status' => 'refunded',
            'failReason' => 'unknown steam login',
            'cards' => [],
            'keys' => [],
            'raw' => ['status' => 'refunded'],
        ]);
        $fresh = $order->fresh();
        $this->assertTrue(in_array($fresh->status, [Order::STATUS_FAILED, Order::STATUS_REFUND_PENDING], true));
    }

    public function test_telegram_timeout_does_not_retry(): void
    {
        $sku = $this->familySku(FazerCardsIds::TELEGRAM_STARS, 'stars', '50', [['key' => 'telegram_username']]);
        try {
            $this->app->make(FazerCardsAdapter::class)->placeOrder(
                $sku,
                ['telegram_username' => 'pinkcat'],
                '0.80',
                'idem-stars',
                true,
                1,
            );
            $this->fail('telegram timeout should surface');
        } catch (ApiException $e) {
            $this->assertSame('SUPPLIER_UNREACHABLE', $e->errorCode);
        }
        $buys = array_values(array_filter($this->posted, fn ($row) => $row['path'] === 'telegram/stars/buy'));
        $this->assertCount(1, $buys);
        $this->assertNull($buys[0]['idempotency']);
    }

    public function test_same_idempotency_key_returns_one_mock_order(): void
    {
        $this->posted = [];
        $seen = [];
        Http::fake(function (Request $request) use (&$seen) {
            $path = ltrim((string) preg_replace('#^/api/v2/#', '', (string) parse_url($request->url(), PHP_URL_PATH)), '/');
            $this->posted[] = [
                'path' => $path,
                'method' => $request->method(),
                'body' => $request->data(),
                'idempotency' => $request->header('Idempotency-Key')[0] ?? null,
            ];
            if ($path === 'balance') {
                return Http::response(['ok' => true, 'balance' => '1000.0000', 'currency' => 'USD'], 200);
            }
            if ($path !== 'topups/order') {
                return Http::response(['ok' => true], 200);
            }
            $key = (string) ($request->header('Idempotency-Key')[0] ?? '');
            if (isset($seen[$key])) {
                return Http::response(['ok' => true, 'order' => $seen[$key]], 200);
            }
            $seen[$key] = ['id' => 'ord-'.$key, 'status' => 'created'];

            return Http::response(['ok' => true, 'order' => $seen[$key]], 201);
        });
        $sku = $this->topupSku('pubg_mobile', 'player_id', 'uc-60', 'pubg-idem');
        $first = $this->app->make(FazerCardsAdapter::class)->placeOrder(
            $sku,
            ['player_id' => '5123456789'],
            '1.00',
            'stable-key-1',
            true,
            1,
        );
        $second = $this->app->make(FazerCardsAdapter::class)->placeOrder(
            $sku,
            ['player_id' => '5123456789'],
            '1.00',
            'stable-key-1',
            true,
            1,
        );
        $this->assertSame($first['orderId'], $second['orderId']);
        $this->assertNotSame('', $first['orderId']);
        $orders = array_values(array_filter($this->posted, fn ($row) => $row['path'] === 'topups/order'));
        $this->assertNotEmpty($orders);
        foreach ($orders as $row) {
            $this->assertSame('stable-key-1', $row['idempotency']);
        }
    }

    public function test_unknown_fazer_status_alerts_admin(): void
    {
        $order = $this->paidOrder(FazerCardsIds::TOPUP);
        $attempt = $this->attempt($order, FazerCardsIds::TOPUP);
        $this->app->make(FazerOrderOutcome::class)->applyParsed($attempt, [
            'orderId' => 'fz-unknown',
            'status' => 'queued_mystery',
            'cards' => [],
            'keys' => [],
            'raw' => ['status' => 'queued_mystery'],
        ]);
        $this->assertSame(FulfillmentAttempt::STATUS_UNKNOWN, $attempt->fresh()->status);
        $this->assertTrue(AdminAlert::query()->where('type', 'FAZER_STATUS_UNKNOWN')->exists());
    }

    public function test_gamekey_qty_over_cap_is_rejected(): void
    {
        $sku = $this->familySku(FazerCardsIds::GAMEKEY, 'elden', 'std', []);
        $token = $this->shopToken('09123456782');
        $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 101,
            'requirements' => [],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422);
    }

    private function topupSku(string $category, string $field, string $offer, ?string $handle = null): Sku
    {
        return $this->familySku(FazerCardsIds::TOPUP, $category, $offer, [['key' => $field]], $handle);
    }

    /**
     * @param  list<array<string, mixed>>  $inputs
     */
    private function familySku(string $family, string $category, string $offer, array $inputs, ?string $handle = null): Sku
    {
        $handle ??= $family.'-'.$category.'-'.Str::random(4);
        $group = CatalogGroup::query()->first() ?? CatalogGroup::query()->create([
            'name' => 'Games',
            'title' => 'بازی',
            'slug' => 'games-align',
            'visible' => true,
            'sort_order' => 1,
        ]);
        $product = Product::query()->updateOrCreate(
            ['handle' => $handle],
            [
                'catalog_group_id' => $group->id,
                'code' => $handle,
                'title' => $category,
                'name_en' => $category,
                'publisher' => 'Test',
                'category_name' => 'تست',
                'subtitle' => '',
                'thumbnail' => '/image/catalog/cod-mobile.svg',
                'fazercards_category_id' => $category,
                'fulfillment_type' => $family,
                'visible' => true,
                'status' => 'active',
                'inputs' => $inputs,
                'purchase_schema' => PurchaseSchema::build($family, $inputs, [
                    'validateCategoryId' => $category,
                ]),
            ],
        );

        return Sku::query()->updateOrCreate(
            ['code' => $offer.'-'.$handle],
            [
                'product_id' => $product->id,
                'name' => '60 UC',
                'price' => 10000,
                'cost_usd' => '1.00',
                'fazercards_category_id' => $category,
                'fazercards_offer_id' => $offer,
                'fulfillment_type' => $family,
                'status' => 'active',
                'visible' => true,
                'synced_at' => now(),
            ],
        );
    }

    private function paidOrder(string $family): Order
    {
        $sku = $this->familySku($family, $family.'-cat', 'off-'.Str::random(4), []);
        $user = User::query()->create([
            'phone' => '0912'.random_int(1000000, 9999999),
            'role' => 'user',
            'status' => 'active',
        ]);
        $order = new Order;
        $order->user_id = $user->id;
        $order->sku_id = $sku->id;
        $order->product_id = $sku->product_id;
        $order->quantity = 1;
        $order->requirements = [];
        $order->amount_toman = 10000;
        $order->cost_usd_snapshot = '1.00';
        $order->fx_rate_snapshot = '100000';
        $order->status = Order::STATUS_PAID;
        $order->fulfillment_type = $family;
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', $family);
        $order->save();

        return $order;
    }

    private function attempt(Order $order, string $kind): FulfillmentAttempt
    {
        return FulfillmentAttempt::query()->create([
            'order_id' => $order->id,
            'attempt_no' => 1,
            'idempotency_key' => 'idem-'.$order->id,
            'idempotent' => true,
            'fazer_kind' => $kind,
            'supplier' => 'fazercards',
            'supplier_order_id' => (string) Str::uuid(),
            'status' => FulfillmentAttempt::STATUS_PROCESSING,
            'dry_run' => false,
        ]);
    }

    private function shopToken(string $phone = '09123456780'): string
    {
        $this->postJson('/api/auth/request-otp', ['phoneNumber' => $phone])->assertOk();
        $verify = $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => $phone,
            'code' => '123456',
        ]);
        $verify->assertOk();

        return (string) $verify->json('data.access_token');
    }
}

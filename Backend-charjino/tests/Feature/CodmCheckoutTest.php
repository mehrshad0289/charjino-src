<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CodmCheckoutTest extends TestCase
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
            if ($path === 'topups/validate-id' && $request->method() === 'GET') {
                return Http::response([
                    'ok' => true,
                    'items' => [
                        ['category_id' => 'pubg_mobile', 'fields' => [['key' => 'player_id']]],
                    ],
                ], 200);
            }
            if ($path === 'balance') {
                return Http::response(['ok' => true, 'balance' => '1000.0000', 'currency' => 'USD'], 200);
            }
            if ($path === 'topups/offers') {
                return Http::response([
                    'ok' => true,
                    'offers' => [
                        ['offer_id' => 'cp-400', 'price_usd' => '1.00'],
                        ['offer_id' => 'offer-us', 'price_usd' => '1.00'],
                        ['offer_id' => 'offer-sg', 'price_usd' => '1.00'],
                        ['offer_id' => 'offer-ca', 'price_usd' => '1.00'],
                        ['offer_id' => 'offer-id', 'price_usd' => '1.00'],
                    ],
                ], 200);
            }
            $this->posted[] = [
                'path' => $path,
                'body' => $request->data(),
                'idempotency' => $request->header('Idempotency-Key')[0] ?? null,
            ];

            return Http::response(['ok' => true, 'order' => ['id' => 'ord-codm', 'status' => 'created']], 201);
        });
    }

    public function test_draft_without_id_ack_is_rejected(): void
    {
        $sku = $this->codmSku('codm_activision_us', 'user_id', 'cp-400');
        $token = $this->shopToken();

        $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['user_id' => '73871234'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ID_ACK_REQUIRED');
    }

    public function test_draft_with_ack_does_not_store_raw_id_as_player_name(): void
    {
        $sku = $this->codmSku('codm_activision_us', 'user_id', 'cp-400');
        $token = $this->shopToken();

        $create = $this->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['user_id' => '73871234', 'id_ack' => '1'],
        ], ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()]);

        $create->assertOk()->assertJsonPath('data.status', Order::STATUS_DRAFT);
        $this->assertNull($create->json('data.playerName'));
        $this->assertSame('73871234', $create->json('data.playerId'));
        $this->assertSame('Activision آمریکا', $create->json('data.versionLabel'));
        $order = Order::query()->where('number', $create->json('data.number'))->firstOrFail();
        $this->assertNull($order->player_name);
        $this->assertSame('1', $order->requirements['id_ack'] ?? null);
        $this->assertSame('codm_activision_us', $order->requirements['version'] ?? null);
        $this->assertNotEmpty($order->requirements['id_ack_at'] ?? null);
    }

    public function test_activision_sends_user_id_and_garena_sends_player_id(): void
    {
        $activision = $this->codmSku('codm_activision_us', 'user_id', 'offer-us');
        $garena = $this->codmSku('codm_garena_sgmy', 'player_id', 'offer-sg');
        $adapter = $this->app->make(FazerCardsAdapter::class);

        $adapter->placeOrder($activision, ['user_id' => '73871234', 'id_ack' => '1', 'version' => 'codm_activision_us'], '1.00', 'idem-us', true, 1);
        $this->assertSame(['user_id' => '73871234'], $this->last()['body']['fields']);
        $this->assertSame('codm_activision_us', $this->last()['body']['category_id']);
        $this->assertSame('offer-us', $this->last()['body']['offer_id']);

        $adapter->placeOrder($garena, ['player_id' => '99112233', 'id_ack' => '1', 'version' => 'codm_garena_sgmy'], '1.00', 'idem-sg', true, 1);
        $this->assertSame(['player_id' => '99112233'], $this->last()['body']['fields']);
        $this->assertSame('codm_garena_sgmy', $this->last()['body']['category_id']);

        $indonesia = $this->codmSku('codm_garena_indonesia', 'open_id', 'offer-id');
        $adapter->placeOrder($indonesia, ['open_id' => '55112233', 'id_ack' => '1', 'version' => 'codm_garena_indonesia'], '1.00', 'idem-id', true, 1);
        $this->assertSame(['open_id' => '55112233'], $this->last()['body']['fields']);
        $this->assertSame('codm_garena_indonesia', $this->last()['body']['category_id']);
    }

    public function test_retry_keeps_the_same_idempotency_key(): void
    {
        $sku = $this->codmSku('codm_activision_ca', 'user_id', 'offer-ca');
        $adapter = $this->app->make(FazerCardsAdapter::class);
        $adapter->placeOrder($sku, ['user_id' => '11112222'], '1.00', 'idem-stable', true, 1);
        $adapter->placeOrder($sku, ['user_id' => '11112222'], '1.00', 'idem-stable', true, 1);
        $this->assertSame('idem-stable', $this->posted[0]['idempotency']);
        $this->assertSame('idem-stable', $this->posted[1]['idempotency']);
        $this->assertSame('topups/order', $this->posted[0]['path']);
        $this->assertSame('topups/order', $this->posted[1]['path']);
    }

    public function test_product_page_exposes_versions_and_ack_mode(): void
    {
        $this->codmSku('codm_activision_us', 'user_id', 'offer-us', 'cod-mobile-us');
        $this->codmSku('codm_garena_indonesia', 'player_id', 'offer-id', 'cod-mobile-id');

        $detail = $this->getJson('/api/catalog/products/cod-mobile-us');
        $detail->assertOk()
            ->assertJsonPath('data.purchaseSchema.validation.mode', 'codm_store')
            ->assertJsonPath('data.purchaseSchema.fields.0.key', 'user_id')
            ->assertJsonPath('data.purchaseSchema.fields.0.pattern', '^\d{5,25}$');
        $labels = collect($detail->json('data.codm.versions'))->pluck('label')->all();
        $this->assertContains('Activision آمریکا', $labels);
        $this->assertContains('Garena اندونزی', $labels);
    }

    public function test_validate_id_catalog_can_enable_codm_api_mode(): void
    {
        Cache::put('fazer:validate-id:catalog', [
            ['category_id' => 'codm_activision_us', 'fields' => [['key' => 'user_id']]],
        ], 900);
        $this->codmSku('codm_activision_us', 'user_id', 'offer-us', 'cod-mobile-live');

        $this->getJson('/api/catalog/products/cod-mobile-live')
            ->assertOk()
            ->assertJsonPath('data.purchaseSchema.validation.mode', 'codm_store');
    }

    public function test_completed_topup_closes_attempt_without_cards(): void
    {
        $order = $this->paidCodmOrder();
        $attempt = $this->attempt($order);
        $this->app->make(FazerOrderOutcome::class)->applyParsed($attempt, [
            'orderId' => 'fz-1',
            'status' => 'completed',
            'cards' => [],
            'keys' => [],
            'raw' => ['status' => 'completed'],
        ]);
        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(FulfillmentAttempt::STATUS_COMPLETED, $attempt->fresh()->status);
    }

    public function test_failed_refunded_cancelled_and_unknown_statuses(): void
    {
        foreach (['failed', 'refunded', 'cancelled'] as $status) {
            $order = $this->paidCodmOrder();
            $attempt = $this->attempt($order);
            $this->app->make(FazerOrderOutcome::class)->applyParsed($attempt, [
                'orderId' => 'fz-'.$status,
                'status' => $status,
                'cards' => [],
                'keys' => [],
                'raw' => ['status' => $status],
            ]);
            $fresh = $order->fresh();
            $this->assertTrue(in_array($fresh->status, [Order::STATUS_FAILED, Order::STATUS_REFUND_PENDING], true), $status);
            $this->assertNotNull($attempt->fresh()->terminal_at);
        }

        $order = $this->paidCodmOrder();
        $attempt = $this->attempt($order);
        $this->app->make(FazerOrderOutcome::class)->applyParsed($attempt, [
            'orderId' => 'fz-weird',
            'status' => 'queued_mystery',
            'cards' => [],
            'keys' => [],
            'raw' => ['status' => 'queued_mystery'],
        ]);
        $this->assertSame(FulfillmentAttempt::STATUS_UNKNOWN, $attempt->fresh()->status);
        $this->assertTrue(AdminAlert::query()->where('type', 'FAZER_STATUS_UNKNOWN')->exists());
    }

    private function last(): array
    {
        $this->assertNotEmpty($this->posted);

        return $this->posted[array_key_last($this->posted)];
    }

    private function codmSku(string $category, string $field, string $offer, ?string $handle = null): Sku
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'شارژ بازی', 'title' => 'شارژ بازی', 'visible' => true, 'sort_order' => 10],
        );
        $handle ??= 'topup-'.$category;
        $product = Product::query()->updateOrCreate(
            ['handle' => $handle],
            [
                'catalog_group_id' => $group->id,
                'code' => $handle,
                'title' => $category,
                'name_en' => $category,
                'publisher' => 'Activision',
                'category_name' => 'شارژ بازی',
                'subtitle' => '',
                'thumbnail' => '/image/catalog/cod-mobile.svg',
                'fazercards_category_id' => $category,
                'fulfillment_type' => FazerCardsIds::TOPUP,
                'visible' => true,
                'status' => 'active',
                'inputs' => [['key' => $field]],
                'purchase_schema' => PurchaseSchema::build(FazerCardsIds::TOPUP, [['key' => $field]], [
                    'validateCategoryId' => $category,
                ]),
            ],
        );

        return Sku::query()->updateOrCreate(
            ['code' => $offer.'-'.$handle],
            [
                'product_id' => $product->id,
                'name' => '400 + 60 CP',
                'price' => 10000,
                'cost_usd' => '1.00',
                'fazercards_category_id' => $category,
                'fazercards_offer_id' => $offer,
                'fulfillment_type' => FazerCardsIds::TOPUP,
                'status' => 'active',
                'visible' => true,
                'synced_at' => now(),
            ],
        );
    }

    private function paidCodmOrder(): Order
    {
        $sku = $this->codmSku('codm_activision_us', 'user_id', 'cp-paid-'.Str::random(6));
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
        $order->requirements = ['user_id' => '73871234', 'id_ack' => '1', 'version' => 'codm_activision_us'];
        $order->amount_toman = 10000;
        $order->cost_usd_snapshot = '1.00';
        $order->fx_rate_snapshot = '100000';
        $order->status = Order::STATUS_PAID;
        $order->fulfillment_type = FazerCardsIds::TOPUP;
        $order->idempotency_key = (string) Str::uuid();
        $order->body_hash = hash('sha256', 'codm');
        $order->save();

        return $order;
    }

    private function attempt(Order $order): FulfillmentAttempt
    {
        return FulfillmentAttempt::query()->create([
            'order_id' => $order->id,
            'attempt_no' => 1,
            'idempotency_key' => 'idem-'.$order->id,
            'idempotent' => true,
            'fazer_kind' => FazerCardsIds::TOPUP,
            'supplier' => 'fazercards',
            'supplier_order_id' => (string) Str::uuid(),
            'status' => FulfillmentAttempt::STATUS_PROCESSING,
            'dry_run' => false,
        ]);
    }

    private function shopToken(string $phone = '09123456789'): string
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

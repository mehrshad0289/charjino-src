<?php

namespace App\Modules\Orders;

use App\Exceptions\ApiException;
use App\Models\AdminAlert;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Modules\Catalog\BuyerIdentity;
use App\Modules\Codm\CodmRegionCheck;
use App\Modules\Codm\CodmRegionResult;
use App\Modules\Coupons\CouponService;
use App\Modules\Fulfillment\FulfillmentGateway;
use App\Modules\Payments\PaymentService;
use App\Modules\Pricing\PricingService;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsPriceFreshness;
use App\Modules\Supplier\FazerCards\FazerCardsValidateId;
use App\Modules\Supplier\FazerCards\FazerIdCheckCache;
use App\Modules\Supplier\FazerCards\SteamTopupAmount;
use App\Support\AdminPage;
use App\Support\CustomerError;
use App\Support\Money;
use App\Support\SafeLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly FulfillmentGateway $gateway,
        private readonly FazerCardsAdapter $fazerCards,
        private readonly OrderQuoteService $quotes,
        private readonly OrderStateMachine $states,
        private readonly CouponService $coupons,
        private readonly CodmRegionCheck $codmRegion,
    ) {}

    /**
     * @param  array<string, mixed>  $requirements
     * @return array{playerName: string, region: string|null, zoneId: string|null, skuId: int}
     */
    public function validatePlayer(int $skuId, array $requirements): array
    {
        $sku = $this->sellableSku($skuId);
        $sku->loadMissing('product');
        $requirePlayer = $sku->product?->needsBuyerIdentity() ?? true;
        $clean = $sku->product
            ? BuyerRequirements::sanitize($requirements, $requirePlayer, $sku->product)
            : BuyerRequirements::sanitize($requirements, $requirePlayer);

        return $this->checkPlayer($sku, $clean);
    }

    public function quoteSubtotalToman(int $skuId, int $quantity = 1, ?string $amount = null, ?string $currency = null): int
    {
        $sku = $this->sellableSku($skuId);
        $sku->loadMissing('product');
        $product = $sku->product;
        $family = FazerCardsIds::familyOf($sku->fulfillment_type, $sku->fazercards_category_id);
        $clean = [];
        if ($family === FazerCardsIds::STEAM_TOPUP) {
            if ($currency) {
                $clean['currency'] = $currency;
            }
            if ($amount) {
                $clean['amount'] = $amount;
            }
        }
        $priced = $this->pricePurchase($sku, $product, $family, $quantity, $clean, $amount, $currency);

        return $priced['amountToman'];
    }

    /**
     * @param  array<string, mixed>  $requirements
     */
    public function createDraft(
        string $userId,
        int $skuId,
        int $quantity,
        array $requirements,
        string $idempotencyKey,
        ?string $amount = null,
        ?string $currency = null,
        ?string $couponCode = null,
    ): Order {
        $sku = $this->sellableSku($skuId);
        $sku->loadMissing('product');
        $product = $sku->product;
        $family = FazerCardsIds::familyOf($sku->fulfillment_type, $sku->fazercards_category_id);
        if ($family === FazerCardsIds::STEAM_TOPUP) {
            if ($currency !== null && $currency !== '') {
                $requirements['currency'] = $currency;
            }
            if ($amount !== null && $amount !== '') {
                $requirements['amount'] = $amount;
            }
        }
        $requirePlayer = $product?->needsBuyerIdentity() ?? true;
        $categoryId = FazerCardsIds::rawCategory($sku->fazercards_category_id);
        $needsAck = $family === FazerCardsIds::TOPUP && FazerCardsValidateId::needsAck($categoryId);
        $ackRaw = $requirements['id_ack'] ?? $requirements['idAck'] ?? null;
        $regionAckRaw = $requirements['region_ack'] ?? $requirements['regionAck'] ?? null;
        unset($requirements['id_ack'], $requirements['idAck'], $requirements['version'], $requirements['region_ack'], $requirements['regionAck'], $requirements['id_unchecked'], $requirements['steam_unverified']);
        $codmVariantEarly = CodmRegionCheck::variantForFazerCategory($categoryId)
            ?? CodmRegionCheck::variantForHandle($product?->handle);
        $codmAckDeferred = $codmVariantEarly !== null;
        if ($needsAck && ! $codmAckDeferred && ! filter_var($ackRaw, FILTER_VALIDATE_BOOLEAN)) {
            throw new ApiException(CustomerError::message('ID_ACK_REQUIRED'), 400, 'ID_ACK_REQUIRED');
        }
        if ($family === FazerCardsIds::STEAM_GIFT && ! filter_var($regionAckRaw, FILTER_VALIDATE_BOOLEAN)) {
            throw new ApiException(CustomerError::message('REGION_ACK_REQUIRED'), 400, 'REGION_ACK_REQUIRED');
        }
        $clean = $product
            ? BuyerRequirements::sanitize($requirements, $requirePlayer, $product)
            : BuyerRequirements::sanitize($requirements, $requirePlayer);
        if ($needsAck) {
            $clean['version'] = $categoryId;
            if (! $codmAckDeferred && filter_var($ackRaw, FILTER_VALIDATE_BOOLEAN)) {
                $clean['id_ack'] = '1';
            }
        }
        if ($family === FazerCardsIds::STEAM_GIFT) {
            $clean['region_ack'] = '1';
        }
        $priced = $this->pricePurchase($sku, $product, $family, $quantity, $clean, $amount, $currency);
        $quantity = $priced['quantity'];
        $usdSnapshot = $priced['usdSnapshot'];
        $amountToman = $priced['amountToman'];
        $clean = $priced['clean'];
        $subtotalToman = $amountToman;
        $discountToman = 0;
        $couponId = null;
        $storedCouponCode = null;
        $normalizedCoupon = strtoupper(trim((string) $couponCode));
        if ($normalizedCoupon !== '') {
            $quoted = $this->coupons->quote($userId, $normalizedCoupon, $subtotalToman);
            $discountToman = $quoted['discountToman'];
            $amountToman = $quoted['payableToman'];
            $couponId = $quoted['coupon']->id;
            $storedCouponCode = $quoted['coupon']->code;
        }
        $bodyHash = hash('sha256', json_encode([
            'skuId' => $sku->id,
            'quantity' => $quantity,
            'requirements' => $clean,
            'couponCode' => $storedCouponCode,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing && $existing->user_id !== $userId) {
            $idempotencyKey = $this->uniquifyIdempotencyKey($idempotencyKey);
            $existing = null;
        }
        if ($existing) {
            if ($existing->body_hash !== $bodyHash) {
                throw new ApiException(CustomerError::message('IDEMPOTENCY_CONFLICT'), 409, 'IDEMPOTENCY_CONFLICT');
            }
            if ($existing->isPayable()) {
                return $existing;
            }
            $this->releaseIdempotencyKey($existing);
        }

        $playerName = null;
        $idUnchecked = false;
        $steamUnverified = false;
        $codmMatched = false;
        $codmVariant = CodmRegionCheck::variantForFazerCategory($sku->fazercards_category_id)
            ?? CodmRegionCheck::variantForHandle($product?->handle);
        $shouldCheckIdentity = $requirePlayer && $this->shouldCheckIdentityOnCreate($family)
            && (! $needsAck || $codmVariant !== null);
        if ($shouldCheckIdentity) {
            try {
                $validated = $this->checkPlayer($sku, $clean);
                $playerName = $validated['playerName'] !== '' ? $validated['playerName'] : null;
                $steamUnverified = ! empty($validated['unverified']);
                $codmStatus = is_array($validated['codmRegion'] ?? null) ? ($validated['codmRegion']['status'] ?? null) : null;
                if ($codmStatus === CodmRegionResult::MATCH || (($validated['validateMode'] ?? '') === 'codm_store' && ($validated['validation'] ?? '') === 'ok')) {
                    $codmMatched = true;
                    $clean['codm_region'] = CodmRegionResult::MATCH;
                    if (is_string($validated['region'] ?? null) && $validated['region'] !== '') {
                        $clean['codm_account_country'] = $validated['region'];
                    }
                } elseif ($codmStatus === CodmRegionResult::UNKNOWN) {
                    $clean['codm_region'] = CodmRegionResult::UNKNOWN;
                }
            } catch (ApiException $e) {
                if (in_array($e->errorCode, ['CODM_REGION_MISMATCH', 'PLAYER_ID_INVALID'], true)) {
                    throw $e;
                }
                if ($e->errorCode === 'ID_NOT_CONFIRMED' || $e->status === 422) {
                    throw new ApiException(CustomerError::message('ID_NOT_CONFIRMED'), 422, 'ID_NOT_CONFIRMED');
                }
                if ($e->errorCode === 'STEAM_LOGIN_CANNOT_REFILL') {
                    throw $e;
                }
                $down = in_array($e->errorCode, ['SUPPLIER_UNREACHABLE', 'SUPPLIER_UNAVAILABLE', 'SUPPLIER_TIMEOUT', 'SUPPLIER_5XX'], true)
                    || in_array($e->status, [502, 503], true);
                if ($down && $family === FazerCardsIds::TOPUP) {
                    $idUnchecked = true;
                    if (! filter_var($ackRaw, FILTER_VALIDATE_BOOLEAN)) {
                        throw new ApiException(CustomerError::message('ID_ACK_REQUIRED'), 400, 'ID_ACK_REQUIRED');
                    }
                    $playerName = null;
                    $clean['id_ack'] = '1';
                    $clean['version'] = $categoryId;
                } elseif ($down && $family === FazerCardsIds::STEAM_TOPUP) {
                    $steamUnverified = true;
                    $playerName = null;
                } elseif (in_array($e->errorCode, ['ID_VALIDATION_UNSUPPORTED'], true)) {
                    $playerName = null;
                } else {
                    throw new ApiException(CustomerError::message($e->errorCode, 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.'), $e->status, $e->errorCode);
                }
            }
        }
        if ($needsAck && ! $codmMatched) {
            if (! filter_var($ackRaw, FILTER_VALIDATE_BOOLEAN)) {
                throw new ApiException(CustomerError::message('ID_ACK_REQUIRED'), 400, 'ID_ACK_REQUIRED');
            }
            $clean['id_ack'] = '1';
            $playerName = null;
        }
        if ($steamUnverified) {
            $clean['steam_unverified'] = '1';
        }
        if ($idUnchecked) {
            $clean['id_unchecked'] = '1';
        }

        if ($amountToman < 1) {
            throw new ApiException(CustomerError::message('SKU_UNAVAILABLE'), 400, 'SKU_UNAVAILABLE');
        }

        $order = new Order;
        $order->user_id = $userId;
        $order->sku_id = $sku->id;
        $order->product_id = $sku->product_id;
        $order->quantity = $quantity;
        $stored = $clean;
        if (($clean['id_ack'] ?? '') === '1') {
            $stored['id_ack_at'] = now()->toIso8601String();
        }
        $order->requirements = $stored;
        $order->player_name = $playerName;
        $order->coupon_id = $couponId;
        $order->coupon_code = $storedCouponCode;
        $order->subtotal_toman = $subtotalToman;
        $order->discount_toman = $discountToman;
        $order->amount_toman = $amountToman;
        $order->cost_usd_snapshot = $usdSnapshot;
        $order->fx_rate_snapshot = $this->pricing->usdTomanRate();
        $order->margin_snapshot = $this->pricing->marginPercent();
        $order->status = Order::STATUS_DRAFT;
        $order->fulfillment_type = $family;
        $order->idempotency_key = $idempotencyKey;
        $order->body_hash = $bodyHash;
        $order->expires_at = now()->addMinutes((int) config('charjino.orders.payment_ttl_minutes', 30));
        $order->appendEvent('created');
        try {
            $order->save();
        } catch (UniqueConstraintViolationException) {
            $owned = Order::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('user_id', $userId)
                ->first();
            if ($owned && $owned->body_hash === $bodyHash && $owned->isPayable()) {
                return $owned;
            }
            $order->idempotency_key = $this->uniquifyIdempotencyKey($idempotencyKey);
            $order->save();
        }
        $this->storeItem($order, $sku, $family, $clean, $quantity, $usdSnapshot, $subtotalToman);
        $this->abandonSiblingUnpaid($order);

        return $order;
    }

    public function listForUser(string $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return Order::query()
            ->with(['sku', 'product'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findOwned(string $userId, string $number): Order
    {
        $order = Order::query()->where('number', $number)->where('user_id', $userId)->first();
        if (! $order) {
            throw new ApiException('سفارش پیدا نشد.', 404, 'ORDER_NOT_FOUND');
        }

        return $order;
    }

    public function findAdmin(string $idOrNumber): Order
    {
        $order = Order::query()->where('id', $idOrNumber)->orWhere('number', $idOrNumber)->first();
        if (! $order) {
            throw new ApiException('سفارش پیدا نشد.', 404, 'ORDER_NOT_FOUND');
        }

        return $order;
    }

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function listAdmin(Request $request): LengthAwarePaginator
    {
        $page = AdminPage::query($request);
        $query = Order::query()->with(['sku', 'product', 'user']);

        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($request->boolean('unknown')) {
            $query->whereHas('fulfillmentAttempts', fn ($a) => $a->where('status', FulfillmentAttempt::STATUS_UNKNOWN));
        }
        $hold = trim((string) $request->query('holdReason', ''));
        if ($hold !== '') {
            $query->where('hold_reason', $hold);
        } elseif ($request->boolean('hold')) {
            $query->whereNotNull('hold_reason');
        }

        $from = trim((string) $request->query('from', ''));
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        $to = trim((string) $request->query('to', ''));
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('number', 'like', $like)
                    ->orWhere('player_name', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('phone', 'like', $like));
            });
        }

        return $query->orderByDesc('created_at')->paginate($page['perPage'], ['*'], 'page', $page['page']);
    }

    public function cancelUnpaid(Order $order): Order
    {
        if (! in_array($order->status, [Order::STATUS_DRAFT, Order::STATUS_PENDING_PAYMENT], true)) {
            throw new ApiException('فقط سفارش پرداخت‌نشده را می‌توان لغو کرد.', 400, 'ORDER_STATE_INVALID');
        }

        $order->customer_message = 'سفارش لغو شد.';

        return $this->states->transition($order, Order::STATUS_CANCELLED, 'customer', 'CANCELLED');
    }

    public function markRefunded(Order $order, ?string $reason = null): Order
    {
        $allowed = [
            Order::STATUS_FAILED,
            Order::STATUS_PARTIAL,
            Order::STATUS_DELIVERED,
            Order::STATUS_REFUND_PENDING,
            Order::STATUS_FULFILLING,
        ];
        if (! in_array($order->status, $allowed, true)) {
            throw new ApiException('این وضعیت برای بازگشت وجه مجاز نیست.', 400, 'ORDER_STATE_INVALID');
        }

        return app(RefundService::class)->markManual($order, 'admin', 'manual', $reason);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(): array
    {
        $today = now()->toDateString();
        $revenue = (int) Order::query()
            ->whereDate('created_at', $today)
            ->whereIn('status', [
                Order::STATUS_PAID,
                Order::STATUS_FULFILLING,
                Order::STATUS_DELIVERED,
                Order::STATUS_PARTIAL,
            ])
            ->sum('amount_toman');

        return [
            'ordersToday' => Order::query()->whereDate('created_at', $today)->count(),
            'revenueTodayToman' => $revenue,
            'pendingFulfillment' => Order::query()->whereIn('status', [
                Order::STATUS_PAID,
                Order::STATUS_SUBMITTING,
                Order::STATUS_SUBMITTED,
                Order::STATUS_PROCESSING,
            ])->count(),
            'failedToday' => Order::query()
                ->whereDate('created_at', $today)
                ->where('status', Order::STATUS_FAILED)
                ->count(),
            ...$this->adminCounters(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function adminCounters(): array
    {
        return [
            'needsReview' => Order::query()->where('status', Order::STATUS_NEEDS_REVIEW)->count(),
            'refundPending' => Order::query()->where('status', Order::STATUS_REFUND_PENDING)->count(),
            'unknownAttempts' => FulfillmentAttempt::query()->where('status', FulfillmentAttempt::STATUS_UNKNOWN)->count(),
            'hold' => Order::query()->whereNotNull('hold_reason')->count(),
            'openAlerts' => AdminAlert::query()->whereNull('resolved_at')->count(),
        ];
    }

    public function expireUnpaid(): int
    {
        $count = 0;
        Order::query()
            ->whereIn('status', [Order::STATUS_DRAFT, Order::STATUS_PENDING_PAYMENT])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$count): void {
                foreach ($orders as $order) {
                    if (app(PaymentService::class)->holdExpireForZarinpal($order)) {
                        continue;
                    }
                    $order->customer_message = CustomerError::message('PAYMENT_EXPIRED');
                    $this->states->transition($order, Order::STATUS_EXPIRED, 'system', 'PAYMENT_EXPIRED');
                    $count++;
                }
            });

        return $count;
    }

    private function releaseIdempotencyKey(Order $order): void
    {
        $order->idempotency_key = mb_substr('used-'.$order->number.'-'.Str::uuid(), 0, 80);
        $order->save();
    }

    private function uniquifyIdempotencyKey(string $key): string
    {
        $suffix = '-'.Str::uuid();

        return mb_substr($key, 0, max(1, 80 - mb_strlen($suffix))).$suffix;
    }

    private function abandonSiblingUnpaid(Order $keep): void
    {
        $siblings = Order::query()
            ->where('user_id', $keep->user_id)
            ->where('sku_id', $keep->sku_id)
            ->where('body_hash', $keep->body_hash)
            ->where('id', '!=', $keep->id)
            ->whereIn('status', [Order::STATUS_DRAFT, Order::STATUS_PENDING_PAYMENT])
            ->get();

        foreach ($siblings as $sibling) {
            try {
                $this->cancelUnpaid($sibling);
            } catch (ApiException) {
            }
        }
    }

    /**
     * @param  array<string, string>  $clean
     */
    private function storeItem(Order $order, Sku $sku, string $family, array $clean, int $quantity, string $usdSnapshot, int $amountToman): void
    {
        $unitUsd = $quantity > 1 && function_exists('bcdiv')
            ? bcdiv($usdSnapshot, (string) $quantity, 6)
            : $usdSnapshot;
        $unitToman = (int) round($amountToman / max(1, $quantity));
        OrderItem::query()->create([
            'order_id' => $order->id,
            'sku_id' => $sku->id,
            'product_id' => $sku->product_id,
            'fulfillment_type' => $family,
            'supplier_ref' => [
                'category_id' => $sku->fazercards_category_id,
                'offer_id' => $sku->fazercards_offer_id,
                'card_id' => $family === FazerCardsIds::GIFTCARD ? $sku->fazercards_offer_id : null,
                'key_id' => $family === FazerCardsIds::GAMEKEY ? $sku->fazercards_offer_id : null,
                'game_id' => $family === FazerCardsIds::GAMEKEY ? FazerCardsIds::rawCategory($sku->fazercards_category_id) : null,
                'sub_id' => $family === FazerCardsIds::STEAM_GIFT ? explode('|', (string) $sku->fazercards_offer_id)[0] : null,
                'app_id' => $family === FazerCardsIds::STEAM_GIFT ? FazerCardsIds::rawCategory($sku->fazercards_category_id) : null,
                'region' => $sku->region,
                'months' => $family === FazerCardsIds::TELEGRAM_PREMIUM ? $sku->fazercards_offer_id : null,
                'stars_quantity' => $family === FazerCardsIds::TELEGRAM_STARS ? $sku->fazercards_offer_id : null,
            ],
            'requirements' => $clean,
            'quantity' => $quantity,
            'steam_currency' => $clean['currency'] ?? null,
            'steam_amount' => $clean['amount'] ?? null,
            'unit_price_toman' => $unitToman,
            'total_toman' => $amountToman,
            'unit_cost_usd' => $unitUsd,
            'total_cost_usd' => $usdSnapshot,
            'fx_rate' => $order->fx_rate_snapshot,
            'margin_percent' => $order->margin_snapshot,
            'price_source' => 'synced',
            'priced_at' => now(),
            'stock_at_order' => $sku->stock,
        ]);
    }

    /**
     * @param  array<string, mixed>  $clean
     * @return array{quantity: int, usdSnapshot: string, amountToman: int, clean: array<string, mixed>}
     */
    private function pricePurchase(
        Sku $sku,
        mixed $product,
        string $family,
        int $quantity,
        array $clean,
        ?string $amount,
        ?string $currency,
    ): array {
        if ($sku->isFazerCards()) {
            $quote = $this->quotes->quote($sku, $quantity, $clean, $amount, $currency);
            $quantity = (int) $quote['quantity'];
            $usdSnapshot = $quote['totalCostUsd'];
            $amountToman = $quote['totalToman'];
            if ($quote['steamCurrency']) {
                $clean['currency'] = $quote['steamCurrency'];
                $clean['amount'] = (string) $quote['steamAmount'];
            }
        } else {
            $quantity = $this->normalizedQuantity($sku, $family, $quantity);
            $usdSnapshot = $sku->usdCost();
            $amountToman = $sku->sellPrice() * $quantity;
            if ($family === FazerCardsIds::STEAM_TOPUP) {
                $priced = $this->priceSteamTopup($clean);
                $clean['currency'] = $priced['currency'];
                $clean['amount'] = $priced['amount'];
                $usdSnapshot = $priced['usd'];
                $amountToman = $this->pricing->sellTomanFor($sku, $usdSnapshot, $product);
            } elseif (in_array($family, [FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY], true)) {
                $usdSnapshot = Money::isPositive($usdSnapshot)
                    ? (function_exists('bcmul') ? bcmul($usdSnapshot, (string) $quantity, 4) : (string) ((float) $usdSnapshot * $quantity))
                    : $usdSnapshot;
            }
        }

        return [
            'quantity' => $quantity,
            'usdSnapshot' => $usdSnapshot,
            'amountToman' => $amountToman,
            'clean' => $clean,
        ];
    }

    private function sellableSku(int $skuId): Sku
    {
        $sku = Sku::query()->with('product')->find($skuId);
        if (
            ! $sku
            || $sku->status !== 'active'
            || ! $sku->product
            || $sku->product->status !== 'active'
            || ! (bool) $sku->product->getAttribute('visible')
            || $sku->product->isStorefrontExcluded()
        ) {
            throw new ApiException(CustomerError::message('SKU_UNAVAILABLE'), 400, 'SKU_UNAVAILABLE');
        }
        FazerCardsPriceFreshness::assertPurchasable($sku);

        return $sku;
    }

    /**
     * @param  array<string, string>  $clean
     * @return array{playerName: string, region: string|null, zoneId: string|null, skuId: int, validateMode?: string, validation?: string, warning?: string, identityKind: string}
     */
    private function checkPlayer(Sku $sku, array $clean): array
    {
        $sku->loadMissing('product.catalogGroup');
        $kind = BuyerIdentity::kind($sku->product);
        $cacheKey = $this->playerCheckCacheKey($sku, $clean);
        if ($cacheKey !== null) {
            $hit = FazerIdCheckCache::get($cacheKey);
            if (is_array($hit)) {
                if (($hit['error'] ?? '') !== '') {
                    throw new ApiException(
                        (string) ($hit['message'] ?: CustomerError::message((string) $hit['error'])),
                        (int) ($hit['status'] ?? 422),
                        (string) $hit['error'],
                        data: $hit['data'] ?? null,
                    );
                }

                return $hit;
            }
        }

        try {
            $result = $this->checkPlayerLive($sku, $clean, $kind);
        } catch (ApiException $e) {
            if ($cacheKey !== null && ($e->errorCode === 'ID_NOT_CONFIRMED' || $e->status === 422 || $e->errorCode === 'STEAM_LOGIN_CANNOT_REFILL')) {
                FazerIdCheckCache::put($cacheKey, [
                    'error' => $e->errorCode ?: 'ID_NOT_CONFIRMED',
                    'status' => $e->status,
                    'message' => $e->getMessage(),
                    'data' => $e->data,
                ]);
            }
            throw $e;
        }
        $codmUnknown = is_array($result['codmRegion'] ?? null)
            && ($result['codmRegion']['status'] ?? null) === CodmRegionResult::UNKNOWN;
        if ($cacheKey !== null && ! $codmUnknown && in_array($result['validation'] ?? '', ['ok', 'unsupported', 'unverified'], true)) {
            FazerIdCheckCache::put($cacheKey, $result);
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $clean
     * @return array{playerName: string, region: string|null, zoneId: string|null, skuId: int, validateMode?: string, validation?: string, warning?: string, identityKind: string, unverified?: bool}
     */
    private function checkPlayerLive(Sku $sku, array $clean, string $kind): array
    {
        if ($sku->isFazerCards()) {
            $codmProbe = $this->probeCodmRegion($sku, $clean);
            if ($codmProbe instanceof CodmRegionResult && $codmProbe->isMatch()) {
                $id = BuyerRequirements::playerId($clean);

                return $this->identityResult(
                    $sku,
                    $kind,
                    $codmProbe->nickname ?: BuyerIdentity::displayName($kind, $id),
                    $codmProbe->accountCountry,
                    null,
                    'ok',
                    'codm_store',
                    extra: ['codmRegion' => $codmProbe->toPublicArray()],
                );
            }
            if (! $this->fazerCards->configured()) {
                return $this->withCodmRegion($this->localIdentity($sku, $clean, $kind), $codmProbe);
            }
            try {
                $result = $this->fazerCards->validatePlayer($sku, $clean);
            } catch (ApiException $e) {
                if ($e->errorCode === 'ID_VALIDATION_UNSUPPORTED') {
                    $id = BuyerRequirements::playerId($clean);
                    BuyerIdentity::assertLooksValid($kind, $id);

                    return $this->withCodmRegion($this->identityResult(
                        $sku,
                        $kind,
                        BuyerIdentity::displayName($kind, $id),
                        null,
                        $clean['zone_id'] ?? null,
                        'unsupported',
                        'manual',
                        CustomerError::message('ID_VALIDATION_UNSUPPORTED'),
                    ), $codmProbe);
                }
                $message = $e->errorCode === 'PLAYER_NOT_FOUND'
                    ? BuyerIdentity::notFoundMessage($kind)
                    : CustomerError::message($e->errorCode, $e->getMessage());
                throw new ApiException($message, $e->status, $e->errorCode);
            }

            $id = BuyerRequirements::playerId($clean);
            $unverified = ! empty($result['unverified']);

            return $this->withCodmRegion($this->identityResult(
                $sku,
                $kind,
                BuyerIdentity::displayName($kind, $id, $result['playerName'] ?? null),
                $result['region'] ?? null,
                $result['zoneId'] ?? ($clean['zone_id'] ?? null),
                $unverified ? 'unverified' : 'ok',
                $result['validateMode'] ?? 'api',
                $unverified ? 'بررسی لاگین انجام نشد؛ می‌توانید ادامه دهید.' : null,
                ['unverified' => $unverified],
            ), $codmProbe);
        }

        $itemId = (int) ($sku->shop2_item_id ?: 0);
        if ($itemId > 0 && $this->shop2Configured()) {
            try {
                $result = $this->gateway->validatePlayer(
                    $itemId,
                    BuyerRequirements::playerId($clean),
                    $clean['server'] ?? null,
                    $clean['zone_id'] ?? null,
                );
                $id = BuyerRequirements::playerId($clean);

                return $this->identityResult(
                    $sku,
                    $kind,
                    BuyerIdentity::displayName($kind, $id, $result['playerName'] ?? null),
                    $result['region'] ?? null,
                    $result['zoneId'] ?? ($clean['zone_id'] ?? null),
                    'ok',
                    'api',
                );
            } catch (ApiException $e) {
                if ($this->canFallbackIdentity($e->errorCode)) {
                    SafeLog::info('player.check.fallback', [
                        'skuId' => $sku->id,
                        'code' => $e->errorCode,
                    ]);

                    return $this->localIdentity($sku, $clean, $kind);
                }
                $message = $e->errorCode === 'PLAYER_NOT_FOUND'
                    ? BuyerIdentity::notFoundMessage($kind)
                    : CustomerError::message($e->errorCode, $e->getMessage());
                throw new ApiException($message, $e->status, $e->errorCode);
            }
        }

        return $this->localIdentity($sku, $clean, $kind);
    }

    /**
     * @param  array<string, string>  $clean
     */
    private function probeCodmRegion(Sku $sku, array $clean): ?CodmRegionResult
    {
        $variant = CodmRegionCheck::variantForFazerCategory($sku->fazercards_category_id)
            ?? CodmRegionCheck::variantForHandle($sku->product?->handle);
        if ($variant === null) {
            return null;
        }
        try {
            $result = $this->codmRegion->check(BuyerRequirements::playerId($clean), $variant);
        } catch (\InvalidArgumentException) {
            throw new ApiException(CustomerError::message('PLAYER_ID_INVALID'), 422, 'PLAYER_ID_INVALID');
        }
        if ($result->isMismatch()) {
            throw new ApiException(
                CodmRegionCheck::mismatchMessage($result),
                422,
                'CODM_REGION_MISMATCH',
                data: $result->toPublicArray(),
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withCodmRegion(array $payload, ?CodmRegionResult $probe): array
    {
        if ($probe instanceof CodmRegionResult) {
            $payload['codmRegion'] = $probe->toPublicArray();
        }

        return $payload;
    }

    /**
     * @param  array<string, string>  $clean
     * @return array{playerName: string, region: string|null, zoneId: string|null, skuId: int, validateMode: string, validation: string, identityKind: string}
     */
    private function localIdentity(Sku $sku, array $clean, string $kind): array
    {
        $id = BuyerRequirements::playerId($clean);
        BuyerIdentity::assertLooksValid($kind, $id);

        return $this->identityResult(
            $sku,
            $kind,
            BuyerIdentity::displayName($kind, $id),
            null,
            $clean['zone_id'] ?? null,
            'skipped',
            'manual',
            'شناسه در تأمین‌کننده بررسی نشد.',
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{playerName: string, region: string|null, zoneId: string|null, skuId: int, validateMode: string, validation: string, identityKind: string, warning?: string}
     */
    private function identityResult(
        Sku $sku,
        string $kind,
        string $playerName,
        ?string $region,
        ?string $zoneId,
        string $validation,
        string $validateMode,
        ?string $warning = null,
        array $extra = [],
    ): array {
        $row = [
            'playerName' => $playerName,
            'region' => $region,
            'zoneId' => $zoneId,
            'skuId' => $sku->id,
            'validateMode' => $validateMode,
            'validation' => $validation,
            'identityKind' => $kind,
        ];
        if ($warning !== null) {
            $row['warning'] = $warning;
        }

        return array_merge($row, $extra);
    }

    /**
     * @param  array<string, string>  $clean
     */
    private function playerCheckCacheKey(Sku $sku, array $clean): ?string
    {
        $family = FazerCardsIds::familyOf($sku->fulfillment_type, $sku->fazercards_category_id);
        if (! in_array($family, [FazerCardsIds::TOPUP, FazerCardsIds::STEAM_TOPUP], true)) {
            return null;
        }
        $fields = $clean;
        unset($fields['id_ack'], $fields['idAck'], $fields['version'], $fields['currency'], $fields['amount'], $fields['id_unchecked'], $fields['steam_unverified'], $fields['region_ack']);

        return FazerIdCheckCache::key($family, FazerCardsIds::rawCategory($sku->fazercards_category_id).':'.$sku->id, $fields);
    }

    private function normalizedQuantity(Sku $sku, string $family, int $quantity): int
    {
        $quantity = max(1, $quantity);
        if (in_array($family, [FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY], true)) {
            $minQty = $sku->minOrderQuantity();
            $maxQty = $sku->maxOrderQuantity();
            $stock = $sku->stock;
            if ($stock !== null) {
                $maxQty = max($minQty, min($maxQty, (int) $stock));
            }
            if ($quantity < $minQty || $quantity > $maxQty) {
                throw new ApiException(CustomerError::message('INVALID_QUANTITY'), 400, 'INVALID_QUANTITY');
            }

            return $quantity;
        }

        return 1;
    }

    /**
     * @param  array<string, string>  $clean
     * @return array{currency: string, amount: string, usd: string}
     */
    private function priceSteamTopup(array $clean): array
    {
        $payload = [];
        try {
            $payload = $this->fazerCards->configured()
                ? $this->fazerCards->steamRatesPayload()
                : [];
        } catch (ApiException) {
            $payload = [];
        }

        return SteamTopupAmount::validate(
            (string) ($clean['currency'] ?? ''),
            (string) ($clean['amount'] ?? ''),
            $payload,
        );
    }

    private function shouldCheckIdentityOnCreate(string $family): bool
    {
        return in_array($family, [FazerCardsIds::TOPUP, FazerCardsIds::STEAM_TOPUP], true);
    }

    private function shop2Configured(): bool
    {
        return trim((string) config('charjino.supplier.s2t_key')) !== '';
    }

    private function canFallbackIdentity(?string $code): bool
    {
        return in_array($code, [
            'PLAYER_CHECK_UNAVAILABLE',
            'NO_PLAYER_VALIDATION_REQUIRED',
            'SUPPLIER_UNREACHABLE',
            'SUPPLIER_NOT_CONFIGURED',
        ], true);
    }
}

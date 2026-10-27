<?php

namespace App\Modules\Fulfillment;

use App\Exceptions\ApiException;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Sku;
use App\Modules\Orders\OrderStateMachine;
use App\Modules\Payments\PaymentProviders;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Support\CustomerError;
use App\Support\SafeLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class FulfillmentService
{
    public function __construct(
        private readonly FulfillmentGateway $gateway,
        private readonly FazerCardsAdapter $fazerCards,
    ) {}

    public function fulfill(string $orderId): Order
    {
        $peek = Order::query()->with('sku')->find($orderId);
        if ($peek?->sku?->isFazerCards() || $peek?->sku?->supplierSku()?->isFazerCards()) {
            \App\Jobs\SubmitFazerOrder::dispatchSync($orderId);

            return $peek->fresh() ?? $peek;
        }

        [$order, $attempt, $live] = DB::transaction(function () use ($orderId) {
            $order = Order::query()->where('id', $orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new ApiException('سفارش پیدا نشد.', 404, 'ORDER_NOT_FOUND');
            }
            $order->load('sku');

            if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_PARTIAL, Order::STATUS_REFUNDED], true)) {
                return [$order, null, false];
            }

            if (! in_array($order->status, [Order::STATUS_PAID, Order::STATUS_FULFILLING, Order::STATUS_FAILED], true)) {
                throw new ApiException('این سفارش آمادهٔ تحویل نیست.', 400, 'ORDER_NOT_PAYABLE');
            }

            $attempt = $this->persistUuid($order);
            if ($order->sku?->gift_sku_id && ! $order->sku->supplierSku()?->isFazerCards()) {
                $this->fail($order, $attempt, 'PRODUCT_UNAVAILABLE');

                return [$order->fresh() ?? $order, $attempt, false];
            }
            app(\App\Modules\Orders\OrderStateMachine::class)->transition($order, Order::STATUS_PROCESSING, 'system', 'FULFILL');
            $order->refresh();

            $live = $this->isLiveFor($order->sku);
            $attempt->dry_run = ! $live;
            $attempt->save();

            if (! $live) {
                $attempt->status = FulfillmentAttempt::STATUS_PENDING;
                $attempt->submitted_at = now();
                $attempt->raw = ['dryRun' => true, 'status' => 'pending'];
                $attempt->save();
                SafeLog::info('order.fulfill.dry_run', ['number' => $order->number]);
            }

            return [$order->fresh() ?? $order, $attempt, $live];
        });

        if ($live && $attempt instanceof FulfillmentAttempt) {
            $this->submitLive($order, $attempt);
        }

        return $order->fresh() ?? $order;
    }

    public function retry(Order $order): Order
    {
        if ($order->sku?->isFazerCards() || $order->latestAttempt()?->supplier === 'fazercards') {
            return $this->redeliver($order, 'system');
        }

        $attempt = $order->latestAttempt();
        if ($attempt && ! in_array($attempt->status, [
            FulfillmentAttempt::STATUS_FAILED,
            FulfillmentAttempt::STATUS_UNKNOWN,
            FulfillmentAttempt::STATUS_NOT_STARTED,
        ], true)) {
            throw new ApiException('فقط سفارش ناموفق را می‌توان دوباره تحویل داد.', 400, 'ORDER_NOT_PAYABLE');
        }

        if ($attempt) {
            $attempt->status = FulfillmentAttempt::STATUS_NOT_STARTED;
            $attempt->error_code = null;
            $attempt->save();
        }

        app(OrderStateMachine::class)->transition($order, Order::STATUS_PAID, 'system', 'RETRY');
        $order->customer_message = null;
        $order->appendEvent('retry_fulfillment');
        $order->save();

        return $this->fulfill($order->id);
    }

    public function redeliver(Order $order, string $adminId): Order
    {
        $attempt = $order->latestAttempt();
        $allowed = [
            FulfillmentAttempt::STATUS_FAILED,
            FulfillmentAttempt::STATUS_REFUND,
            FulfillmentAttempt::STATUS_REFUNDED,
            FulfillmentAttempt::STATUS_ABANDONED,
        ];
        if (! $attempt || ! in_array((string) $attempt->status, $allowed, true)) {
            throw new ApiException(CustomerError::message('ORDER_STATE_INVALID', 'فقط سفارش ناموفق را می‌توان دوباره تحویل داد.'), 400, 'ORDER_NOT_PAYABLE');
        }
        if (app(\App\Modules\Orders\RefundService::class)->activeRefund($order)) {
            throw new ApiException(CustomerError::message('ORDER_STATE_INVALID'), 400, 'ORDER_STATE_INVALID');
        }

        $actor = str_starts_with($adminId, 'admin:') || $adminId === 'system' ? $adminId : 'admin:'.$adminId;
        $status = OrderStateMachine::normalize((string) $order->status);
        if ($status !== Order::STATUS_PAID) {
            app(OrderStateMachine::class)->transition($order, Order::STATUS_PAID, $actor, 'REDELIVER');
        }
        $order->customer_message = null;
        $order->needs_review_reason = null;
        $order->appendEvent('redeliver', ['code' => 'REDELIVER']);
        $order->save();

        if ($order->sku?->isFazerCards() || $attempt->supplier === 'fazercards') {
            if ((string) config('queue.default') === 'sync') {
                \App\Jobs\SubmitFazerOrder::dispatchSync($order->id);
            } else {
                \App\Jobs\SubmitFazerOrder::dispatch($order->id)->onQueue('fulfillment');
            }

            return $order->fresh() ?? $order;
        }

        return $this->fulfill($order->id);
    }

    public function abandonAttempt(Order $order, string $reason, string $adminId): Order
    {
        $attempt = $order->latestAttempt();
        if (! $attempt || $attempt->status !== FulfillmentAttempt::STATUS_UNKNOWN) {
            throw new ApiException(CustomerError::message('ORDER_STATE_INVALID'), 400, 'ORDER_STATE_INVALID');
        }
        $attempt->status = FulfillmentAttempt::STATUS_ABANDONED;
        $attempt->terminal_at = now();
        $attempt->resolution_note = $reason;
        $attempt->resolved_by_admin_id = ctype_digit($adminId) ? (int) $adminId : null;
        $attempt->save();
        $order->customer_message = CustomerError::message('FAZER_FAILED');
        $actor = 'admin:'.$adminId;

        return app(OrderStateMachine::class)->transition($order, Order::STATUS_FAILED, $actor, 'ABANDONED', $reason);
    }

    public function linkFazerOrder(Order $order, string $fazerOrderId, string $adminId): Order
    {
        $fazerOrderId = trim($fazerOrderId);
        if ($fazerOrderId === '' || str_starts_with($fazerOrderId, 'cj-')) {
            throw new ApiException(CustomerError::message('ORDER_STATE_INVALID'), 400, 'ORDER_STATE_INVALID');
        }
        $attempt = $order->latestAttempt();
        if (! $attempt || $attempt->status !== FulfillmentAttempt::STATUS_UNKNOWN) {
            throw new ApiException(CustomerError::message('ORDER_STATE_INVALID'), 400, 'ORDER_STATE_INVALID');
        }
        $taken = FulfillmentAttempt::query()
            ->where('fazer_order_id', $fazerOrderId)
            ->where('id', '!=', $attempt->id)
            ->exists();
        if ($taken) {
            throw new ApiException(CustomerError::message('IDEMPOTENCY_CONFLICT'), 409, 'IDEMPOTENCY_CONFLICT');
        }
        $parsed = $this->fazerCards->getOrder($fazerOrderId);
        $remoteKind = strtolower(str_replace(['_', '-'], '', (string) ($parsed['kind'] ?? '')));
        $localKind = strtolower(str_replace(['_', '-'], '', (string) ($attempt->fazer_kind ?: $order->fulfillment_type ?: '')));
        if ($remoteKind !== '' && $localKind !== '' && ! str_contains($remoteKind, $localKind) && ! str_contains($localKind, $remoteKind)) {
            throw new ApiException(CustomerError::message('ORDER_STATE_INVALID'), 400, 'ORDER_STATE_INVALID');
        }
        $attempt->fazer_order_id = $fazerOrderId;
        $attempt->resolution_note = 'linked';
        $attempt->resolved_by_admin_id = ctype_digit($adminId) ? (int) $adminId : null;
        $attempt->save();
        \App\Jobs\RefreshFazerOrder::dispatchSync($attempt->id);

        return $order->fresh() ?? $order;
    }

    public function applySupplierStatus(string $supplierOrderId, string $supplierStatus): ?Order
    {
        $attempt = FulfillmentAttempt::query()->where('supplier_order_id', $supplierOrderId)->first();
        if (! $attempt) {
            SafeLog::warning('fulfillment.unknown_supplier_order', ['status' => $supplierStatus]);

            return null;
        }

        $order = $attempt->order;
        if (! $order) {
            return null;
        }

        $mapped = $this->mapSupplierStatus($supplierStatus);
        $attempt->status = $mapped['attempt'];
        $attempt->raw = array_merge(is_array($attempt->raw) ? $attempt->raw : [], [
            'supplierStatus' => $supplierStatus,
        ]);
        $attempt->save();

        if (in_array(OrderStateMachine::normalize((string) $order->status), [Order::STATUS_REFUNDED], true)
            && $mapped['order'] !== Order::STATUS_REFUNDED) {
            return $order;
        }

        $order->customer_message = $mapped['message'];
        try {
            app(\App\Modules\Orders\OrderStateMachine::class)->transition(
                $order,
                $mapped['order'],
                'webhook',
                'supplier_'.$supplierStatus,
            );
        } catch (\App\Exceptions\IllegalTransition) {
            return $order->fresh() ?? $order;
        }

        SafeLog::info('order.supplier_status', [
            'number' => $order->number,
            'status' => $order->status,
        ]);

        return $order;
    }

    public function pollStuck(): int
    {
        $after = (int) config('charjino.orders.poll_after_minutes', 5);
        $max = (int) config('charjino.orders.poll_max_per_window', 3);
        $window = (int) config('charjino.orders.poll_window_seconds', 60);
        $cutoff = now()->subMinutes($after);
        $polled = 0;

        $attempts = FulfillmentAttempt::query()
            ->whereIn('status', [FulfillmentAttempt::STATUS_SUBMITTED, FulfillmentAttempt::STATUS_PENDING])
            ->where('dry_run', false)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_polled_at')->orWhere('last_polled_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($attempts as $attempt) {
            $key = ($attempt->supplier === 'fazercards' ? 'fc-poll:' : 's2t-poll:').$attempt->supplier_order_id;
            if (RateLimiter::tooManyAttempts($key, $max)) {
                continue;
            }
            RateLimiter::hit($key, $window);
            $attempt->last_polled_at = now();
            $attempt->poll_count = (int) $attempt->poll_count + 1;
            $attempt->save();
            $polled++;

            try {
                $remote = $attempt->supplier === 'fazercards'
                    ? $this->fazerCards->getOrder($attempt->supplier_order_id)
                    : $this->gateway->getOrder($attempt->supplier_order_id);
                $this->applySupplierStatus($attempt->supplier_order_id, (string) $remote['status']);
            } catch (ApiException $e) {
                SafeLog::warning('fulfillment.poll_failed', [
                    'number' => $attempt->order?->number,
                    'code' => $e->errorCode,
                ]);
            }
        }

        return $polled;
    }

    private function persistUuid(Order $order): FulfillmentAttempt
    {
        $existing = $order->fulfillmentAttempts()->latest('id')->first();
        if ($existing) {
            return $existing;
        }

        $uuid = (string) Str::uuid();
        $sku = $order->sku;
        $route = $sku?->gift_sku_id ? $sku->supplierSku() : $sku;

        return FulfillmentAttempt::query()->create([
            'order_id' => $order->id,
            'supplier' => $route?->isFazerCards() ? 'fazercards' : 'shop2topup',
            'supplier_order_id' => $uuid,
            'idempotency_key' => $uuid,
            'attempt_no' => 1,
            'idempotent' => true,
            'status' => FulfillmentAttempt::STATUS_NOT_STARTED,
            'dry_run' => true,
            'raw' => ['uuidPersistedAt' => now()->toIso8601String()],
        ]);
    }

    private function submitLive(Order $order, FulfillmentAttempt $attempt): void
    {
        $sku = $order->sku;
        if ($sku?->gift_sku_id) {
            $target = $sku->supplierSku();
            if (! $target?->isFazerCards()) {
                $this->fail($order, $attempt, 'PRODUCT_UNAVAILABLE');

                return;
            }
            $this->submitLiveFazerCards($order, $attempt, $target);

            return;
        }
        if ($sku?->isFazerCards()) {
            $this->submitLiveFazerCards($order, $attempt, $sku);

            return;
        }

        $itemId = (int) ($sku?->shop2_item_id ?: 0);
        if ($itemId < 1) {
            $this->fail($order, $attempt, 'PRODUCT_UNAVAILABLE');

            return;
        }

        $requirements = is_array($order->requirements) ? $order->requirements : [];
        try {
            $price = $this->gateway->livePrice($itemId);
        } catch (ApiException $e) {
            $this->fail($order, $attempt, $e->errorCode ?: 'INTERNAL_ERROR');

            return;
        }
        $attempt->expected_unit_price = $price['unitPrice'];
        $attempt->save();

        $input = [
            'order_id' => $attempt->supplier_order_id,
            'sub_category_id' => $itemId,
            'quantity' => $order->quantity,
            'requirements' => $requirements,
            'expected_unit_price' => (string) $attempt->expected_unit_price,
            'confirm' => true,
        ];

        try {
            $result = $this->createWithAntiDouble($input, $attempt, $itemId);
        } catch (ApiException $e) {
            $this->fail($order, $attempt, $e->errorCode ?: 'INTERNAL_ERROR');

            return;
        }

        $status = strtolower((string) ($result['status'] ?? 'pending'));
        $attempt->status = $status === 'pending' ? FulfillmentAttempt::STATUS_PENDING : $this->mapSupplierStatus($status)['attempt'];
        $attempt->submitted_at = now();
        $attempt->raw = ['status' => $status, 'dryRun' => false];
        $attempt->save();

        if (in_array($attempt->status, [
            FulfillmentAttempt::STATUS_COMPLETED,
            FulfillmentAttempt::STATUS_PARTIAL,
            FulfillmentAttempt::STATUS_REFUNDED,
            FulfillmentAttempt::STATUS_FAILED,
        ], true)) {
            $this->applySupplierStatus($attempt->supplier_order_id, $status);
        }
    }

    /**
     * @param  array{order_id: string, sub_category_id: int, quantity: int, requirements: array<string, string>, expected_unit_price: string, confirm: bool}  $input
     * @return array{orderId: string, status: string}
     */
    private function createWithAntiDouble(array $input, FulfillmentAttempt $attempt, int $itemId): array
    {
        try {
            return $this->gateway->createOrder($input);
        } catch (ApiException $e) {
            if ($e->errorCode === 'DUPLICATE_ORDER') {
                SafeLog::info('order.fulfill.duplicate', ['number' => $attempt->order?->number]);

                return $this->gateway->getOrder($input['order_id']);
            }

            if (in_array($e->errorCode, ['PRICE_INCREASED', 'PRICE_CHANGED'], true)) {
                $fresh = $this->gateway->livePrice($itemId);
                $attempt->expected_unit_price = $fresh['unitPrice'];
                $attempt->save();
                $input['expected_unit_price'] = $fresh['unitPrice'];
                SafeLog::info('order.fulfill.price_increased', ['number' => $attempt->order?->number]);

                return $this->gateway->createOrder($input);
            }

            if (in_array($e->errorCode, ['SUPPLIER_UNREACHABLE', 'INTERNAL_ERROR'], true)) {
                try {
                    return $this->gateway->getOrder($input['order_id']);
                } catch (ApiException $inner) {
                    if ($inner->errorCode === 'ORDER_NOT_FOUND') {
                        return $this->gateway->createOrder($input);
                    }
                    throw $inner;
                }
            }

            throw $e;
        }
    }

    private function isLiveFor(?Sku $sku): bool
    {
        if ($sku?->gift_sku_id) {
            return (bool) $sku->supplierSku()?->isFazerCards() && $this->fazerCards->isLiveCreateEnabled();
        }
        if ($sku?->isFazerCards()) {
            return $this->fazerCards->isLiveCreateEnabled();
        }

        return $this->gateway->isLiveCreateEnabled();
    }

    private function submitLiveFazerCards(Order $order, FulfillmentAttempt $attempt, Sku $sku): void
    {
        if (! $this->hasVerifiedShopPayment($order)) {
            $this->fail($order, $attempt, 'SHOP_PAYMENT_REQUIRED');

            return;
        }

        $requirements = is_array($order->requirements) ? $order->requirements : [];
        try {
            $price = $this->fazerCards->livePrice($sku);
        } catch (ApiException $e) {
            $this->fail($order, $attempt, $e->errorCode ?: 'PRODUCT_UNAVAILABLE');

            return;
        }

        $attempt->expected_unit_price = $price['unitPrice'];
        $attempt->save();

        $idempotency = (string) $attempt->supplier_order_id;

        try {
            $result = $this->createFcWithAntiDouble($sku, $requirements, $attempt, $idempotency, max(1, (int) $order->quantity));
        } catch (ApiException $e) {
            $this->fail($order, $attempt, $e->errorCode ?: 'INTERNAL_ERROR');

            return;
        }

        if (! empty($result['orderId']) && $result['orderId'] !== $attempt->supplier_order_id) {
            $attempt->supplier_order_id = $result['orderId'];
        }

        $status = strtolower((string) ($result['status'] ?? 'pending'));
        $attempt->status = $status === 'pending' ? FulfillmentAttempt::STATUS_PENDING : $this->mapSupplierStatus($status)['attempt'];
        $attempt->submitted_at = now();
        $attempt->raw = [
            'status' => $status,
            'dryRun' => false,
            'idempotencyKey' => $idempotency,
        ];
        $attempt->save();

        if (in_array($attempt->status, [
            FulfillmentAttempt::STATUS_COMPLETED,
            FulfillmentAttempt::STATUS_PARTIAL,
            FulfillmentAttempt::STATUS_REFUNDED,
            FulfillmentAttempt::STATUS_FAILED,
        ], true)) {
            $this->applySupplierStatus($attempt->supplier_order_id, $status);
        }
    }

    /**
     * @param  array<string, string>  $requirements
     * @return array{orderId: string, status: string}
     */
    private function createFcWithAntiDouble(Sku $sku, array $requirements, FulfillmentAttempt $attempt, string $idempotency, int $quantity = 1): array
    {
        $priceUsd = (string) $attempt->expected_unit_price;
        try {
            return $this->fazerCards->placeOrder($sku, $requirements, $priceUsd, $idempotency, true, $quantity);
        } catch (ApiException $e) {
            if ($e->errorCode === 'DUPLICATE_ORDER') {
                SafeLog::info('order.fulfill.duplicate', ['number' => $attempt->order?->number]);

                return $this->fazerCards->getOrder($attempt->supplier_order_id);
            }

            if (in_array($e->errorCode, ['PRICE_INCREASED', 'PRICE_CHANGED'], true)) {
                $fresh = $this->fazerCards->livePrice($sku);
                $attempt->expected_unit_price = $fresh['unitPrice'];
                $attempt->save();
                SafeLog::info('order.fulfill.price_increased', ['number' => $attempt->order?->number]);

                return $this->fazerCards->placeOrder($sku, $requirements, $fresh['unitPrice'], $idempotency, true, $quantity);
            }

            if (in_array($e->errorCode, ['SUPPLIER_UNREACHABLE', 'INTERNAL_ERROR'], true)) {
                try {
                    return $this->fazerCards->getOrder($idempotency);
                } catch (ApiException $inner) {
                    if ($inner->errorCode === 'ORDER_NOT_FOUND') {
                        return $this->fazerCards->placeOrder($sku, $requirements, $priceUsd, $idempotency, true, $quantity);
                    }
                    throw $inner;
                }
            }

            throw $e;
        }
    }

    private function hasVerifiedShopPayment(Order $order): bool
    {
        return $order->payments()
            ->where('status', Payment::STATUS_VERIFIED)
            ->whereIn('provider', PaymentProviders::shopPaid())
            ->exists();
    }

    private function fail(Order $order, FulfillmentAttempt $attempt, string $code): void
    {
        if (in_array($code, [
            'INSUFFICIENT_BALANCE',
            'OUT_OF_STOCK',
            'PRODUCT_UNAVAILABLE',
            'PRICE_CHANGED',
            'PRICE_INCREASED',
            'TEMPORARILY_UNAVAILABLE',
            'SUPPLIER_UNREACHABLE',
        ], true)) {
            $this->holdForSupport($order, $attempt, $code);

            return;
        }
        $attempt->status = FulfillmentAttempt::STATUS_FAILED;
        $attempt->error_code = $code;
        $attempt->save();
        $order->customer_message = CustomerError::message($code);
        app(\App\Modules\Orders\OrderStateMachine::class)->transition($order, Order::STATUS_FAILED, 'system', $code);
        SafeLog::warning('order.fulfill.failed', ['number' => $order->number, 'code' => $code]);
    }

    private function holdForSupport(Order $order, FulfillmentAttempt $attempt, string $code): void
    {
        $attempt->status = FulfillmentAttempt::STATUS_NOT_STARTED;
        $attempt->error_code = $code;
        $attempt->save();
        $order->needs_review_reason = $code;
        $order->customer_message = CustomerError::message('INSUFFICIENT_BALANCE');
        if ($code === 'INSUFFICIENT_BALANCE') {
            $order->hold_reason = 'INSUFFICIENT_BALANCE';
            $minutes = (int) config('charjino.orders.balance_hold_retry_minutes', 5);
            $order->hold_until = now()->addMinutes(max(1, $minutes));
            app(\App\Modules\Ops\AdminAlertService::class)->open('LOW_BALANCE', 'balance', [
                'number' => $order->number,
            ]);
        } else {
            app(\App\Modules\Ops\AdminAlertService::class)->open('NEEDS_REVIEW', 'fulfill:'.$order->id, [
                'order_number' => $order->number,
                'code' => $code,
            ]);
        }
        $order->save();
        $status = \App\Modules\Orders\OrderStateMachine::normalize((string) $order->status);
        if ($status !== Order::STATUS_NEEDS_REVIEW) {
            try {
                app(\App\Modules\Orders\OrderStateMachine::class)->transition(
                    $order,
                    Order::STATUS_NEEDS_REVIEW,
                    'system',
                    $code,
                );
            } catch (\App\Exceptions\IllegalTransition) {
            }
        }
        SafeLog::warning('order.fulfill.held', ['number' => $order->number, 'code' => $code]);
    }

    /**
     * @return array{attempt: string, order: string, message: ?string}
     */
    private function mapSupplierStatus(string $status): array
    {
        return match (strtolower($status)) {
            'completed', 'success', 'delivered' => [
                'attempt' => FulfillmentAttempt::STATUS_COMPLETED,
                'order' => Order::STATUS_DELIVERED,
                'message' => null,
            ],
            'partial' => [
                'attempt' => FulfillmentAttempt::STATUS_PARTIAL,
                'order' => Order::STATUS_PARTIAL,
                'message' => 'بخشی از این سفارش تحویل شد.',
            ],
            'refunded', 'refund' => [
                'attempt' => FulfillmentAttempt::STATUS_REFUNDED,
                'order' => Order::STATUS_REFUND_PENDING,
                'message' => 'بازگشت وجه در حال بررسی است.',
            ],
            'failed', 'error' => [
                'attempt' => FulfillmentAttempt::STATUS_FAILED,
                'order' => Order::STATUS_FAILED,
                'message' => CustomerError::message('INTERNAL_ERROR'),
            ],
            default => [
                'attempt' => FulfillmentAttempt::STATUS_PENDING,
                'order' => Order::STATUS_FULFILLING,
                'message' => null,
            ],
        };
    }
}

<?php

namespace App\Jobs;

use App\Exceptions\ApiException;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Modules\Fulfillment\FazerIdempotencyKey;
use App\Modules\Fulfillment\FazerOrderOutcome;
use App\Modules\Fulfillment\FazerSubmitter;
use App\Modules\Fulfillment\PollSchedule;
use App\Modules\Ops\AdminAlertService;
use App\Modules\Orders\OrderQuoteService;
use App\Modules\Payments\PaymentProviders;
use App\Modules\Orders\OrderStateMachine;
use App\Modules\Supplier\FazerCards\FazerCardsBalance;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Support\CustomerError;
use App\Support\Money;
use App\Support\SafeLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubmitFazerOrder implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 900;

    public int $tries = 12;

    public int $timeout = 90;

    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(public readonly string $orderId)
    {
        $this->onQueue('fulfillment');
    }

    public function uniqueId(): string
    {
        return $this->orderId;
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('order:'.$this->orderId))->releaseAfter(30)->expireAfter(300),
        ];
    }

    public function handle(
        OrderStateMachine $states,
        FazerSubmitter $submitter,
        FazerOrderOutcome $outcome,
        OrderQuoteService $quotes,
        FazerCardsBalance $balance,
        AdminAlertService $alerts,
    ): void {
        $prepared = DB::transaction(function () {
            $order = Order::query()->where('id', $this->orderId)->lockForUpdate()->first();
            if (! $order) {
                return null;
            }
            $status = OrderStateMachine::normalize((string) $order->status);
            if ($order->hold_until && $order->hold_until->isFuture()) {
                return ['hold' => true, 'until' => $order->hold_until];
            }
            if ($status !== Order::STATUS_PAID) {
                return null;
            }
            $order->loadMissing('sku');
            if (! $order->sku?->isFazerCards() && ! $order->sku?->supplierSku()?->isFazerCards()) {
                return null;
            }
            $item = $order->items()->orderBy('id')->first();
            if (! $item) {
                $item = $this->backfillItem($order);
            }
            $active = FulfillmentAttempt::query()
                ->where('order_item_id', $item->id)
                ->whereIn('status', FulfillmentAttempt::ACTIVE)
                ->first();
            if ($active) {
                if ($active->hasSupplierOrder() || in_array($active->status, [
                    FulfillmentAttempt::STATUS_CREATED,
                    FulfillmentAttempt::STATUS_PROCESSING,
                    FulfillmentAttempt::STATUS_UNKNOWN,
                ], true)) {
                    return ['poll' => true, 'attempt' => $active];
                }
                if ($active->status === FulfillmentAttempt::STATUS_SENDING) {
                    return null;
                }

                return ['attempt' => $active, 'item' => $item, 'order' => $order, 'resume' => true];
            }
            if (! $this->hasVerifiedShopPayment($order)) {
                $attemptNo = (int) FulfillmentAttempt::query()->where('order_item_id', $item->id)->max('attempt_no') + 1;
                $key = FazerIdempotencyKey::make((string) $order->number, (int) $item->id, $attemptNo);
                FulfillmentAttempt::query()->create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'attempt_no' => $attemptNo,
                    'idempotency_key' => $key,
                    'idempotent' => true,
                    'fazer_kind' => (string) $item->fulfillment_type,
                    'supplier' => 'fazercards',
                    'supplier_order_id' => substr(hash('sha256', $key), 0, 36),
                    'status' => FulfillmentAttempt::STATUS_FAILED,
                    'error_code' => 'SHOP_PAYMENT_REQUIRED',
                    'terminal_at' => now(),
                    'dry_run' => false,
                ]);
                $order->customer_message = CustomerError::message('SHOP_PAYMENT_REQUIRED');
                $states = app(OrderStateMachine::class);
                $states->transition($order, Order::STATUS_FAILED, 'system', 'SHOP_PAYMENT_REQUIRED');

                return null;
            }
            $attemptNo = (int) FulfillmentAttempt::query()->where('order_item_id', $item->id)->max('attempt_no') + 1;
            $family = (string) $item->fulfillment_type;
            $idempotent = ! in_array($family, [FazerCardsIds::TELEGRAM_STARS, FazerCardsIds::TELEGRAM_PREMIUM], true);
            $key = FazerIdempotencyKey::make((string) $order->number, (int) $item->id, $attemptNo);
            $attempt = FulfillmentAttempt::query()->create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'attempt_no' => $attemptNo,
                'idempotency_key' => $key,
                'idempotent' => $idempotent,
                'fazer_kind' => $family,
                'supplier' => 'fazercards',
                'supplier_order_id' => substr(hash('sha256', $key), 0, 36),
                'status' => FulfillmentAttempt::STATUS_QUEUED,
                'dry_run' => false,
            ]);

            return ['attempt' => $attempt, 'item' => $item, 'order' => $order, 'resume' => false];
        });

        if ($prepared === null) {
            return;
        }
        if (isset($prepared['hold'])) {
            $this->release(max(1, now()->diffInSeconds($prepared['until'])));

            return;
        }
        if (isset($prepared['poll'])) {
            RefreshFazerOrder::dispatch((int) $prepared['attempt']->id)->onQueue('fulfillment');

            return;
        }

        /** @var Order $order */
        $order = $prepared['order'];
        /** @var OrderItem $item */
        $item = $prepared['item'];
        /** @var FulfillmentAttempt $attempt */
        $attempt = $prepared['attempt'];

        try {
            $this->preflight($order, $item, $attempt, $quotes, $balance, $states, $alerts);
        } catch (ApiException $e) {
            $order->refresh();
            if ($order->hold_until && $order->hold_until->isFuture()) {
                $this->release(max(1, now()->diffInSeconds($order->hold_until)));
            }

            return;
        }

        DB::transaction(function () use ($attempt, $item, $order, $submitter, $states) {
            $fresh = FulfillmentAttempt::query()->where('id', $attempt->id)->lockForUpdate()->firstOrFail();
            $fresh->status = FulfillmentAttempt::STATUS_SENDING;
            $fresh->sending_at = now();
            $fresh->request_redacted = $submitter->requestPreview($item);
            $fresh->save();
            $locked = Order::query()->where('id', $order->id)->lockForUpdate()->firstOrFail();
            if (OrderStateMachine::normalize((string) $locked->status) === Order::STATUS_PAID) {
                $states->transition($locked, Order::STATUS_SUBMITTING, 'system', 'SUBMIT');
            }
        });
        $attempt->refresh();
        $order->refresh();

        try {
            $parsed = $submitter->send($attempt, $item);
            $outcome->applyParsed($attempt->fresh() ?? $attempt, $parsed, 'system');
            if (! empty($parsed['orderId'])) {
                $attempt->refresh();
                $attempt->next_poll_at = PollSchedule::nextAt($attempt->sending_at ?? now(), 0);
                $attempt->save();
                if (strtolower((string) ($parsed['status'] ?? '')) === 'completed') {
                    RefreshFazerOrder::dispatch($attempt->id)->onQueue('fulfillment');
                }
            }
        } catch (ApiException $e) {
            $outcome->applyException($attempt->fresh() ?? $attempt, $e);
            $attempt->refresh();
            $order->refresh();
            if ($order->hold_until && $order->hold_until->isFuture()) {
                $this->release(max(1, now()->diffInSeconds($order->hold_until)));
            }
            if ($attempt->status === FulfillmentAttempt::STATUS_UNKNOWN && ! $attempt->idempotent) {
                ReconcileUnknownAttempt::dispatch($attempt->id)->delay(now()->addSeconds(60))->onQueue('fulfillment');
            }
        }
    }

    public function failed(?Throwable $e): void
    {
        SafeLog::warning('order.fulfill.job_failed', [
            'orderId' => $this->orderId,
            'code' => 'JOB_FAILED',
        ]);
    }

    private function preflight(
        Order $order,
        OrderItem $item,
        FulfillmentAttempt $attempt,
        OrderQuoteService $quotes,
        FazerCardsBalance $balance,
        OrderStateMachine $states,
        AdminAlertService $alerts,
    ): void {
        $sku = $item->sku;
        if ($sku) {
            try {
                $fresh = $quotes->quote(
                    $sku,
                    (int) $item->quantity,
                    is_array($item->requirements) ? $item->requirements : [],
                    $item->steam_amount,
                    $item->steam_currency,
                );
                $tolerance = (float) config('charjino.orders.price_tolerance_pct', 2);
                $cap = function_exists('bcmul')
                    ? bcmul((string) $item->unit_cost_usd, (string) (1 + $tolerance / 100), 6)
                    : (string) ((float) $item->unit_cost_usd * (1 + $tolerance / 100));
                if (Money::compare($fresh['unitCostUsd'], $cap) > 0) {
                    $order->needs_review_reason = 'PRICE_INCREASED';
                    $states->transition($order, Order::STATUS_NEEDS_REVIEW, 'system', 'PRICE_INCREASED');
                    $alerts->open('PRICE_SPIKE', 'price:'.$order->id, ['number' => $order->number]);
                    throw new ApiException(CustomerError::message('PRICE_CHANGED'), 400, 'PRICE_CHANGED');
                }
            } catch (ApiException $e) {
                if ($e->errorCode === 'OUT_OF_STOCK') {
                    $attempt->error_code = 'OUT_OF_STOCK';
                    $attempt->save();
                    $order->needs_review_reason = 'OUT_OF_STOCK';
                    $states->transition($order, Order::STATUS_NEEDS_REVIEW, 'system', 'OUT_OF_STOCK');
                    $alerts->open('OUT_OF_STOCK', 'stock:'.$order->id, ['number' => $order->number]);
                }
                throw $e;
            }
        }
        if (! $balance->canAfford(Money::string($item->total_cost_usd), (string) $order->id)) {
            $alerts->open('LOW_BALANCE', 'balance', ['number' => $order->number]);
            $order->hold_reason = 'INSUFFICIENT_BALANCE';
            $minutes = (int) config('charjino.orders.balance_hold_retry_minutes', 5);
            $order->hold_until = now()->addMinutes(max(1, $minutes));
            $maxHours = (int) config('charjino.orders.balance_hold_max_hours', 12);
            if ($order->paid_at && $order->paid_at->lt(now()->subHours($maxHours))) {
                $order->needs_review_reason = 'INSUFFICIENT_BALANCE';
                $states->transition($order, Order::STATUS_NEEDS_REVIEW, 'system', 'INSUFFICIENT_BALANCE');
                throw new ApiException(CustomerError::message('TEMPORARILY_UNAVAILABLE'), 400, 'TEMPORARILY_UNAVAILABLE');
            }
            $order->save();
            throw new ApiException(CustomerError::message('TEMPORARILY_UNAVAILABLE'), 400, 'TEMPORARILY_UNAVAILABLE');
        }
    }

    private function hasVerifiedShopPayment(Order $order): bool
    {
        return $order->payments()
            ->where('status', Payment::STATUS_VERIFIED)
            ->whereIn('provider', PaymentProviders::shopPaid())
            ->exists();
    }

    private function backfillItem(Order $order): OrderItem
    {
        $order->loadMissing('sku.product');

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'sku_id' => $order->sku_id,
            'product_id' => $order->product_id,
            'fulfillment_type' => $order->fulfillment_type ?: $order->sku?->fulfillment_type,
            'requirements' => $order->requirements,
            'quantity' => $order->quantity,
            'unit_price_toman' => (int) $order->amount_toman,
            'total_toman' => (int) $order->amount_toman,
            'unit_cost_usd' => $order->cost_usd_snapshot,
            'total_cost_usd' => $order->cost_usd_snapshot,
            'fx_rate' => $order->fx_rate_snapshot,
            'margin_percent' => $order->margin_snapshot,
            'price_source' => 'synced',
            'priced_at' => now(),
            'supplier_ref' => [
                'category_id' => $order->sku?->fazercards_category_id,
                'offer_id' => $order->sku?->fazercards_offer_id,
                'region' => $order->sku?->region,
            ],
        ]);
    }
}

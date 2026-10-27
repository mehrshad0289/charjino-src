<?php

namespace App\Modules\Fulfillment;

use App\Exceptions\ApiException;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Modules\Ops\AdminAlertService;
use App\Modules\Orders\OrderDeliveryService;
use App\Modules\Orders\OrderStateMachine;
use App\Modules\Orders\RefundService;
use App\Support\CustomerError;

class FazerOrderOutcome
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly OrderDeliveryService $delivery,
        private readonly RefundService $refunds,
        private readonly AdminAlertService $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $parsed
     */
    public function applyParsed(FulfillmentAttempt $attempt, array $parsed, string $actor = 'system'): Order
    {
        $order = $attempt->order;
        if (! $order) {
            throw new ApiException('سفارش پیدا نشد.', 404, 'ORDER_NOT_FOUND');
        }
        if (! empty($parsed['orderId']) && ! str_starts_with((string) $parsed['orderId'], 'cj-')) {
            $attempt->fazer_order_id = (string) $parsed['orderId'];
        }
        $next = $this->delivery->storeFromFazer($attempt, $parsed);
        $status = strtolower(trim((string) ($parsed['status'] ?? '')));
        if (! in_array($status, ['created', 'processing', 'completed', 'failed', 'refund', 'refunded', 'cancelled'], true)) {
            $attempt->status = FulfillmentAttempt::STATUS_UNKNOWN;
            $attempt->unknown_since = $attempt->unknown_since ?? now();
            $attempt->fazer_status = $status !== '' ? $status : $attempt->fazer_status;
            $attempt->save();
            $this->alerts->open('FAZER_STATUS_UNKNOWN', 'fazer-status:'.$attempt->id, [
                'order_number' => $order->number,
                'fazer_status' => $status,
                'fazer_order_id' => $attempt->fazer_order_id,
            ]);
        }
        if ($next === Order::STATUS_NEEDS_REVIEW) {
            $order->needs_review_reason = $order->needs_review_reason ?: 'SHORT_DELIVERY';
            $order->save();

            return $this->states->transition($order, Order::STATUS_NEEDS_REVIEW, $actor, 'SHORT_DELIVERY');
        }
        if ($next === Order::STATUS_FAILED) {
            $order = $this->states->transition($order, Order::STATUS_FAILED, $actor, 'FAZER_FAILED');

            return $this->refunds->onDefinitiveFailure($order, 'FAZER_FAILED');
        }
        $attempt->next_poll_at = now()->addSeconds(30);
        $attempt->save();

        return $this->states->applyFazerStatus($order, (string) ($parsed['status'] ?? ''), $actor);
    }

    public function applyException(FulfillmentAttempt $attempt, ApiException $e): void
    {
        $order = $attempt->order;
        if (! $order) {
            return;
        }
        $attempt->http_status = $e->status;
        $attempt->error_code = $e->errorCode;
        $code = (string) $e->errorCode;
        if ($code === 'INSUFFICIENT_BALANCE') {
            $attempt->status = FulfillmentAttempt::STATUS_QUEUED;
            $attempt->sending_at = null;
            $attempt->save();
            $order->hold_reason = 'INSUFFICIENT_BALANCE';
            $order->customer_message = CustomerError::message('INSUFFICIENT_BALANCE');
            $minutes = (int) config('charjino.orders.balance_hold_retry_minutes', 5);
            $order->hold_until = now()->addMinutes(max(1, $minutes));
            $order->save();
            $this->alerts->open('LOW_BALANCE', 'balance', ['number' => $order->number]);
            $status = OrderStateMachine::normalize((string) $order->status);
            if ($status !== Order::STATUS_PAID) {
                $this->states->transition($order, Order::STATUS_PAID, 'system', 'INSUFFICIENT_BALANCE');
            }

            return;
        }
        if (in_array($code, [
            'OUT_OF_STOCK',
            'PRODUCT_UNAVAILABLE',
            'PRICE_CHANGED',
            'PRICE_INCREASED',
            'TEMPORARILY_UNAVAILABLE',
        ], true)) {
            $attempt->status = FulfillmentAttempt::STATUS_QUEUED;
            $attempt->sending_at = null;
            $attempt->save();
            $order->needs_review_reason = $code;
            $order->customer_message = CustomerError::message('INSUFFICIENT_BALANCE');
            $order->save();
            $this->alerts->open('NEEDS_REVIEW', 'fulfill:'.$order->id, [
                'order_number' => $order->number,
                'code' => $code,
            ]);
            $status = OrderStateMachine::normalize((string) $order->status);
            if ($status !== Order::STATUS_NEEDS_REVIEW) {
                $this->states->transition($order, Order::STATUS_NEEDS_REVIEW, 'system', $code);
            }

            return;
        }
        if ($e->status === 429 || $code === 'SUPPLIER_RATE_LIMITED' || $code === 'RATE_LIMIT_EXCEEDED') {
            $attempt->status = FulfillmentAttempt::STATUS_QUEUED;
            $attempt->save();
            $order->hold_reason = 'RATE_LIMITED';
            $retry = max(1, (int) ($e->retryAfter ?: 15));
            $order->hold_until = now()->addSeconds((int) round($retry * (0.85 + (mt_rand(0, 30) / 100))));
            $order->save();
            $this->states->transition($order, Order::STATUS_PAID, 'system', 'SUPPLIER_RATE_LIMITED');

            return;
        }
        if (in_array($e->status, [401, 403], true) || $code === 'SUPPLIER_BLOCKED') {
            $attempt->status = FulfillmentAttempt::STATUS_QUEUED;
            $attempt->save();
            $order->hold_reason = 'SUPPLIER_BLOCKED';
            $order->hold_until = now()->addMinutes(10);
            $order->save();
            $this->states->transition($order, Order::STATUS_PAID, 'system', 'SUPPLIER_BLOCKED');

            return;
        }
        if ($e->status === 409 || $code === 'IDEMPOTENCY_CONFLICT') {
            $attempt->status = FulfillmentAttempt::STATUS_FAILED;
            $attempt->error_code = $code !== '' ? $code : 'IDEMPOTENCY_CONFLICT';
            $attempt->fail_reason = $e->getMessage();
            $attempt->http_status = $e->status;
            $attempt->terminal_at = now();
            $attempt->save();
            $order->needs_review_reason = $attempt->error_code;
            $this->states->transition($order, Order::STATUS_NEEDS_REVIEW, 'system', $attempt->error_code);

            return;
        }
        if ($e->status >= 500 || in_array($code, ['SUPPLIER_TIMEOUT', 'SUPPLIER_5XX', 'SUPPLIER_UNREACHABLE'], true)) {
            $attempt->status = FulfillmentAttempt::STATUS_UNKNOWN;
            $attempt->unknown_since = $attempt->unknown_since ?? now();
            $attempt->save();

            return;
        }
        if ($e->status === 400 || $e->status === 404 || ($e->status >= 400 && $e->status < 500 && $e->status !== 409 && $e->status !== 429)) {
            $attempt->status = FulfillmentAttempt::STATUS_FAILED;
            $attempt->terminal_at = now();
            $attempt->save();
            $order->customer_message = CustomerError::message($code);
            $order = $this->states->transition($order, Order::STATUS_FAILED, 'system', $code);
            $this->refunds->onDefinitiveFailure($order, $code ?: 'SUPPLIER_REJECTED');
        }
    }
}

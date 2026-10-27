<?php

namespace App\Modules\Orders;

use App\Events\OrderStatusChanged;
use App\Exceptions\IllegalTransition;
use App\Models\Order;
use App\Models\OrderTransition;
use Illuminate\Support\Facades\DB;

final class OrderStateMachine
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        Order::STATUS_DRAFT => [Order::STATUS_AWAITING_PAYMENT, Order::STATUS_CANCELLED, Order::STATUS_EXPIRED],
        Order::STATUS_AWAITING_PAYMENT => [Order::STATUS_CANCELLED, Order::STATUS_EXPIRED, Order::STATUS_PAID],
        Order::STATUS_PAID => [
            Order::STATUS_PAID,
            Order::STATUS_SUBMITTING,
            Order::STATUS_PROCESSING,
            Order::STATUS_COMPLETED,
            Order::STATUS_NEEDS_REVIEW,
            Order::STATUS_FAILED,
            Order::STATUS_REFUND_PENDING,
        ],
        Order::STATUS_SUBMITTING => [
            Order::STATUS_PAID,
            Order::STATUS_SUBMITTED,
            Order::STATUS_PROCESSING,
            Order::STATUS_COMPLETED,
            Order::STATUS_FAILED,
            Order::STATUS_NEEDS_REVIEW,
            Order::STATUS_REFUND_PENDING,
        ],
        Order::STATUS_SUBMITTED => [
            Order::STATUS_PROCESSING,
            Order::STATUS_COMPLETED,
            Order::STATUS_FAILED,
            Order::STATUS_NEEDS_REVIEW,
            Order::STATUS_REFUND_PENDING,
        ],
        Order::STATUS_PROCESSING => [
            Order::STATUS_COMPLETED,
            Order::STATUS_FAILED,
            Order::STATUS_NEEDS_REVIEW,
            Order::STATUS_REFUND_PENDING,
        ],
        Order::STATUS_NEEDS_REVIEW => [
            Order::STATUS_PAID,
            Order::STATUS_PROCESSING,
            Order::STATUS_COMPLETED,
            Order::STATUS_FAILED,
            Order::STATUS_REFUND_PENDING,
        ],
        Order::STATUS_FAILED => [Order::STATUS_PAID, Order::STATUS_REFUND_PENDING, Order::STATUS_REFUNDED],
        Order::STATUS_COMPLETED => [Order::STATUS_REFUND_PENDING],
        Order::STATUS_REFUND_PENDING => [Order::STATUS_REFUNDED],
        Order::STATUS_CANCELLED => [],
        Order::STATUS_EXPIRED => [Order::STATUS_REFUND_PENDING, Order::STATUS_REFUNDED],
        Order::STATUS_REFUNDED => [],
    ];

    /** @var array<string, int> */
    private const RANK = [
        Order::STATUS_SUBMITTING => 1,
        Order::STATUS_SUBMITTED => 2,
        Order::STATUS_PROCESSING => 3,
        Order::STATUS_COMPLETED => 100,
        Order::STATUS_FAILED => 100,
        Order::STATUS_REFUNDED => 100,
    ];

    public static function normalize(string $status): string
    {
        return match ($status) {
            'pending_payment' => Order::STATUS_AWAITING_PAYMENT,
            'fulfilling' => Order::STATUS_PROCESSING,
            'delivered' => Order::STATUS_COMPLETED,
            'partial' => Order::STATUS_NEEDS_REVIEW,
            default => $status,
        };
    }

    public static function isFinal(string $status): bool
    {
        $status = self::normalize($status);

        return in_array($status, [Order::STATUS_COMPLETED, Order::STATUS_FAILED, Order::STATUS_REFUNDED, Order::STATUS_CANCELLED, Order::STATUS_EXPIRED], true);
    }

    public function transition(Order $order, string $to, string $actor, ?string $code = null, ?string $note = null): Order
    {
        $to = self::normalize($to);
        $changed = false;
        $from = '';

        $fresh = DB::transaction(function () use ($order, $to, $actor, $code, $note, &$changed, &$from) {
            $fresh = Order::query()->where('id', $order->id)->lockForUpdate()->firstOrFail();
            $from = self::normalize((string) $fresh->status);
            if ($from === $to && $to !== Order::STATUS_PAID) {
                return $fresh;
            }
            $allowed = self::ALLOWED[$from] ?? [];
            if (! in_array($to, $allowed, true)) {
                throw new IllegalTransition($from, $to);
            }
            $fresh->status = $to;
            $fresh->appendEvent('transition', array_filter(['code' => $code, 'note' => $note]));
            if ($to === Order::STATUS_PAID && $fresh->paid_at === null) {
                $fresh->paid_at = now();
            }
            if ($to === Order::STATUS_COMPLETED) {
                $fresh->completed_at = now();
            }
            if ($to === Order::STATUS_FAILED) {
                $fresh->failed_at = now();
            }
            if ($to === Order::STATUS_REFUNDED) {
                $fresh->refunded_at = now();
            }
            if ($to !== Order::STATUS_PAID) {
                $fresh->hold_reason = null;
                $fresh->hold_until = null;
            }
            $fresh->save();
            OrderTransition::query()->create([
                'order_id' => $fresh->id,
                'from' => $from,
                'to' => $to,
                'actor' => $actor,
                'code' => $code,
                'note' => $note,
                'created_at' => now(),
            ]);
            $changed = true;

            return $fresh;
        });

        if ($changed) {
            OrderStatusChanged::dispatch($fresh->id, $from, $to, $actor, $code);
        }

        return $fresh;
    }

    public function applyFazerStatus(Order $order, string $fazerStatus, string $actor, ?string $code = null): Order
    {
        $target = match (strtolower(trim($fazerStatus))) {
            'created' => Order::STATUS_SUBMITTED,
            'processing' => Order::STATUS_PROCESSING,
            'completed' => Order::STATUS_COMPLETED,
            'failed', 'refund', 'refunded', 'cancelled' => Order::STATUS_FAILED,
            default => null,
        };
        if ($target === null) {
            return $order;
        }
        $current = self::normalize((string) $order->status);
        if (self::isFinal($current) && $target !== Order::STATUS_REFUND_PENDING) {
            return $order;
        }
        $currentRank = self::RANK[$current] ?? 0;
        $targetRank = self::RANK[$target] ?? 0;
        if ($currentRank >= 100) {
            return $order;
        }
        if ($targetRank > 0 && $targetRank <= $currentRank && $targetRank < 100) {
            return $order;
        }
        if ($current === $target) {
            return $order;
        }

        return $this->transition($order, $target, $actor, $code);
    }
}

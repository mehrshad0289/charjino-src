<?php

namespace App\Modules\Orders;

use App\Exceptions\ApiException;
use App\Models\DeliveredItem;
use App\Models\Order;
use App\Models\Refund;
use App\Modules\Ops\AdminAlertService;
use App\Modules\Wallet\WalletService;
use App\Support\CustomerError;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly WalletService $wallets,
        private readonly AdminAlertService $alerts,
    ) {}

    public function onDefinitiveFailure(Order $order, string $reasonCode): Order
    {
        $order->loadMissing('items');
        if (DeliveredItem::query()->where('order_id', $order->id)->exists()) {
            return $this->states->transition($order, Order::STATUS_NEEDS_REVIEW, 'system', 'PARTIAL_DELIVERY');
        }
        $mode = (string) config('charjino.orders.refund_mode', 'admin');
        if ($mode === 'wallet_auto') {
            return $this->refundToWallet($order, 'system', $reasonCode);
        }
        $order = $this->states->transition($order, Order::STATUS_REFUND_PENDING, 'system', $reasonCode);
        $this->ensurePendingRefund($order, $reasonCode, 'system');
        $this->alerts->open('NEEDS_REVIEW', 'refund:'.$order->id, [
            'order_number' => $order->number,
            'code' => $reasonCode,
        ]);

        return $order;
    }

    public function refundToWallet(Order $order, string $actor, string $reasonCode = 'ADMIN', ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $actor, $reasonCode, $note) {
            $fresh = Order::query()->where('id', $order->id)->lockForUpdate()->firstOrFail();
            $existing = Refund::query()
                ->where('order_id', $fresh->id)
                ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_COMPLETED])
                ->first();
            if ($existing && $existing->status === Refund::STATUS_COMPLETED) {
                return $fresh;
            }
            $refund = $existing ?? new Refund;
            $refund->order_id = $fresh->id;
            $refund->amount_toman = (int) $fresh->amount_toman;
            $refund->method = Refund::METHOD_WALLET;
            $refund->status = Refund::STATUS_COMPLETED;
            $refund->reason_code = $reasonCode;
            $refund->requested_by = $actor;
            $refund->decided_by_admin_id = str_starts_with($actor, 'admin:') ? (int) substr($actor, 6) : null;
            $refund->decided_at = now();
            $refund->note = $note;
            $refund->save();
            $tx = $this->wallets->credit(
                (string) $fresh->user_id,
                (int) $fresh->amount_toman,
                'refund',
                'refund:'.$refund->id,
                $fresh->id,
                $refund->id,
            );
            $refund->wallet_transaction_id = $tx->id;
            $refund->save();
            $fresh->customer_message = 'مبلغ این سفارش به کیف پول شارژینو برگشت.';

            return $this->states->transition($fresh, Order::STATUS_REFUNDED, $actor, $reasonCode, $note);
        });
    }

    public function markManual(Order $order, string $actor, string $externalRef, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $actor, $externalRef, $note) {
            $fresh = Order::query()->where('id', $order->id)->lockForUpdate()->firstOrFail();
            $refund = $this->ensurePendingRefund($fresh, 'MANUAL_GATEWAY', $actor);
            $refund->method = Refund::METHOD_MANUAL;
            $refund->status = Refund::STATUS_COMPLETED;
            $refund->external_ref = $externalRef;
            $refund->note = $note;
            $refund->decided_at = now();
            $refund->decided_by_admin_id = str_starts_with($actor, 'admin:') ? (int) substr($actor, 6) : null;
            $refund->save();
            $fresh->customer_message = 'مبلغ این سفارش برگشت داده شد.';

            return $this->states->transition($fresh, Order::STATUS_REFUNDED, $actor, 'MANUAL_REFUND', $note);
        });
    }

    private function ensurePendingRefund(Order $order, string $reasonCode, string $actor): Refund
    {
        $existing = Refund::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_COMPLETED])
            ->first();
        if ($existing) {
            return $existing;
        }

        return Refund::query()->create([
            'order_id' => $order->id,
            'amount_toman' => (int) $order->amount_toman,
            'method' => Refund::METHOD_WALLET,
            'status' => Refund::STATUS_PENDING,
            'reason_code' => $reasonCode,
            'requested_by' => $actor,
        ]);
    }

    public function activeRefund(Order $order): ?Refund
    {
        return Refund::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_COMPLETED])
            ->latest('id')
            ->first();
    }
}

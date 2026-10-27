<?php

namespace App\Console\Commands;

use App\Jobs\RefreshFazerOrder;
use App\Jobs\SubmitFazerOrder;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Modules\Fulfillment\FulfillmentService;
use App\Modules\Orders\OrderService;
use App\Modules\Orders\OrderStateMachine;
use Illuminate\Console\Command;

class PollStuckFulfillmentCommand extends Command
{
    protected $signature = 'charjino:poll-fulfillment';

    protected $description = 'Expire unpaid drafts, poll Shop2, and refresh Fazer attempts';

    public function handle(OrderService $orders, FulfillmentService $fulfillment): int
    {
        $expired = $orders->expireUnpaid();
        $polled = $fulfillment->pollStuck();
        $budget = (int) config('charjino.orders.poll_budget_per_min', 80);
        $fazer = FulfillmentAttempt::query()
            ->where('supplier', 'fazercards')
            ->whereIn('status', [FulfillmentAttempt::STATUS_CREATED, FulfillmentAttempt::STATUS_PROCESSING])
            ->whereNotNull('fazer_order_id')
            ->where(function ($q) {
                $q->whereNull('next_poll_at')->orWhere('next_poll_at', '<=', now());
            })
            ->orderBy('next_poll_at')
            ->limit($budget)
            ->pluck('id');
        foreach ($fazer as $id) {
            RefreshFazerOrder::dispatch((int) $id)->onQueue('fulfillment');
        }
        FulfillmentAttempt::query()
            ->where('supplier', 'fazercards')
            ->where('status', FulfillmentAttempt::STATUS_SENDING)
            ->where('sending_at', '<=', now()->subMinutes(2))
            ->update([
                'status' => FulfillmentAttempt::STATUS_UNKNOWN,
                'unknown_since' => now(),
            ]);
        $paid = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->where(function ($q) {
                $q->whereNull('hold_until')->orWhere('hold_until', '<=', now());
            })
            ->where('updated_at', '<=', now()->subMinutes(2))
            ->where(function ($q) {
                $q->whereDoesntHave('fulfillmentAttempts', fn ($a) => $a->whereIn('status', FulfillmentAttempt::ACTIVE))
                    ->orWhereHas('fulfillmentAttempts', function ($a) {
                        $a->where('supplier', 'fazercards')
                            ->where('status', FulfillmentAttempt::STATUS_QUEUED)
                            ->whereNull('sending_at');
                    });
            })
            ->pluck('id');
        foreach ($paid as $id) {
            SubmitFazerOrder::dispatch((string) $id)->onQueue('fulfillment');
        }
        $maxHours = (int) config('charjino.orders.poll_max_hours', 24);
        $stuck = FulfillmentAttempt::query()
            ->where('supplier', 'fazercards')
            ->whereIn('status', FulfillmentAttempt::ACTIVE)
            ->where('sending_at', '<=', now()->subHours($maxHours))
            ->with('order')
            ->get();
        $states = app(OrderStateMachine::class);
        foreach ($stuck as $attempt) {
            if ($attempt->order) {
                $attempt->order->needs_review_reason = 'STUCK_24H';
                try {
                    $states->transition($attempt->order, Order::STATUS_NEEDS_REVIEW, 'poller', 'STUCK_24H');
                } catch (\Throwable) {
                }
            }
        }
        cache()->put('scheduler:poll-fulfillment', now()->toIso8601String(), 3600);
        $this->info("expired={$expired} polled={$polled} fazer=".$fazer->count());

        return self::SUCCESS;
    }
}

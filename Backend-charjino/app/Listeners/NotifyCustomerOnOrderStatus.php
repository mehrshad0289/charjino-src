<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Modules\Identity\SmsPatternClient;
use App\Modules\Orders\OrderStateMachine;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyCustomerOnOrderStatus implements ShouldQueue
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = Order::query()->with('user')->find($event->orderId);
        if (! $order) {
            return;
        }
        $to = OrderStateMachine::normalize($event->to);
        $notified = is_array($order->notified_statuses) ? $order->notified_statuses : [];
        if (in_array($to, $notified, true)) {
            return;
        }
        $template = match ($to) {
            Order::STATUS_COMPLETED => (string) config('charjino.sms.order_completed_template'),
            Order::STATUS_REFUND_PENDING, Order::STATUS_FAILED => (string) config('charjino.sms.order_failed_template'),
            Order::STATUS_REFUNDED => (string) config('charjino.sms.order_refunded_template'),
            default => '',
        };
        $phone = (string) ($order->user?->phone ?? '');
        if ($template !== '' && $phone !== '') {
            try {
                app(SmsPatternClient::class)->sendToken($phone, $template, ['number' => $order->number]);
            } catch (\Throwable) {
            }
        }
        $notified[] = $to;
        $order->notified_statuses = array_values(array_unique($notified));
        $order->save();
    }
}

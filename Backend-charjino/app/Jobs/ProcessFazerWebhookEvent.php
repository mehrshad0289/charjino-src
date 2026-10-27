<?php

namespace App\Jobs;

use App\Models\FulfillmentAttempt;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFazerWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $row = WebhookEvent::query()->find($this->eventId);
        if (! $row || $row->status === 'processed') {
            return;
        }
        if (in_array($row->event, ['webhook.test'], true)) {
            $row->status = 'processed';
            $row->processed_at = now();
            $row->save();

            return;
        }
        if (str_starts_with((string) $row->event, 'manual_service.')) {
            $row->status = 'ignored';
            $row->processed_at = now();
            $row->save();

            return;
        }
        $fazerId = (string) $row->fazer_order_id;
        $attempt = $fazerId !== ''
            ? FulfillmentAttempt::query()->where('fazer_order_id', $fazerId)->first()
            : null;
        if ($row->event === 'order.created') {
            $row->attempt_id = $attempt?->id;
            $row->status = $attempt ? 'processed' : 'unmatched';
            $row->processed_at = now();
            $row->save();

            return;
        }
        if ($row->event === 'order.status_changed') {
            if (! $attempt) {
                $row->status = 'unmatched';
                $row->save();
                if ($this->attempts() < 10) {
                    $this->release(30);
                }

                return;
            }
            $attempt->last_webhook_at = now();
            $attempt->save();
            $row->attempt_id = $attempt->id;
            $row->status = 'processed';
            $row->processed_at = now();
            $row->save();
            // Payload status is not applied: FazerCards does not document webhook signature/body. Refresh via GET /orders/{id}.
            RefreshFazerOrder::dispatch($attempt->id)->onQueue('fulfillment');
        }
    }
}

<?php

namespace App\Jobs;

use App\Modules\Fulfillment\Shop2WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessShop2Webhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('default');
    }

    public function handle(Shop2WebhookService $webhooks): void
    {
        $webhooks->process($this->eventId);
    }
}

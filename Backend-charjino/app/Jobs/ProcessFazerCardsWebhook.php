<?php

namespace App\Jobs;

use App\Modules\Fulfillment\FazerCardsWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessFazerCardsWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('default');
    }

    public function handle(FazerCardsWebhookService $webhooks): void
    {
        $webhooks->process($this->eventId);
    }
}

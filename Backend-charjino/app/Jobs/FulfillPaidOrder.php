<?php

namespace App\Jobs;

use App\Modules\Fulfillment\FulfillmentService;
use App\Support\SafeLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FulfillPaidOrder implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 900;

    public function __construct(public readonly string $orderId)
    {
        $this->onQueue('fulfillment');
    }

    public function uniqueId(): string
    {
        return $this->orderId;
    }

    public function handle(FulfillmentService $fulfillment): void
    {
        $fulfillment->fulfill($this->orderId);
    }

    public function failed(?Throwable $e): void
    {
        SafeLog::warning('order.fulfill.job_failed', [
            'orderId' => $this->orderId,
            'code' => $e?->getMessage() !== null ? 'JOB_FAILED' : null,
        ]);
    }
}

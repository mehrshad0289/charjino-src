<?php

namespace App\Jobs;

use App\Models\FulfillmentAttempt;
use App\Modules\Fulfillment\FazerReconciler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileUnknownAttempt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $attemptId)
    {
        $this->onQueue('fulfillment');
    }

    public function handle(FazerReconciler $reconciler): void
    {
        $attempt = FulfillmentAttempt::query()->find($this->attemptId);
        if (! $attempt || $attempt->status !== FulfillmentAttempt::STATUS_UNKNOWN) {
            return;
        }
        $reconciler->reconcile($attempt);
    }
}

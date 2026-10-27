<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileUnknownAttempt;
use App\Models\FulfillmentAttempt;
use Illuminate\Console\Command;

class ReconcileFazerCommand extends Command
{
    protected $signature = 'charjino:reconcile-fazer';

    protected $description = 'Reconcile unknown FazerCards fulfillment attempts';

    public function handle(): int
    {
        $ids = FulfillmentAttempt::query()
            ->where('status', FulfillmentAttempt::STATUS_UNKNOWN)
            ->where('supplier', 'fazercards')
            ->pluck('id');
        foreach ($ids as $id) {
            ReconcileUnknownAttempt::dispatch((int) $id)->onQueue('fulfillment');
        }
        cache()->put('scheduler:reconcile-fazer', now()->toIso8601String(), 3600);
        $this->info('queued='.$ids->count());

        return self::SUCCESS;
    }
}

<?php

namespace App\Jobs\SmartSearch;

use App\Modules\SmartSearch\SearchIndexWriteRegistry;
use App\Modules\SmartSearch\SmartSearchSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductSearchDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 120];

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(public readonly array $productIds)
    {
        $this->onQueue((string) config('smart-search.queue', 'search'));
        $this->afterCommit();
    }

    public function handle(): void
    {
        if (SearchIndexWriteRegistry::barrier()) {
            $this->release(8);

            return;
        }
        if (! SmartSearchSyncService::writesAreEnabled() || $this->productIds === []) {
            return;
        }
        SmartSearchSyncService::make()->syncProducts(array_values(array_unique(array_map('intval', $this->productIds))));
    }
}

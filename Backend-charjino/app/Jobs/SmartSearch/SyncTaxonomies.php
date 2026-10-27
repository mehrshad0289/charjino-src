<?php

namespace App\Jobs\SmartSearch;

use App\Modules\SmartSearch\SearchIndexWriteRegistry;
use App\Modules\SmartSearch\SmartSearchSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncTaxonomies implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public int $uniqueFor = 60;

    public function __construct(public readonly ?int $groupId = null)
    {
        $this->onQueue((string) config('smart-search.queue', 'search'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'taxonomies:'.($this->groupId ?? 'all');
    }

    public function handle(): void
    {
        if (SearchIndexWriteRegistry::barrier()) {
            $this->release(5);

            return;
        }
        if (! SmartSearchSyncService::writesAreEnabled()) {
            return;
        }
        $sync = SmartSearchSyncService::make();
        foreach (SearchIndexWriteRegistry::writeTargets()['taxonomies'] as $uid) {
            $sync->rebuildTaxonomies($uid);
        }
        if ($this->groupId) {
            $ids = \App\Models\Product::query()->where('catalog_group_id', $this->groupId)->pluck('id')->all();
            foreach (array_chunk($ids, 80) as $chunk) {
                SyncProductSearchDocuments::dispatch(array_map('intval', $chunk));
            }
        }
    }
}

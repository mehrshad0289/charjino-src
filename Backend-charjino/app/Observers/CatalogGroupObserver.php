<?php

namespace App\Observers;

use App\Jobs\SmartSearch\SyncTaxonomies;
use App\Models\CatalogGroup;
use App\Modules\SmartSearch\SmartSearchSyncService;

class CatalogGroupObserver
{
    public function saved(CatalogGroup $group): void
    {
        $this->sync($group);
    }

    public function deleted(CatalogGroup $group): void
    {
        $this->sync($group);
    }

    private function sync(CatalogGroup $group): void
    {
        if (! SmartSearchSyncService::writesAreEnabled()) {
            return;
        }
        SyncTaxonomies::dispatch((int) $group->id);
    }
}

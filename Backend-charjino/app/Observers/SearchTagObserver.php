<?php

namespace App\Observers;

use App\Jobs\SmartSearch\SyncBrandSearchDocument;
use App\Jobs\SmartSearch\SyncProductSearchDocuments;
use App\Models\SearchTag;
use App\Modules\SmartSearch\SmartSearchSyncService;

class SearchTagObserver
{
    public function saved(SearchTag $tag): void
    {
        $this->sync($tag);
    }

    public function deleted(SearchTag $tag): void
    {
        $this->sync($tag);
    }

    private function sync(SearchTag $tag): void
    {
        if (! SmartSearchSyncService::writesAreEnabled()) {
            return;
        }
        SyncBrandSearchDocument::dispatch();
        $ids = $tag->products()->pluck('products.id')->map(fn ($id) => (int) $id)->all();
        foreach (array_chunk($ids, 80) as $chunk) {
            SyncProductSearchDocuments::dispatch($chunk);
        }
    }
}

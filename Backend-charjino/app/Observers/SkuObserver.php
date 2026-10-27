<?php

namespace App\Observers;

use App\Jobs\SmartSearch\SyncProductSearchDocument;
use App\Models\Sku;
use App\Modules\SmartSearch\SmartSearchSyncService;

class SkuObserver
{
    public function saved(Sku $sku): void
    {
        $this->sync($sku);
    }

    public function deleted(Sku $sku): void
    {
        $this->sync($sku);
    }

    private function sync(Sku $sku): void
    {
        if (! SmartSearchSyncService::writesAreEnabled()) {
            return;
        }
        $productId = (int) $sku->product_id;
        if ($productId < 1) {
            return;
        }
        SyncProductSearchDocument::dispatch($productId);
    }
}

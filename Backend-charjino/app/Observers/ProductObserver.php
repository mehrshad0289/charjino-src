<?php

namespace App\Observers;

use App\Jobs\SmartSearch\SyncProductSearchDocument;
use App\Models\Product;
use App\Modules\SmartSearch\SmartSearchSyncService;

class ProductObserver
{
    public function saved(Product $product): void
    {
        $this->sync($product);
    }

    public function deleted(Product $product): void
    {
        $this->sync($product);
    }

    private function sync(Product $product): void
    {
        if (! SmartSearchSyncService::writesAreEnabled()) {
            return;
        }
        SyncProductSearchDocument::dispatch((int) $product->id, (string) $product->handle);
    }
}

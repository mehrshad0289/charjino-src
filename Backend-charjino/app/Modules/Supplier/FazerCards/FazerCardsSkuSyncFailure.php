<?php

namespace App\Modules\Supplier\FazerCards;

use App\Models\Sku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FazerCardsSkuSyncFailure
{
    public static function markProduct(int $productId): void
    {
        if ($productId < 1 || ! self::columnsReady()) {
            return;
        }

        Sku::query()
            ->where('product_id', $productId)
            ->update([
                'sync_failed_count' => DB::raw('sync_failed_count + 1'),
                'last_sync_failed_at' => now(),
            ]);
    }

    public static function clearCount(int $productId): void
    {
        if ($productId < 1 || ! self::columnsReady()) {
            return;
        }

        Sku::query()
            ->where('product_id', $productId)
            ->update([
                'sync_failed_count' => 0,
            ]);
    }

    private static function columnsReady(): bool
    {
        return Schema::hasColumn('skus', 'sync_failed_count')
            && Schema::hasColumn('skus', 'last_sync_failed_at');
    }
}

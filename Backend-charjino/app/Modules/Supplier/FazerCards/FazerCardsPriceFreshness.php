<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;
use App\Models\Sku;
use App\Support\CustomerError;

final class FazerCardsPriceFreshness
{
    public static function blocksSale(Sku $sku): bool
    {
        if (! (bool) $sku->getAttribute('visible')) {
            return false;
        }
        $sku->loadMissing('product');
        $productCat = trim((string) ($sku->product?->fazercards_category_id ?? ''));
        $skuCat = trim((string) ($sku->fazercards_category_id ?? ''));
        if ($productCat === '' && $skuCat === '') {
            return false;
        }
        $seconds = max(1, (int) config('charjino.orders.price_fresh_seconds', 86400));
        if ($sku->synced_at === null) {
            return true;
        }

        return $sku->synced_at->lte(now()->subSeconds($seconds));
    }

    public static function assertPurchasable(Sku $sku): void
    {
        if (self::blocksSale($sku)) {
            throw new ApiException(CustomerError::message('SKU_UNAVAILABLE'), 400, 'SKU_UNAVAILABLE');
        }
    }
}

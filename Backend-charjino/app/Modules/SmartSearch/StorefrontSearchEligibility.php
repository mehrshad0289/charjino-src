<?php

namespace App\Modules\SmartSearch;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Modules\Supplier\FazerCards\FazerCardsSeeds;
use Illuminate\Database\Eloquent\Builder;

final class StorefrontSearchEligibility
{
    /**
     * @param  Builder<Product>  $query
     */
    public static function constrainProducts(Builder $query): void
    {
        $query->where('products.status', 'active')
            ->where('products.visible', true)
            ->notStorefrontExcluded()
            ->whereHas('skus', fn (Builder $sku) => $sku->where('status', 'active')->where('visible', true))
            ->where(function (Builder $inner) {
                $inner->whereNull('catalog_group_id')
                    ->orWhereHas('catalogGroup', fn (Builder $group) => $group->where('visible', true));
            });
    }

    public static function productIsEligible(Product $product): bool
    {
        if ($product->status !== 'active' || ! $product->visible || $product->isStorefrontExcluded()) {
            return false;
        }
        $group = $product->relationLoaded('catalogGroup') ? $product->catalogGroup : $product->catalogGroup()->first();
        if ($group instanceof CatalogGroup && ! $group->visible) {
            return false;
        }
        $hasSku = $product->skus()
            ->where('status', 'active')
            ->where('visible', true)
            ->exists();
        if (! $hasSku) {
            return false;
        }

        return ! self::isForbidden($product);
    }

    public static function isForbidden(Product $product): bool
    {
        if ($product->isStorefrontExcluded()) {
            return true;
        }
        $hay = trim($product->title.' '.$product->name_en.' '.$product->handle.' '.$product->subtitle);

        return FazerCardsSeeds::isForbiddenStorefront($hay);
    }
}

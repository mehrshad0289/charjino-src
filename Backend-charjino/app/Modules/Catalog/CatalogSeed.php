<?php

namespace App\Modules\Catalog;

use App\Models\Banner;
use App\Models\CatalogGroup;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CatalogSeed
{
    /**
     * @return list<string>
     */
    public static function handles(): array
    {
        $catalog = require database_path('data/catalog.php');

        return collect($catalog['products'] ?? [])
            ->pluck('handle')
            ->filter(fn ($handle) => is_string($handle) && $handle !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{products: int, skus: int, banners: int, groups: int}
     */
    public static function purgeFromDatabase(): array
    {
        return DB::transaction(function () {
            CatalogOrigin::purgeShop2Catalog();

            $seedHandles = self::handles();
            $productIds = Product::query()
                ->where(function (Builder $inner) use ($seedHandles) {
                    $inner->whereIn('handle', $seedHandles)
                        ->orWhereNull('fazercards_category_id')
                        ->orWhere('fazercards_category_id', '');
                })
                ->pluck('id');

            $skuIds = Sku::query()
                ->where(function (Builder $inner) use ($productIds) {
                    $inner->whereIn('product_id', $productIds)
                        ->orWhereNull('fazercards_offer_id')
                        ->orWhere('fazercards_offer_id', '');
                })
                ->pluck('id');

            FlashSaleItem::query()->whereIn('sku_id', $skuIds)->delete();
            $skus = (int) Sku::query()->whereIn('id', $skuIds)->delete();
            $products = (int) Product::query()->whereIn('id', $productIds)->delete();

            $orphanSkus = (int) Sku::query()
                ->where(function (Builder $inner) {
                    $inner->whereNull('fazercards_offer_id')->orWhere('fazercards_offer_id', '');
                })
                ->delete();
            $skus += $orphanSkus;

            $catalog = require database_path('data/catalog.php');
            $banners = 0;
            foreach ($catalog['banners'] ?? [] as $row) {
                $banners += (int) Banner::query()
                    ->where('title', $row['title'] ?? '')
                    ->where('href', $row['href'] ?? '')
                    ->delete();
            }

            FlashSaleItem::query()->delete();

            $keepSlugs = [
                'game-topup',
                'gift-card',
                'game-keys',
                'steam-wallet',
                'steam-gifts',
                'telegram',
                'manual-services',
            ];
            $groupIds = CatalogGroup::query()
                ->whereNotIn('slug', $keepSlugs)
                ->whereDoesntHave('products', function (Builder $product) {
                    $product->whereNotNull('fazercards_category_id')->where('fazercards_category_id', '!=', '');
                })
                ->pluck('id');
            Product::query()->whereIn('catalog_group_id', $groupIds)->update(['catalog_group_id' => null]);
            $groups = (int) CatalogGroup::query()->whereIn('id', $groupIds)->delete();

            return [
                'products' => $products,
                'skus' => $skus,
                'banners' => $banners,
                'groups' => $groups,
            ];
        });
    }
}

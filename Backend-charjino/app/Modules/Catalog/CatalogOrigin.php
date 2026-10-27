<?php

namespace App\Modules\Catalog;

use App\Models\CatalogGroup;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CatalogOrigin
{
    public const MANUAL = 'manual';

    public const SUPPLIER = 'supplier';

    public const SHOP2 = 'shop2';

    public const FAZERCARDS = 'fazercards';

    public static function product(Product $product): string
    {
        if (self::filledId($product->fazercards_category_id ?? null)) {
            return self::FAZERCARDS;
        }
        if ($product->shop2_category_id) {
            return self::SHOP2;
        }

        return self::MANUAL;
    }

    public static function group(CatalogGroup $group): string
    {
        if ($group->shop2_big_category_id) {
            return self::SHOP2;
        }
        if (self::filledId($group->getAttribute('fazercards_category_id'))) {
            return self::FAZERCARDS;
        }
        if ($group->getAttribute('has_fazer_products')) {
            return self::FAZERCARDS;
        }
        if ($group->getAttribute('has_shop2_products')) {
            return self::SHOP2;
        }

        return self::MANUAL;
    }

    /**
     * @param  Builder<Product>  $query
     */
    public static function applyProductFilter(Builder $query, string $source): void
    {
        $source = trim($source);
        if ($source === self::MANUAL) {
            $query->where(fn (Builder $inner) => self::constrainManualProduct($inner));

            return;
        }
        if ($source === self::SUPPLIER) {
            $query->where(fn (Builder $inner) => self::constrainSupplierProduct($inner));

            return;
        }
        if ($source === self::SHOP2) {
            $query->whereNotNull('shop2_category_id');

            return;
        }
        if ($source === self::FAZERCARDS) {
            $query->where(fn (Builder $inner) => self::constrainFazerProduct($inner));
        }
    }

    /**
     * @param  Builder<CatalogGroup>  $query
     */
    public static function applyGroupFilter(Builder $query, string $source): void
    {
        $source = trim($source);
        if ($source === self::MANUAL) {
            $query->where(fn (Builder $inner) => self::constrainManualGroup($inner));

            return;
        }
        if ($source === self::SUPPLIER) {
            $query->where(fn (Builder $inner) => self::constrainSupplierGroup($inner));
        }
    }

    /**
     * @param  Builder<CatalogGroup>  $query
     * @return Builder<CatalogGroup>
     */
    public static function withGroupOriginFlags(Builder $query): Builder
    {
        return $query
            ->withExists(['products as has_shop2_products' => fn (Builder $q) => $q->whereNotNull('shop2_category_id')])
            ->withExists(['products as has_fazer_products' => fn (Builder $q) => self::constrainFazerProduct($q)]);
    }

    /**
     * @return array{deleted: int, ids: list<int>}
     */
    public static function purgeUnlinkedGroups(): array
    {
        /** @var Collection<int, int> $ids */
        $ids = CatalogGroup::query()
            ->where(fn (Builder $inner) => self::constrainManualGroup($inner))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return ['deleted' => 0, 'ids' => []];
        }

        Product::query()->whereIn('catalog_group_id', $ids)->update(['catalog_group_id' => null]);
        $deleted = CatalogGroup::query()->whereIn('id', $ids)->delete();

        return [
            'deleted' => (int) $deleted,
            'ids' => $ids->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * @return array{groups: int, products: int, skus: int}
     */
    public static function purgeShop2Catalog(): array
    {
        return DB::transaction(function () {
            $shop2OnlyProducts = Product::query()
                ->whereNotNull('shop2_category_id')
                ->where(function (Builder $inner) {
                    $inner->whereNull('fazercards_category_id')->orWhere('fazercards_category_id', '');
                });
            $productIds = $shop2OnlyProducts->pluck('id');

            $skuIds = Sku::query()
                ->where(function (Builder $inner) use ($productIds) {
                    $inner->whereIn('product_id', $productIds)
                        ->orWhere(function (Builder $shop2Sku) {
                            $shop2Sku->whereNotNull('shop2_item_id')
                                ->where(function (Builder $fazer) {
                                    $fazer->whereNull('fazercards_offer_id')->orWhere('fazercards_offer_id', '');
                                });
                        });
                })
                ->pluck('id');

            FlashSaleItem::query()->whereIn('sku_id', $skuIds)->delete();
            $skus = (int) Sku::query()->whereIn('id', $skuIds)->delete();
            $products = (int) Product::query()->whereIn('id', $productIds)->delete();

            Product::query()
                ->where(function (Builder $inner) {
                    $inner->whereNotNull('shop2_category_id')->orWhereNotNull('shop2_big_category_id');
                })
                ->update([
                    'shop2_category_id' => null,
                    'shop2_big_category_id' => null,
                ]);
            Sku::query()->whereNotNull('shop2_item_id')->update(['shop2_item_id' => null]);

            $groupIds = CatalogGroup::query()
                ->whereNotNull('shop2_big_category_id')
                ->whereDoesntHave('products', function (Builder $product) {
                    self::constrainFazerProduct($product);
                })
                ->pluck('id');
            Product::query()->whereIn('catalog_group_id', $groupIds)->update(['catalog_group_id' => null]);
            $groups = (int) CatalogGroup::query()->whereIn('id', $groupIds)->delete();
            CatalogGroup::query()->whereNotNull('shop2_big_category_id')->update(['shop2_big_category_id' => null]);

            $leftoverIds = CatalogGroup::query()
                ->whereNotIn('slug', [
                    'game-topup',
                    'gift-card',
                    'game-keys',
                    'steam-wallet',
                    'steam-gifts',
                    'telegram',
                    'manual-services',
                ])
                ->whereDoesntHave('products', function (Builder $product) {
                    self::constrainFazerProduct($product);
                })
                ->get()
                ->filter(fn (CatalogGroup $group) => preg_match('/-\d+$/', (string) $group->slug) === 1)
                ->pluck('id');
            if ($leftoverIds->isNotEmpty()) {
                $leftoverProductIds = Product::query()->whereIn('catalog_group_id', $leftoverIds)
                    ->where(function (Builder $inner) {
                        $inner->whereNull('fazercards_category_id')->orWhere('fazercards_category_id', '');
                    })
                    ->pluck('id');
                FlashSaleItem::query()->whereIn(
                    'sku_id',
                    Sku::query()->whereIn('product_id', $leftoverProductIds)->pluck('id')
                )->delete();
                Sku::query()->whereIn('product_id', $leftoverProductIds)->delete();
                $products += (int) Product::query()->whereIn('id', $leftoverProductIds)->delete();
                $groups += (int) CatalogGroup::query()->whereIn('id', $leftoverIds)->delete();
            }

            return [
                'groups' => $groups,
                'products' => $products,
                'skus' => $skus,
            ];
        });
    }

    public static function isManualGroup(CatalogGroup $group): bool
    {
        return self::group($group) === self::MANUAL;
    }

    /**
     * @param  Builder<Product>  $query
     */
    private static function constrainFazerProduct(Builder $query): void
    {
        $query->whereNotNull('fazercards_category_id')->where('fazercards_category_id', '!=', '');
    }

    /**
     * @param  Builder<Product>  $query
     */
    private static function constrainManualProduct(Builder $query): void
    {
        $query->whereNull('shop2_category_id')
            ->where(function (Builder $inner) {
                $inner->whereNull('fazercards_category_id')->orWhere('fazercards_category_id', '');
            });
    }

    /**
     * @param  Builder<Product>  $query
     */
    private static function constrainSupplierProduct(Builder $query): void
    {
        $query->where(function (Builder $inner) {
            $inner->whereNotNull('shop2_category_id')
                ->orWhere(function (Builder $fazer) {
                    $fazer->whereNotNull('fazercards_category_id')->where('fazercards_category_id', '!=', '');
                });
        });
    }

    /**
     * @param  Builder<CatalogGroup>  $query
     */
    private static function constrainSupplierGroup(Builder $query): void
    {
        $query->where(function (Builder $inner) {
            $inner->whereNotNull('shop2_big_category_id')
                ->orWhereHas('products', function (Builder $product) {
                    self::constrainSupplierProduct($product);
                });
        });
    }

    /**
     * @param  Builder<CatalogGroup>  $query
     */
    private static function constrainManualGroup(Builder $query): void
    {
        $query->whereNull('shop2_big_category_id')
            ->whereDoesntHave('products', function (Builder $product) {
                self::constrainSupplierProduct($product);
            });
    }

    private static function filledId(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }
}

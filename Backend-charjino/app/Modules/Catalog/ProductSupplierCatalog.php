<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use App\Models\ProductSupplierIdentity;

final class ProductSupplierCatalog
{
    public const SHOP2 = ProductSupplierIdentity::SHOP2;

    public const FAZERCARDS = ProductSupplierIdentity::FAZERCARDS;

    public function remember(Product $product, string $supplier, string $remoteId): void
    {
        $remoteId = trim($remoteId);
        $supplier = trim($supplier);
        if ($remoteId === '' || $supplier === '' || ! $product->id) {
            return;
        }
        ProductSupplierIdentity::query()->updateOrCreate(
            [
                'supplier' => $supplier,
                'remote_id' => $remoteId,
            ],
            ['product_id' => $product->id],
        );
    }

    public function findByRemote(string $supplier, string $remoteId): ?Product
    {
        $remoteId = trim($remoteId);
        if ($remoteId === '') {
            return null;
        }
        $row = ProductSupplierIdentity::query()
            ->where('supplier', $supplier)
            ->where('remote_id', $remoteId)
            ->first();
        if (! $row) {
            return null;
        }

        return Product::query()->find($row->product_id);
    }

    public function findLiveOrAnyByKey(string $handle, ?string $family, ?string $region, bool $stripTrailingId = false): ?Product
    {
        $parts = ProductNormalizedKey::fromHandle($handle, $family, $region, $stripTrailingId);
        if ($parts['key'] === '') {
            return null;
        }
        $query = Product::query()
            ->where('normalized_kind', $parts['kind'])
            ->where('normalized_region', $parts['region'])
            ->where('normalized_key', $parts['key']);
        $live = (clone $query)
            ->where('status', 'active')
            ->where('visible', true)
            ->whereNull('substitute_product_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        return $live ?? $query->orderBy('created_at')->orderBy('id')->first();
    }

    public function resolveCanonical(Product $product): Product
    {
        $seen = [];
        $current = $product;
        while ($current->substitute_product_id && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $next = Product::query()->find($current->substitute_product_id);
            if (! $next) {
                break;
            }
            $current = $next;
        }

        return $current;
    }

    /**
     * @return list<string>
     */
    public function remoteIds(Product $product, string $supplier): array
    {
        $ids = [];
        if ($supplier === self::FAZERCARDS && filled($product->fazercards_category_id)) {
            $ids[] = (string) $product->fazercards_category_id;
        }
        if ($supplier === self::SHOP2 && $product->shop2_category_id) {
            $ids[] = (string) $product->shop2_category_id;
        }
        foreach ($product->supplierIdentities as $row) {
            if ($row->supplier === $supplier && trim((string) $row->remote_id) !== '') {
                $ids[] = (string) $row->remote_id;
            }
        }

        return array_values(array_unique($ids));
    }
}

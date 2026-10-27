<?php

namespace App\Modules\Catalog;

use App\Models\FlashSaleItem;
use App\Models\Sku;

final class FlashSaleSync
{
    public static function sync(Sku $sku): void
    {
        $sku->loadMissing('product');
        $existing = FlashSaleItem::query()->where('sku_id', $sku->id)->first();

        if (! self::storefrontWorthy($sku)) {
            $existing?->delete();

            return;
        }

        $product = $sku->product;
        $attrs = [
            'image' => ($existing && $existing->image !== '') ? $existing->image : ($product?->displayImage() ?? ''),
            'original_price' => $sku->listPrice(),
            'price' => $sku->sellPrice(),
            'product_code' => $product?->code ?? '',
            'product_handle' => $product?->handle ?? '',
            'product_name' => $product?->title ?? '',
            'product_variant_code' => $sku->code,
            'product_variant_name' => $sku->name,
        ];

        if ($existing) {
            FlashSaleItem::query()->where('sku_id', $sku->id)->where('id', '!=', $existing->id)->delete();
            $existing->fill($attrs)->save();

            return;
        }

        FlashSaleItem::query()->create($attrs + [
            'sku_id' => $sku->id,
            'visible' => true,
            'sort_order' => (int) (FlashSaleItem::query()->max('sort_order') ?? 0) + 1,
        ]);
    }

    public static function forget(Sku $sku): void
    {
        FlashSaleItem::query()->where('sku_id', $sku->id)->delete();
    }

    public static function storefrontWorthy(Sku $sku): bool
    {
        if (! $sku->hasActiveSale()) {
            return false;
        }
        if (! $sku->visible || $sku->status !== 'active') {
            return false;
        }
        $product = $sku->product;
        if (! $product || ! $product->visible || $product->status !== 'active') {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function toApiFromSku(Sku $sku): array
    {
        $product = $sku->product;

        return [
            'id' => 1_000_000_000 + (int) $sku->id,
            'image' => $product?->displayImage() ?: '',
            'originalPrice' => $sku->listPrice(),
            'price' => $sku->sellPrice(),
            'productCode' => $product?->code ?? '',
            'productHandle' => $product?->handle ?? '',
            'productName' => $product?->title ?? '',
            'productVariantCode' => $sku->code,
            'productVariantName' => $sku->name,
        ];
    }
}

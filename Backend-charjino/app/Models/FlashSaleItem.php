<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontHomeCache;
use App\Modules\Catalog\CatalogMedia;
use App\Modules\Catalog\FlashSaleSync;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleItem extends Model
{
    use FlushesStorefrontHomeCache;

    protected $fillable = [
        'sku_id',
        'image',
        'original_price',
        'price',
        'product_code',
        'product_handle',
        'product_name',
        'product_variant_code',
        'product_variant_name',
        'sort_order',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'integer',
            'price' => 'integer',
            'sort_order' => 'integer',
            'visible' => 'boolean',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function storefrontListPrice(): int
    {
        $sku = $this->relationLoaded('sku') ? $this->sku : null;

        return Money::roundToman($sku ? $sku->listPrice() : (int) $this->original_price);
    }

    public function storefrontSalePrice(): int
    {
        $sku = $this->relationLoaded('sku') ? $this->sku : null;

        return Money::roundToman($sku ? $sku->sellPrice() : (int) $this->price);
    }

    public function isStorefrontWorthy(): bool
    {
        if (! $this->getAttribute('visible')) {
            return false;
        }

        $sku = $this->sku;
        if ($sku && ! FlashSaleSync::storefrontWorthy($sku)) {
            return false;
        }

        return Money::isCredibleSale($this->storefrontSalePrice(), $this->storefrontListPrice());
    }

    public function toApi(): array
    {
        $image = CatalogMedia::catalogSrc($this->image);
        if ($image === '') {
            $product = $this->relationLoaded('sku') ? $this->sku?->product : null;
            $image = $product?->displayImage() ?: CatalogMedia::publicSrc('');
        }

        return [
            'id' => $this->id,
            'image' => $image,
            'originalPrice' => $this->storefrontListPrice(),
            'price' => $this->storefrontSalePrice(),
            'productCode' => $this->product_code,
            'productHandle' => $this->product_handle,
            'productName' => $this->product_name,
            'productVariantCode' => $this->product_variant_code,
            'productVariantName' => $this->product_variant_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        return $this->toApi() + [
            'skuId' => $this->sku_id,
            'visible' => (bool) $this->getAttribute('visible'),
            'sortOrder' => (int) $this->sort_order,
        ];
    }
}

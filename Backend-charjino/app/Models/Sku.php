<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontHomeCache;
use App\Modules\Catalog\CatalogAttr;
use App\Modules\Catalog\FlashSaleSync;
use App\Modules\Pricing\PricingService;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sku extends Model
{
    use FlushesStorefrontHomeCache;

    protected $fillable = [
        'product_id',
        'gift_sku_id',
        'shop2_item_id',
        'fazercards_category_id',
        'fazercards_offer_id',
        'code',
        'name',
        'name_en',
        'fulfillment_type',
        'price',
        'price_manual',
        'margin_percent',
        'sale_price',
        'sale_ends_at',
        'cost_usd',
        'live_cost_usd',
        'region',
        'min_order_quantity',
        'max_order_quantity',
        'stock',
        'status',
        'visible',
        'sort_order',
        'synced_at',
        'sync_failed_count',
        'last_sync_failed_at',
    ];

    protected function casts(): array
    {
        return [
            'shop2_item_id' => 'integer',
            'gift_sku_id' => 'integer',
            'price' => 'integer',
            'price_manual' => 'boolean',
            'margin_percent' => 'decimal:4',
            'sale_price' => 'integer',
            'sale_ends_at' => 'datetime',
            'visible' => 'boolean',
            'sort_order' => 'integer',
            'min_order_quantity' => 'integer',
            'max_order_quantity' => 'integer',
            'stock' => 'integer',
            'synced_at' => 'datetime',
            'sync_failed_count' => 'integer',
            'last_sync_failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static function (Sku $sku): void {
            FlashSaleSync::sync($sku);
        });
        static::deleted(static function (Sku $sku): void {
            FlashSaleSync::forget($sku);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * SKUهایی که صفحه‌ی محصول برای دکمهٔ خرید و نمایش قیمت لیست می‌کند.
     * CatalogService::loadStorefrontProduct
     *
     * @param  Builder<Sku>  $query
     */
    public function scopeForProductPage(Builder $query): void
    {
        $query->where('status', 'active')->orderBy('sort_order')->orderBy('id');
    }

    public function isListedOnProductPage(): bool
    {
        return $this->status === 'active';
    }

    public function giftSku(): BelongsTo
    {
        return $this->belongsTo(self::class, 'gift_sku_id');
    }

    /**
     * Package the supplier actually fulfills. Gift-equivalent listings point at a gift card.
     */
    public function supplierSku(): ?self
    {
        if (! $this->gift_sku_id) {
            return $this;
        }

        $gift = $this->relationLoaded('giftSku') ? $this->giftSku : $this->giftSku()->first();
        if (! $gift instanceof self || ! $gift->isFazerCards()) {
            return null;
        }
        $family = FazerCardsIds::familyOf($gift->fulfillment_type, $gift->fazercards_category_id);
        if ($family !== FazerCardsIds::GIFTCARD) {
            return null;
        }

        return $gift;
    }

    public function isFazerCards(): bool
    {
        return filled($this->fazercards_category_id) && filled($this->fazercards_offer_id);
    }

    public function usdCost(): string
    {
        $live = trim((string) $this->live_cost_usd);
        if ($live !== '') {
            return $live;
        }

        return trim((string) $this->cost_usd) !== '' ? (string) $this->cost_usd : '0';
    }

    public function listPrice(): int
    {
        return Money::roundToman((int) $this->price);
    }

    public function hasActiveSale(): bool
    {
        $sale = (int) ($this->sale_price ?? 0);
        $list = $this->listPrice();
        if (! Money::isCredibleSale($sale, $list)) {
            return false;
        }
        if ($this->sale_ends_at === null) {
            return false;
        }

        return $this->sale_ends_at->greaterThanOrEqualTo(now());
    }

    public function sellPrice(): int
    {
        return $this->hasActiveSale() ? Money::roundToman((int) $this->sale_price) : $this->listPrice();
    }

    public function minOrderQuantity(): int
    {
        return CatalogAttr::minOrderQty((int) ($this->min_order_quantity ?: 0));
    }

    public function maxOrderQuantity(): int
    {
        return CatalogAttr::maxOrderQty((int) ($this->max_order_quantity ?: 0), $this->minOrderQuantity());
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        $onSale = $this->hasActiveSale();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'nameEn' => $this->name_en,
            'price' => $this->sellPrice(),
            'originalPrice' => $onSale ? $this->listPrice() : null,
            'saleEndsAt' => $onSale ? $this->sale_ends_at?->toIso8601String() : null,
            'fulfillmentType' => $this->fulfillment_type,
            'region' => CatalogAttr::publicRegion($this->region),
            'minOrderQuantity' => $this->minOrderQuantity(),
            'maxOrderQuantity' => $this->maxOrderQuantity(),
            'stock' => $this->stock,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'giftSkuId' => $this->gift_sku_id,
            'shop2ItemId' => $this->shop2_item_id,
            'fazercardsCategoryId' => $this->fazercards_category_id,
            'fazercardsOfferId' => $this->fazercards_offer_id,
            'code' => $this->code,
            'name' => $this->name,
            'nameEn' => $this->name_en,
            'fulfillmentType' => $this->fulfillment_type,
            'price' => $this->listPrice(),
            'priceManual' => (bool) $this->price_manual,
            'marginPercent' => PricingService::formatMargin($this->margin_percent),
            'effectiveMarginPercent' => app(PricingService::class)->marginFor($this),
            'marginSource' => app(PricingService::class)->marginSource($this),
            'salePrice' => $this->sale_price,
            'saleEndsAt' => $this->sale_ends_at?->toIso8601String(),
            'saleActive' => $this->hasActiveSale(),
            'sellPrice' => $this->sellPrice(),
            'costUsd' => $this->cost_usd,
            'liveCostUsd' => $this->live_cost_usd,
            'purchaseUsd' => $this->usdCost(),
            'purchaseToman' => app(PricingService::class)->costToman($this->usdCost()),
            'status' => $this->status,
            'visible' => (bool) $this->getAttribute('visible'),
            'sortOrder' => $this->sort_order,
            'region' => CatalogAttr::publicRegion($this->region),
            'minOrderQuantity' => $this->minOrderQuantity(),
            'maxOrderQuantity' => $this->maxOrderQuantity(),
            'stock' => $this->stock,
            'syncedAt' => $this->synced_at?->toIso8601String(),
        ];
    }
}

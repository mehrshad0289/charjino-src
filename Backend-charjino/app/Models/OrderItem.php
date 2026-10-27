<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'sku_id',
        'product_id',
        'fulfillment_type',
        'supplier_ref',
        'requirements',
        'quantity',
        'steam_currency',
        'steam_amount',
        'unit_price_toman',
        'total_toman',
        'unit_cost_usd',
        'total_cost_usd',
        'fx_rate',
        'margin_percent',
        'price_source',
        'priced_at',
        'stock_at_order',
    ];

    protected function casts(): array
    {
        return [
            'supplier_ref' => 'array',
            'requirements' => 'array',
            'quantity' => 'integer',
            'unit_price_toman' => 'integer',
            'total_toman' => 'integer',
            'priced_at' => 'datetime',
            'stock_at_order' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(FulfillmentAttempt::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id,
            'skuId' => $this->sku_id,
            'productId' => $this->product_id,
            'fulfillmentType' => $this->fulfillment_type,
            'supplierRef' => $this->supplier_ref,
            'quantity' => $this->quantity,
            'steamCurrency' => $this->steam_currency,
            'steamAmount' => $this->steam_amount,
            'unitPriceToman' => $this->unit_price_toman,
            'totalToman' => $this->total_toman,
            'unitCostUsd' => (string) $this->unit_cost_usd,
            'totalCostUsd' => (string) $this->total_cost_usd,
            'fxRate' => $this->fx_rate,
            'marginPercent' => $this->margin_percent,
            'priceSource' => $this->price_source,
            'pricedAt' => $this->priced_at?->toIso8601String(),
            'stockAtOrder' => $this->stock_at_order,
        ];
    }
}

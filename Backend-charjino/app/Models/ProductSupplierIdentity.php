<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplierIdentity extends Model
{
    public const SHOP2 = 'shop2';

    public const FAZERCARDS = 'fazercards';

    protected $fillable = [
        'product_id',
        'supplier',
        'remote_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

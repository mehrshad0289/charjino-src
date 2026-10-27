<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogChangeAudit extends Model
{
    protected $fillable = [
        'product_id',
        'actor_type',
        'actor_id',
        'source',
        'field',
        'before',
        'after',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

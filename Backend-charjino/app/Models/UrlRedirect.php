<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrlRedirect extends Model
{
    protected $fillable = [
        'old_path',
        'target_path',
        'status_code',
        'reason',
        'source',
        'active',
        'product_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'status_code' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

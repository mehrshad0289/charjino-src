<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyGuideSection extends Model
{
    protected $fillable = [
        'buy_guide_id',
        'type',
        'inherit',
        'sort_order',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'inherit' => 'boolean',
            'sort_order' => 'integer',
            'data' => 'array',
        ];
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(BuyGuide::class, 'buy_guide_id');
    }
}

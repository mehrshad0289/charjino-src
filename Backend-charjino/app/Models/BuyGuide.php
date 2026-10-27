<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyGuide extends Model
{
    protected $fillable = [
        'scope_key',
        'scope_type',
        'category',
        'subcategory',
        'product_id',
        'title',
        'summary',
        'intro',
        'status',
        'last_reviewed_at',
        'seo_title',
        'seo_description',
        'published_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'last_reviewed_at' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(BuyGuideSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}

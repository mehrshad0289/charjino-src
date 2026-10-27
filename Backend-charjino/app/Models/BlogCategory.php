<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'body_html',
        'meta_title',
        'meta_description',
        'noindex',
        'image',
        'image_alt',
        'parent_id',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'noindex' => 'boolean',
            'parent_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }
}

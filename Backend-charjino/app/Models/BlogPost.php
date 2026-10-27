<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogPost extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SCHEDULED = 'scheduled';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'meta_title',
        'meta_description',
        'canonical_url',
        'noindex',
        'focus_keyword',
        'og_title',
        'og_description',
        'og_image',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'secondary_keywords',
        'content',
        'faq',
        'preview_token',
        'cover',
        'cover_alt',
        'cover_width',
        'cover_height',
        'category_id',
        'user_id',
        'status',
        'published_at',
        'views_count',
        'likes_count',
        'comments_count',
        'reading_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'published_at' => 'datetime',
            'noindex' => 'boolean',
            'faq' => 'array',
            'secondary_keywords' => 'array',
            'cover_width' => 'integer',
            'cover_height' => 'integer',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'reading_minutes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class, 'post_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'post_id', 'tag_id')->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'blog_post_product', 'post_id', 'product_id')->withTimestamps();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BlogPostRevision::class, 'post_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function isPublished(): bool
    {
        return $this->isLive();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}

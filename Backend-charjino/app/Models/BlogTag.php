<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogTag extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'body_html',
        'image',
        'image_alt',
        'meta_title',
        'meta_description',
        'force_noindex',
        'noindex',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'force_noindex' => 'boolean',
            'noindex' => 'boolean',
        ];
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag', 'tag_id', 'post_id');
    }

    public function isIndexable(int $publishedCount): bool
    {
        return ! $this->force_noindex && $publishedCount >= 3;
    }
}

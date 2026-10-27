<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPostRevision extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'payload' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }
}

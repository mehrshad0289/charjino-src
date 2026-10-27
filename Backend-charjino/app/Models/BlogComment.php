<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogComment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_HIDDEN,
    ];

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'body',
        'status',
        'likes_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'parent_id' => 'integer',
            'likes_count' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}

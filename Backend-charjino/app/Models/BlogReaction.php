<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogReaction extends Model
{
    public const TYPE_POST = 'post';

    public const TYPE_COMMENT = 'comment';

    public const TYPE_PRODUCT_COMMENT = 'product_comment';

    public const VOTE_LIKE = 'like';

    protected $fillable = [
        'user_id',
        'reactable_type',
        'reactable_id',
        'vote',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reactable_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

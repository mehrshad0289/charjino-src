<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_REDIRECTED = 'redirected';

    public const STATUS_VERIFY_PENDING = 'verify_pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVIEW = 'review';

    protected $fillable = [
        'order_id',
        'provider',
        'amount_toman',
        'status',
        'authority',
        'ref',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'amount_toman' => 'integer',
            'raw' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'amountToman' => $this->amount_toman,
            'status' => $this->status,
            'authority' => $this->authority,
            'ref' => $this->ref,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public const METHOD_WALLET = 'wallet';

    public const METHOD_MANUAL = 'manual_gateway';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'order_id',
        'amount_toman',
        'method',
        'status',
        'reason_code',
        'requested_by',
        'decided_by_admin_id',
        'decided_at',
        'wallet_transaction_id',
        'external_ref',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount_toman' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toCustomer(): array
    {
        return [
            'method' => $this->method,
            'status' => $this->status,
        ];
    }
}

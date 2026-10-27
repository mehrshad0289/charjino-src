<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    public $timestamps = false;

    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    protected $fillable = [
        'user_id',
        'type',
        'amount_toman',
        'balance_after',
        'reason',
        'order_id',
        'refund_id',
        'idempotency_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_toman' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}

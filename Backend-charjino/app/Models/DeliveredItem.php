<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveredItem extends Model
{
    public $timestamps = false;

    protected $hidden = ['secret'];

    protected $fillable = [
        'order_id',
        'order_item_id',
        'attempt_id',
        'kind',
        'position',
        'secret',
        'fingerprint',
        'first_revealed_at',
        'reveal_count',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'position' => 'integer',
            'reveal_count' => 'integer',
            'first_revealed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

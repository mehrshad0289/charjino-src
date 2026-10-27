<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierWebhookEvent extends Model
{
    protected $fillable = [
        'event',
        'supplier_order_id',
        'signature_valid',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}

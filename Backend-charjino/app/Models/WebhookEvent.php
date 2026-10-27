<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'event',
        'fazer_order_id',
        'data_type',
        'data_status',
        'previous_status',
        'delivery_attempt',
        'signature_valid',
        'payload',
        'attempt_id',
        'status',
        'process_error',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'delivery_attempt' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(FulfillmentAttempt::class, 'attempt_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id,
            'eventId' => $this->event_id,
            'event' => $this->event,
            'fazerOrderId' => $this->fazer_order_id,
            'dataStatus' => $this->data_status,
            'status' => $this->status,
            'receivedAt' => $this->received_at?->toIso8601String(),
            'processedAt' => $this->processed_at?->toIso8601String(),
        ];
    }
}

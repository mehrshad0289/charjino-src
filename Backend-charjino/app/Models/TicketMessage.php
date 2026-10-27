<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    public const SENDER_USER = 'user';

    public const SENDER_STAFF = 'staff';

    protected $fillable = [
        'ticket_id',
        'sender',
        'body',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toCustomer(): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'body' => $this->body,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

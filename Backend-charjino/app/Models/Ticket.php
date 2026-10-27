<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUS_OPEN = 'open';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_CLOSED = 'closed';

    public const CATEGORIES = ['order', 'payment', 'delivery', 'account', 'other'];

    protected $fillable = [
        'id',
        'number',
        'user_id',
        'subject',
        'category',
        'order_number',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            if (! $ticket->id) {
                $ticket->id = (string) Str::uuid();
            }
            if (! $ticket->number) {
                $ticket->number = self::nextNumber();
            }
        });
    }

    public static function nextNumber(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $candidate = 'TK'.now()->format('ymd').strtoupper(Str::random(5));
            if (! self::query()->where('number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'TK'.now()->format('ymdHis').strtoupper(Str::lower(Str::random(3)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toCustomer(bool $withMessages = false): array
    {
        $payload = [
            'id' => $this->id,
            'number' => $this->number,
            'subject' => $this->subject,
            'category' => $this->category,
            'orderNumber' => $this->order_number,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
        if ($withMessages) {
            $this->loadMissing('messages');
            $payload['messages'] = $this->messages->map(fn (TicketMessage $message) => $message->toCustomer())->values()->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(bool $withMessages = false): array
    {
        $this->loadMissing('user');

        return [
            ...$this->toCustomer($withMessages),
            'userId' => $this->user_id,
            'userPhoneNumber' => $this->user?->phone,
            'userName' => $this->user?->name,
        ];
    }
}

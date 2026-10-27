<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAlert extends Model
{
    protected $fillable = [
        'type',
        'dedupe_key',
        'payload',
        'opened_at',
        'resolved_at',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpChallenge extends Model
{
    protected $fillable = [
        'phone',
        'code_hash',
        'attempts',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}

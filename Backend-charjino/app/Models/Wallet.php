<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'balance_toman',
    ];

    protected function casts(): array
    {
        return [
            'balance_toman' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'user_id', 'user_id');
    }
}

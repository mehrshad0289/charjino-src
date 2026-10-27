<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    public const TYPE_AMOUNT = 'amount';

    public const TYPE_PERCENTAGE = 'percentage';

    protected $fillable = [
        'name',
        'code',
        'type',
        'amount',
        'percentage',
        'max_percentage_amount',
        'min_order_toman',
        'usage_limit_per_user',
        'max_uses',
        'is_active',
        'expired_at',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'percentage' => 'integer',
            'max_percentage_amount' => 'integer',
            'min_order_toman' => 'integer',
            'usage_limit_per_user' => 'integer',
            'max_uses' => 'integer',
            'is_active' => 'boolean',
            'expired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon): void {
            $coupon->code = strtoupper(trim((string) $coupon->code));
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isPercentage(): bool
    {
        return $this->type === self::TYPE_PERCENTAGE;
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    /**
     * @param  list<string>  $exclude
     */
    public function usedQuery(array $exclude = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = Order::query()
            ->where('coupon_id', $this->id)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_EXPIRED]);
        if ($exclude !== []) {
            $query->whereNotIn('id', $exclude);
        }

        return $query;
    }
}

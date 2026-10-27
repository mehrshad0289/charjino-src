<?php

namespace App\Modules\Coupons;

use App\Exceptions\ApiException;
use App\Models\Coupon;
use App\Models\Order;
use App\Support\AdminPage;
use App\Support\CustomerError;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CouponService
{
    /**
     * @return array{items: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int, totalPages: int}}
     */
    public function adminList(Request $request): array
    {
        $page = AdminPage::query($request, 20);
        $query = Coupon::query()->orderByDesc('id');
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('code', 'like', '%'.$q.'%')
                    ->orWhere('name', 'like', '%'.$q.'%');
            });
        }
        $status = trim((string) $request->query('status', ''));
        if ($status === 'active') {
            $query->where('is_active', true)->where(function ($inner) {
                $inner->whereNull('expired_at')->orWhere('expired_at', '>', now());
            });
        }
        if ($status === 'inactive') {
            $query->where('is_active', false);
        }
        if ($status === 'expired') {
            $query->whereNotNull('expired_at')->where('expired_at', '<=', now());
        }
        $paginator = $query->paginate($page['perPage'], ['*'], 'page', $page['page']);

        return [
            'items' => collect($paginator->items())->map(fn (Coupon $coupon) => $this->toAdmin($coupon))->values()->all(),
            'meta' => AdminPage::meta($paginator),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $data = $this->validated($payload);
        $coupon = Coupon::query()->create($data);

        return $this->toAdmin($coupon);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(Coupon $coupon, array $payload): array
    {
        $data = $this->validated($payload, $coupon->id);
        $coupon->fill($data);
        $coupon->save();

        return $this->toAdmin($coupon);
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Coupon $coupon): array
    {
        $payload = $this->toAdmin($coupon);
        $coupon->delete();

        return $payload;
    }

    /**
     * @return array{coupon: Coupon, subtotalToman: int, discountToman: int, payableToman: int}
     */
    public function quote(string $userId, string $code, int $subtotalToman, ?string $exceptOrderId = null): array
    {
        $coupon = $this->usableByCode($code);
        $this->assertQuota($coupon, $userId, $exceptOrderId);
        if ($coupon->min_order_toman > 0 && $subtotalToman < (int) $coupon->min_order_toman) {
            throw new ApiException(
                'برای این کد حداقل خرید '.number_format((int) $coupon->min_order_toman).' تومان لازم است.',
                422,
                'COUPON_MIN_ORDER',
            );
        }
        $discount = $this->discountToman($coupon, $subtotalToman);
        if ($discount < 1) {
            throw new ApiException(CustomerError::message('COUPON_INVALID'), 422, 'COUPON_INVALID');
        }

        return [
            'coupon' => $coupon,
            'subtotalToman' => $subtotalToman,
            'discountToman' => $discount,
            'payableToman' => $subtotalToman - $discount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicQuote(string $userId, string $code, int $subtotalToman): array
    {
        $quoted = $this->quote($userId, $code, $subtotalToman);

        return [
            'code' => $quoted['coupon']->code,
            'type' => $quoted['coupon']->type,
            'subtotalToman' => $quoted['subtotalToman'],
            'discountToman' => $quoted['discountToman'],
            'payableToman' => $quoted['payableToman'],
        ];
    }

    public function assertStillValid(Order $order): void
    {
        if (! $order->coupon_id) {
            return;
        }
        $coupon = Coupon::query()->find($order->coupon_id);
        if (! $coupon || ! $coupon->is_active || $coupon->isExpired()) {
            throw new ApiException(CustomerError::message('COUPON_EXPIRED'), 422, 'COUPON_EXPIRED');
        }
        $this->assertQuota($coupon, (string) $order->user_id, (string) $order->id);
        $subtotal = (int) ($order->subtotal_toman ?: $order->amount_toman);
        if ($coupon->min_order_toman > 0 && $subtotal < (int) $coupon->min_order_toman) {
            throw new ApiException(CustomerError::message('COUPON_MIN_ORDER'), 422, 'COUPON_MIN_ORDER');
        }
        $expected = $this->discountToman($coupon, $subtotal);
        if ($expected !== (int) $order->discount_toman || ($subtotal - $expected) !== (int) $order->amount_toman) {
            throw new ApiException(CustomerError::message('COUPON_INVALID'), 422, 'COUPON_INVALID');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'name' => $coupon->name,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'percentage' => $coupon->percentage,
            'maxPercentageAmount' => $coupon->max_percentage_amount,
            'minOrderToman' => (int) $coupon->min_order_toman,
            'usageLimitPerUser' => (int) $coupon->usage_limit_per_user,
            'maxUses' => $coupon->max_uses,
            'isActive' => (bool) $coupon->is_active,
            'expiredAt' => $coupon->expired_at?->toDateString(),
            'description' => $coupon->description,
            'usedCount' => $coupon->usedQuery()->count(),
            'createdAt' => $coupon->created_at?->toIso8601String(),
            'updatedAt' => $coupon->updated_at?->toIso8601String(),
        ];
    }

    public function usableByCode(string $code): Coupon
    {
        $normalized = strtoupper(trim($code));
        $coupon = Coupon::query()->where('code', $normalized)->first();
        if (! $coupon) {
            throw new ApiException(CustomerError::message('COUPON_NOT_FOUND'), 422, 'COUPON_NOT_FOUND');
        }
        if (! $coupon->is_active) {
            throw new ApiException(CustomerError::message('COUPON_INACTIVE'), 422, 'COUPON_INACTIVE');
        }
        if ($coupon->isExpired()) {
            throw new ApiException(CustomerError::message('COUPON_EXPIRED'), 422, 'COUPON_EXPIRED');
        }

        return $coupon;
    }

    public function discountToman(Coupon $coupon, int $subtotalToman): int
    {
        if ($subtotalToman < 2) {
            return 0;
        }
        if ($coupon->isPercentage()) {
            $raw = intdiv($subtotalToman * (int) $coupon->percentage, 100);
            $cap = (int) $coupon->max_percentage_amount;
            if ($cap > 0) {
                $raw = min($raw, $cap);
            }
        } else {
            $raw = (int) $coupon->amount;
        }

        return max(0, min($raw, $subtotalToman - 1));
    }

    private function assertQuota(Coupon $coupon, string $userId, ?string $exceptOrderId): void
    {
        $exclude = $exceptOrderId ? [$exceptOrderId] : [];
        if ($coupon->max_uses !== null && $coupon->usedQuery($exclude)->count() >= (int) $coupon->max_uses) {
            throw new ApiException(CustomerError::message('COUPON_USAGE_LIMIT'), 422, 'COUPON_USAGE_LIMIT');
        }
        $perUser = (int) $coupon->usage_limit_per_user;
        if ($perUser > 0) {
            $mine = $coupon->usedQuery($exclude)->where('user_id', $userId)->count();
            if ($mine >= $perUser) {
                throw new ApiException(CustomerError::message('COUPON_USAGE_LIMIT'), 422, 'COUPON_USAGE_LIMIT');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validated(array $payload, ?int $ignoreId = null): array
    {
        if (isset($payload['code']) && is_string($payload['code'])) {
            $payload['code'] = strtoupper(trim($payload['code']));
        }
        $unique = Rule::unique('coupons', 'code')->whereNull('deleted_at');
        if ($ignoreId) {
            $unique = $unique->ignore($ignoreId);
        }
        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'code' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', $unique],
            'type' => ['required', 'in:amount,percentage'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'percentage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'maxPercentageAmount' => ['nullable', 'integer', 'min:1'],
            'minOrderToman' => ['nullable', 'integer', 'min:0'],
            'usageLimitPerUser' => ['nullable', 'integer', 'min:1', 'max:100'],
            'maxUses' => ['nullable', 'integer', 'min:1'],
            'isActive' => ['nullable', 'boolean'],
            'expiredAt' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $validator->after(function ($validator) use ($payload) {
            $type = (string) ($payload['type'] ?? '');
            if ($type === Coupon::TYPE_AMOUNT) {
                if ((int) ($payload['amount'] ?? 0) < 1) {
                    $validator->errors()->add('amount', 'مبلغ تخفیف لازم است.');
                }
                $min = (int) ($payload['minOrderToman'] ?? 0);
                if ($min < 1) {
                    $validator->errors()->add('minOrderToman', 'حداقل خرید برای تخفیف مبلغی لازم است.');
                } elseif ($min < (int) ($payload['amount'] ?? 0)) {
                    $validator->errors()->add('minOrderToman', 'حداقل خرید نباید از مبلغ تخفیف کمتر باشد.');
                }
            }
            if ($type === Coupon::TYPE_PERCENTAGE) {
                if ((int) ($payload['percentage'] ?? 0) < 1) {
                    $validator->errors()->add('percentage', 'درصد تخفیف لازم است.');
                }
                if ((int) ($payload['maxPercentageAmount'] ?? 0) < 1) {
                    $validator->errors()->add('maxPercentageAmount', 'سقف تومان تخفیف درصدی لازم است.');
                }
            }
        });
        $data = $validator->validate();
        $expired = Carbon::parse((string) $data['expiredAt'], config('app.timezone'))->endOfDay();
        if ($expired->isPast()) {
            throw new ApiException('تاریخ انقضا باید در آینده باشد.', 422);
        }
        $type = (string) $data['type'];

        return [
            'name' => $data['name'],
            'code' => strtoupper((string) $data['code']),
            'type' => $type,
            'amount' => $type === Coupon::TYPE_AMOUNT ? (int) $data['amount'] : null,
            'percentage' => $type === Coupon::TYPE_PERCENTAGE ? (int) $data['percentage'] : null,
            'max_percentage_amount' => $type === Coupon::TYPE_PERCENTAGE ? (int) $data['maxPercentageAmount'] : null,
            'min_order_toman' => (int) ($data['minOrderToman'] ?? 0),
            'usage_limit_per_user' => (int) ($data['usageLimitPerUser'] ?? 1),
            'max_uses' => isset($data['maxUses']) ? (int) $data['maxUses'] : null,
            'is_active' => array_key_exists('isActive', $data) ? (bool) $data['isActive'] : true,
            'expired_at' => $expired,
            'description' => $data['description'] ?? null,
        ];
    }
}

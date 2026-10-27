<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Coupons\CouponService;
use App\Modules\Orders\OrderService;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutCouponController extends Controller
{
    public function preview(Request $request, OrderService $orders, CouponService $coupons): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'skuId' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'amount' => ['nullable', 'string', 'max:32'],
            'currency' => ['nullable', 'string', 'max:8'],
        ]);
        $subtotal = $orders->quoteSubtotalToman(
            (int) $data['skuId'],
            (int) ($data['quantity'] ?? 1),
            isset($data['amount']) ? (string) $data['amount'] : null,
            isset($data['currency']) ? (string) $data['currency'] : null,
        );

        return ApiResponse::ok($coupons->publicQuote(AuthUser::id($request), (string) $data['code'], $subtotal), 'کد تخفیف اعمال شد.');
    }
}

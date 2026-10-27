<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Modules\Coupons\CouponService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCouponController extends Controller
{
    public function index(Request $request, CouponService $coupons): JsonResponse
    {
        return ApiResponse::ok($coupons->adminList($request));
    }

    public function store(Request $request, CouponService $coupons): JsonResponse
    {
        return ApiResponse::ok($coupons->create($request->all()), 'کد تخفیف ذخیره شد.', 201);
    }

    public function show(Coupon $coupon, CouponService $coupons): JsonResponse
    {
        return ApiResponse::ok($coupons->toAdmin($coupon));
    }

    public function update(Request $request, Coupon $coupon, CouponService $coupons): JsonResponse
    {
        return ApiResponse::ok($coupons->update($coupon, $request->all()), 'کد تخفیف به‌روز شد.');
    }

    public function destroy(Coupon $coupon, CouponService $coupons): JsonResponse
    {
        return ApiResponse::ok($coupons->destroy($coupon), 'کد تخفیف حذف شد.');
    }
}

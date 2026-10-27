<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Supplier\Shop2\Shop2Client;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlatformController extends Controller
{
    public function policy(): JsonResponse
    {
        return ApiResponse::ok(config('charjino.policy'));
    }

    public function supplier(Shop2Client $shop2): JsonResponse
    {
        return ApiResponse::ok($shop2->status());
    }
}

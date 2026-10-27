<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Orders\OrderService;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function validatePlayer(Request $request): JsonResponse
    {
        AuthUser::id($request);
        $data = $request->validate([
            'skuId' => ['required', 'integer', 'min:1'],
            'requirements' => ['required', 'array'],
        ]);

        return ApiResponse::ok($this->orders->validatePlayer(
            (int) $data['skuId'],
            $data['requirements'],
        ));
    }
}

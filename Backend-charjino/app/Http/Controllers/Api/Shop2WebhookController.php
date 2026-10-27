<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Fulfillment\Shop2WebhookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Shop2WebhookController extends Controller
{
    public function __construct(private readonly Shop2WebhookService $webhooks) {}

    public function handle(Request $request): JsonResponse
    {
        $result = $this->webhooks->accept(
            $request->getContent(),
            (string) $request->header('X-Shop2Topup-Signature', ''),
            (string) $request->header('X-Shop2Topup-Event', ''),
        );

        return ApiResponse::ok($result);
    }
}

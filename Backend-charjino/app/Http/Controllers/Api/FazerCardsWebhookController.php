<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Fulfillment\FazerCardsWebhookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FazerCardsWebhookController extends Controller
{
    public function __construct(private readonly FazerCardsWebhookService $webhooks) {}

    public function handle(Request $request): JsonResponse
    {
        $result = $this->webhooks->accept(
            $request->getContent(),
            (string) $request->header('X-Webhook-Signature', ''),
            (string) $request->header('X-Webhook-Event', ''),
            $request->header('X-Webhook-Event-Id'),
            $request->header('X-Webhook-Delivery-Attempt'),
        );

        return ApiResponse::ok($result);
    }
}

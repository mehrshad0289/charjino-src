<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Supplier\FazerCards\FazerCardsCredentials;
use App\Modules\Supplier\Shop2\Shop2Client;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    public function __invoke(Shop2Client $shop2, FazerCardsCredentials $fazerCards): JsonResponse
    {
        $queuePending = null;
        $queueFailed = null;
        try {
            if (Schema::hasTable('jobs')) {
                $queuePending = (int) DB::table('jobs')->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $queueFailed = (int) DB::table('failed_jobs')->count();
            }
        } catch (\Throwable) {
        }

        return ApiResponse::ok([
            'app' => 'charjino-api',
            'status' => 'ok',
            'language' => 'fa',
            'stack' => 'laravel',
            'supplierConfigured' => $shop2->configured() || $fazerCards->configured(),
            'fazercardsConfigured' => $fazerCards->configured(),
            'fazercardsLiveOrderEnabled' => $fazerCards->liveOrderEnabled(),
            'liveOrderEnabled' => $shop2->liveOrderEnabled(),
            'paymentDriver' => (bool) config('charjino.orders.mock_payments')
                ? 'mock'
                : (string) config('payments.driver', config('charjino.orders.payment_driver', 'zibal')),
            'database' => config('database.default'),
            'queues' => [
                'pending' => $queuePending,
                'failed' => $queueFailed,
            ],
            'scheduler' => [
                'pollFulfillment' => Cache::get('scheduler:poll-fulfillment'),
                'reconcileFazer' => Cache::get('scheduler:reconcile-fazer'),
                'fazerBalanceWatch' => Cache::get('scheduler:fazer-balance-watch'),
                'fazerWebhookHealth' => Cache::get('scheduler:fazer-webhook-health'),
            ],
        ]);
    }
}

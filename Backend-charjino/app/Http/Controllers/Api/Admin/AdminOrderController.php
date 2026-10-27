<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshFazerOrder;
use App\Models\AdminAlert;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Modules\Fulfillment\FulfillmentService;
use App\Modules\Orders\OrderDeliveryService;
use App\Modules\Orders\OrderService;
use App\Modules\Orders\RefundService;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsClient;
use App\Modules\Supplier\Shop2\Shop2Adapter;
use App\Modules\Supplier\Shop2\Shop2Client;
use App\Support\AdminPage;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly FulfillmentService $fulfillment,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orders->listAdmin($request);

        return ApiResponse::ok([
            'items' => collect($paginator->items())->map(fn (Order $order) => $order->toAdmin())->all(),
            'meta' => AdminPage::meta($paginator) + $this->orders->adminCounters(),
        ]);
    }

    public function show(string $order, FazerCardsClient $client): JsonResponse
    {
        $model = $this->orders->findAdmin($order);
        $payload = $model->toAdmin();
        $attempt = $model->latestAttempt();
        if ($attempt?->status === FulfillmentAttempt::STATUS_UNKNOWN && $client->configured()) {
            try {
                $payload['fazerTransactions'] = $client->fetch('transactions')['data'] ?? null;
            } catch (\Throwable) {
                $payload['fazerTransactions'] = null;
            }
        }

        return ApiResponse::ok($payload);
    }

    public function retryFulfillment(Request $request, string $order): JsonResponse
    {
        $model = $this->orders->findAdmin($order);
        $updated = $this->fulfillment->retry($model);

        return ApiResponse::ok($updated->fresh()?->toAdmin() ?? $updated->toAdmin(), 'تحویل دوباره در صف قرار گرفت.');
    }

    public function redeliver(Request $request, string $order): JsonResponse
    {
        $model = $this->orders->findAdmin($order);
        $updated = $this->fulfillment->redeliver($model, AuthUser::id($request));

        return ApiResponse::ok($updated->fresh()?->toAdmin() ?? $updated->toAdmin(), 'تحویل دوباره در صف قرار گرفت.');
    }

    public function refresh(string $order): JsonResponse
    {
        $model = $this->orders->findAdmin($order);
        $attempt = $model->latestAttempt();
        if ($attempt?->id) {
            RefreshFazerOrder::dispatchSync($attempt->id);
        }

        return ApiResponse::ok($model->fresh()?->toAdmin() ?? $model->toAdmin(), 'از تأمین‌کننده تازه شد.');
    }

    public function link(Request $request, string $order): JsonResponse
    {
        $data = $request->validate([
            'fazerOrderId' => ['required', 'string', 'max:64'],
        ]);
        $model = $this->orders->findAdmin($order);
        $updated = $this->fulfillment->linkFazerOrder($model, (string) $data['fazerOrderId'], AuthUser::id($request));

        return ApiResponse::ok($updated->toAdmin(), 'به سفارش Fazer وصل شد.');
    }

    public function abandon(Request $request, string $order): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $model = $this->orders->findAdmin($order);
        $updated = $this->fulfillment->abandonAttempt($model, (string) $data['reason'], AuthUser::id($request));

        return ApiResponse::ok($updated->toAdmin(), 'به‌عنوان ثبت‌نشده در Fazer علامت خورد.');
    }

    public function refund(Request $request, string $order, RefundService $refunds): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', 'in:wallet,manual_gateway'],
            'note' => ['nullable', 'string', 'max:500'],
            'externalRef' => ['nullable', 'string', 'max:80'],
        ]);
        $model = $this->orders->findAdmin($order);
        $actor = 'admin:'.AuthUser::id($request);
        $updated = $data['method'] === 'wallet'
            ? $refunds->refundToWallet($model, $actor, 'ADMIN', $data['note'] ?? null)
            : $refunds->markManual($model, $actor, trim((string) ($data['externalRef'] ?? 'manual')), $data['note'] ?? null);

        return ApiResponse::ok($updated->toAdmin(), 'بازگشت وجه ثبت شد. این کار کوین یا کش‌اوت نیست.');
    }

    public function reveal(Request $request, string $order, OrderDeliveryService $delivery): JsonResponse
    {
        $model = $this->orders->findAdmin($order);
        $payload = $delivery->reveal($model);
        $model->appendEvent('admin_reveal', ['code' => 'ADMIN_REVEAL', 'note' => AuthUser::id($request)]);
        $model->save();

        return ApiResponse::ok($payload)->header('Cache-Control', 'no-store');
    }

    public function markRefunded(Request $request, string $order): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $model = $this->orders->findAdmin($order);
        $updated = $this->orders->markRefunded($model, $data['reason'] ?? null);

        return ApiResponse::ok($updated->toAdmin(), 'بازگشت وجه ثبت شد. این کار کوین یا کش‌اوت نیست.');
    }

    public function cancel(string $order): JsonResponse
    {
        $model = $this->orders->findAdmin($order);
        $updated = $this->orders->cancelUnpaid($model);

        return ApiResponse::ok($updated->toAdmin(), 'سفارش لغو شد.');
    }

    public function alerts(): JsonResponse
    {
        $rows = AdminAlert::query()->whereNull('resolved_at')->orderByDesc('id')->limit(50)->get();

        return ApiResponse::ok([
            'items' => $rows->map(fn (AdminAlert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'dedupeKey' => $alert->dedupe_key,
                'payload' => $alert->payload,
                'openedAt' => $alert->opened_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function resolveAlert(AdminAlert $alert): JsonResponse
    {
        $alert->resolved_at = now();
        $alert->save();

        return ApiResponse::ok(['id' => $alert->id, 'resolved' => true], 'هشدار حل شد.');
    }

    public function summary(Shop2Client $shop2, Shop2Adapter $adapter, FazerCardsAdapter $fazerCards): JsonResponse
    {
        $data = $this->orders->dashboardSummary();
        $data['supplierWallet'] = null;
        $data['accountError'] = null;
        $data['accountErrorCode'] = null;
        $data['supplierWalletLow'] = false;
        $data['fazercardsWallet'] = null;
        if ($shop2->configured()) {
            try {
                $account = $adapter->account();
                $data['supplierWallet'] = $account['wallet'];
                $data['supplierWalletLow'] = ! \App\Support\Money::isPositive($account['wallet']);
            } catch (\App\Exceptions\ApiException $e) {
                $data['accountError'] = \App\Support\CustomerError::message($e->errorCode, $e->getMessage());
                $data['accountErrorCode'] = $e->errorCode;
                $data['supplierWalletLow'] = $e->errorCode === 'INSUFFICIENT_BALANCE';
            }
        }
        if ($fazerCards->configured()) {
            try {
                $fc = $fazerCards->account();
                $data['fazercardsWallet'] = $fc['wallet'];
                if (! \App\Support\Money::isPositive($fc['wallet'])) {
                    $data['supplierWalletLow'] = true;
                }
            } catch (\App\Exceptions\ApiException) {
            }
        }

        return ApiResponse::ok($data);
    }
}

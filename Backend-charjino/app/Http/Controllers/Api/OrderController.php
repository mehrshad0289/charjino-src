<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Jobs\RefreshFazerOrder;
use App\Modules\Orders\OrderDeliveryService;
use App\Modules\Orders\OrderService;
use App\Modules\Payments\PaymentService;
use App\Modules\Wallet\WalletService;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '' || strlen($key) > 80) {
            throw new ApiException('هدر Idempotency-Key لازم است.', 400, 'MISSING_IDEMPOTENCY_KEY');
        }

        $data = $request->validate([
            'skuId' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'requirements' => ['present', 'array'],
            'amount' => ['nullable', 'string', 'max:32'],
            'currency' => ['nullable', 'string', 'max:8'],
            'couponCode' => ['nullable', 'string', 'max:32'],
        ]);

        $order = $this->orders->createDraft(
            AuthUser::id($request),
            (int) $data['skuId'],
            (int) ($data['quantity'] ?? 1),
            $data['requirements'],
            $key,
            isset($data['amount']) ? (string) $data['amount'] : null,
            isset($data['currency']) ? (string) $data['currency'] : null,
            isset($data['couponCode']) ? (string) $data['couponCode'] : null,
        );

        return ApiResponse::ok($order->toCustomer());
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('perPage', 20)));
        $paginator = $this->orders->listForUser(AuthUser::id($request), $page, $perPage);

        return ApiResponse::ok([
            'items' => collect($paginator->items())->map(fn ($order) => $order->toCustomer())->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $number): JsonResponse
    {
        $order = $this->orders->findOwned(AuthUser::id($request), $number);

        return ApiResponse::ok($order->toCustomer());
    }

    public function pay(Request $request, string $number): JsonResponse
    {
        $order = $this->orders->findOwned(AuthUser::id($request), $number);
        $data = $request->validate([
            'returnOrigin' => ['nullable', 'string', 'max:180'],
            'method' => ['nullable', 'string', 'in:wallet,zarinpal,zibal,mock'],
        ]);
        if (($data['method'] ?? '') === 'wallet') {
            return ApiResponse::ok($this->payments->payWithWallet($order));
        }

        return ApiResponse::ok($this->payments->start($order, $data['returnOrigin'] ?? null));
    }

    public function reveal(Request $request, string $number): JsonResponse
    {
        $order = $this->orders->findOwned(AuthUser::id($request), $number);
        $payload = app(OrderDeliveryService::class)->reveal($order);

        return ApiResponse::ok($payload)->header('Cache-Control', 'no-store');
    }

    public function refresh(Request $request, string $number): JsonResponse
    {
        $order = $this->orders->findOwned(AuthUser::id($request), $number);
        $key = 'order-refresh:'.$order->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 1)) {
            throw new ApiException(\App\Support\CustomerError::message('RATE_LIMIT_EXCEEDED'), 429, 'RATE_LIMIT_EXCEEDED');
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 30);
        $attempt = $order->latestAttempt();
        if ($attempt?->fazer_order_id) {
            RefreshFazerOrder::dispatch($attempt->id)->onQueue('fulfillment');
        }

        return ApiResponse::ok(['queued' => true]);
    }

    public function wallet(Request $request, WalletService $wallets): JsonResponse
    {
        return ApiResponse::ok($wallets->customerPayload(AuthUser::id($request)));
    }
}

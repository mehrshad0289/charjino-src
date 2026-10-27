<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Modules\Payments\PaymentService;
use App\Support\ApiResponse;
use App\Support\DigitNormalizer;
use App\Support\ShopOrigin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function handle(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        $provider = strtolower(trim($provider));
        if ($provider === 'zibal') {
            return $this->handleZibal($request);
        }
        if ($provider === 'zarinpal') {
            return $this->handleZarinpal($request);
        }

        if ($provider !== 'mock') {
            if ($this->wantsBrowser($request)) {
                return redirect()->away($this->shopPaymentsZibal($request, null, [
                    'error' => 'unknown_psp',
                ]));
            }

            return ApiResponse::fail('درگاه پرداخت پشتیبانی نمی‌شود.', 404, 'PSP_UNKNOWN');
        }

        $authority = (string) ($request->input('authority') ?: $request->query('authority', ''));
        $status = (string) ($request->input('status') ?: $request->query('status', 'OK'));
        $payment = Payment::query()->where('authority', $authority)->where('provider', 'mock')->first();

        try {
            $order = $this->payments->mockCallback($authority, $status);
        } catch (ApiException $e) {
            if ($this->wantsBrowser($request)) {
                $number = $payment?->order?->number;
                if (is_string($number) && $number !== '') {
                    return redirect()->away($this->shopInvoiceUrl($request, $payment, $number, [
                        'from' => 'psp',
                        'error' => $e->errorCode ?: 'PAYMENT_FAILED',
                    ]));
                }

                return redirect()->away($this->shopInvoicesUrl($request, [
                    'from' => 'psp',
                    'error' => $e->errorCode ?: 'PAYMENT_FAILED',
                ], $payment));
            }

            throw $e;
        }

        if ($this->wantsBrowser($request)) {
            return redirect()->away($this->shopInvoiceUrl($request, $payment, (string) $order->number, ['from' => 'psp']));
        }

        return ApiResponse::ok($order->fresh()?->toCustomer() ?? $order->toCustomer(), 'پرداخت تأیید شد.');
    }

    public function verifyZarinpal(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->wantsBrowser($request)) {
            return $this->handleZarinpal($request);
        }

        $order = $this->payments->verifyZarinpal($this->zarinpalInput($request));

        return ApiResponse::ok($order->fresh()?->toCustomer() ?? $order->toCustomer(), 'پرداخت تأیید شد.');
    }

    public function verifyZibal(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->wantsBrowser($request)) {
            return $this->handleZibal($request);
        }

        $order = $this->payments->verifyZibal($this->zibalInput($request));

        return ApiResponse::ok($order->fresh()?->toCustomer() ?? $order->toCustomer(), 'پرداخت تأیید شد.');
    }

    private function handleZibal(Request $request): JsonResponse|RedirectResponse
    {
        $input = $this->zibalInput($request);
        $trackId = DigitNormalizer::digits((string) ($input['trackId'] ?? ''));
        $payment = $trackId !== ''
            ? Payment::query()->where('provider', 'zibal')->where('authority', $trackId)->latest('id')->first()
            : null;

        if ($this->wantsBrowser($request)) {
            return redirect()->away($this->shopPaymentsZibal($request, $payment, [
                'trackId' => (string) ($input['trackId'] ?? ''),
                'success' => (string) ($input['success'] ?? ''),
                'status' => (string) ($input['status'] ?? ''),
                'orderId' => (string) ($input['orderId'] ?? ''),
            ]));
        }

        try {
            $order = $this->payments->verifyZibal($input);
        } catch (ApiException $e) {
            throw $e;
        }

        return ApiResponse::ok($order->fresh()?->toCustomer() ?? $order->toCustomer(), 'پرداخت تأیید شد.');
    }

    private function handleZarinpal(Request $request): JsonResponse|RedirectResponse
    {
        $input = $this->zarinpalInput($request);
        $authority = trim((string) ($input['authority'] ?? ''));
        $payment = $authority !== ''
            ? Payment::query()->where('provider', 'zarinpal')->where('authority', $authority)->latest('id')->first()
            : null;

        if ($this->wantsBrowser($request)) {
            return redirect()->away($this->shopPaymentsZarinpal($request, $payment, [
                'Authority' => (string) ($input['authority'] ?? ''),
                'Status' => (string) ($input['status'] ?? ''),
            ]));
        }

        $order = $this->payments->verifyZarinpal($input);

        return ApiResponse::ok($order->fresh()?->toCustomer() ?? $order->toCustomer(), 'پرداخت تأیید شد.');
    }

    /**
     * @return array{authority: string, status: string}
     */
    private function zarinpalInput(Request $request): array
    {
        return [
            'authority' => (string) ($request->input('authority')
                ?: $request->input('Authority')
                ?: $request->query('Authority', $request->query('authority', ''))),
            'status' => (string) ($request->input('status')
                ?: $request->input('Status')
                ?: $request->query('Status', $request->query('status', ''))),
        ];
    }

    /**
     * @return array{trackId: string, success: string, status: string, orderId: string}
     */
    private function zibalInput(Request $request): array
    {
        return [
            'trackId' => (string) ($request->input('trackId') ?: $request->query('trackId', '')),
            'success' => (string) ($request->input('success') ?: $request->query('success', '')),
            'status' => (string) ($request->input('status') ?: $request->query('status', '')),
            'orderId' => (string) ($request->input('orderId') ?: $request->query('orderId', '')),
        ];
    }

    private function wantsBrowser(Request $request): bool
    {
        return $request->isMethod('GET') || $request->isMethod('HEAD');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function shopInvoiceUrl(Request $request, ?Payment $payment, string $number, array $query = []): string
    {
        $base = $this->shopBase($request, $payment);

        return $base.'/invoices/'.rawurlencode($number).($query !== [] ? '?'.http_build_query($query) : '');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function shopInvoicesUrl(Request $request, array $query = [], ?Payment $payment = null): string
    {
        $base = $this->shopBase($request, $payment);

        return $base.'/invoices'.($query !== [] ? '?'.http_build_query($query) : '');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function shopPaymentsZibal(Request $request, ?Payment $payment, array $query = []): string
    {
        $base = $this->shopBase($request, $payment);
        $filtered = array_filter($query, fn ($value) => $value !== '');

        return $base.'/payments/zibal'.($filtered !== [] ? '?'.http_build_query($filtered) : '');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function shopPaymentsZarinpal(Request $request, ?Payment $payment, array $query = []): string
    {
        $base = $this->shopBase($request, $payment);
        $filtered = array_filter($query, fn ($value) => $value !== '');

        return $base.'/payments/zarinpal'.($filtered !== [] ? '?'.http_build_query($filtered) : '');
    }

    private function shopBase(Request $request, ?Payment $payment): string
    {
        return ShopOrigin::allowed((string) $request->query('front', ''))
            ?: ShopOrigin::allowed((string) data_get($payment?->raw, 'front', ''))
            ?: rtrim((string) config('charjino.shop_url'), '/');
    }
}

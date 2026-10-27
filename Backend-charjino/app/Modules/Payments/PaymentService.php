<?php

namespace App\Modules\Payments;

use App\Exceptions\ApiException;
use App\Jobs\FulfillPaidOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Modules\Ops\AdminAlertService;
use App\Modules\Orders\RefundService;
use App\Support\CustomerError;
use App\Support\DigitNormalizer;
use App\Support\SafeLog;
use App\Support\ShopOrigin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly ZibalClient $zibal,
        private readonly ZarinpalClient $zarinpal,
    ) {}

    /**
     * @return array{redirectUrl: string, authority: string, provider: string, order: array<string, mixed>}
     */
    public function start(Order $order, ?string $returnOrigin = null): array
    {
        if (! $order->isPayable()) {
            if ($order->expires_at && $order->expires_at->isPast()) {
                throw new ApiException(CustomerError::message('PAYMENT_EXPIRED'), 410, 'PAYMENT_EXPIRED');
            }
            throw new ApiException(CustomerError::message('ORDER_NOT_PAYABLE'), 400, 'ORDER_NOT_PAYABLE');
        }

        $this->recheckBeforePay($order);

        $front = ShopOrigin::allowed($returnOrigin);
        if ($this->isMock()) {
            return $this->startMock($order, $front);
        }

        if ($this->driver() === 'zarinpal') {
            return $this->startZarinpal($order, $front);
        }

        return $this->startZibal($order, $front);
    }

    public function mockCallback(string $authority, string $status): Order
    {
        if (! $this->isMock()) {
            throw new ApiException('مسیر پیدا نشد.', 404);
        }

        $payment = Payment::query()->where('authority', $authority)->where('provider', 'mock')->first();
        if (! $payment) {
            throw new ApiException('پرداخت پیدا نشد.', 404, 'PAYMENT_NOT_FOUND');
        }

        return $this->complete($payment, strtoupper($status) === 'OK');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function verifyZibal(array $input): Order
    {
        $trackId = DigitNormalizer::digits((string) ($input['trackId'] ?? $input['authority'] ?? ''));
        $orderId = trim((string) ($input['orderId'] ?? ''));
        $success = DigitNormalizer::digits((string) ($input['success'] ?? ''));
        $status = DigitNormalizer::digits((string) ($input['status'] ?? ''));

        if ($trackId === '') {
            throw new ApiException('شناسه تراکنش نامعتبر است.', 400, 'PAYMENT_FAILED');
        }

        $payment = $this->findZibalPayment($trackId, $orderId);
        if (! $payment) {
            throw new ApiException('پرداخت پیدا نشد.', 404, 'PAYMENT_NOT_FOUND');
        }

        $existing = $payment->order;
        if ($existing && in_array($existing->status, [
            Order::STATUS_PAID,
            Order::STATUS_FULFILLING,
            Order::STATUS_DELIVERED,
        ], true)) {
            return $existing;
        }

        if ($success !== '1') {
            return $this->complete($payment, false, [
                'success' => $success,
                'status' => $status,
                'trackId' => $trackId,
            ]);
        }

        $remote = $this->zibal->verifyPayment($trackId);
        $paidToman = isset($remote['amount']) ? (int) round(((float) $remote['amount']) / 10) : 0;
        if ($paidToman > 0 && $paidToman !== (int) $payment->amount_toman) {
            SafeLog::warning('zibal.amount_mismatch', [
                'number' => $payment->order?->number,
                'expected' => $payment->amount_toman,
                'paid' => $paidToman,
            ]);
            throw new ApiException(CustomerError::message('PAYMENT_FAILED'), 400, 'PAYMENT_FAILED');
        }

        return $this->complete($payment, true, $remote);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function verifyZarinpal(array $input): Order
    {
        $authority = trim((string) ($input['authority'] ?? $input['Authority'] ?? ''));
        $status = strtoupper(trim((string) ($input['status'] ?? $input['Status'] ?? '')));

        if (! $this->zarinpal->validAuthority($authority)) {
            throw new ApiException('شناسه تراکنش نامعتبر است.', 400, 'PAYMENT_FAILED');
        }

        $payment = Payment::query()
            ->where('provider', 'zarinpal')
            ->where('authority', $authority)
            ->latest('id')
            ->first();
        if (! $payment) {
            throw new ApiException('پرداخت پیدا نشد.', 404, 'PAYMENT_NOT_FOUND');
        }

        $existing = $payment->order;
        if ($existing && in_array($existing->status, [
            Order::STATUS_PAID,
            Order::STATUS_FULFILLING,
            Order::STATUS_DELIVERED,
            Order::STATUS_REFUND_PENDING,
            Order::STATUS_REFUNDED,
        ], true)) {
            return $existing;
        }
        if ($payment->status === Payment::STATUS_VERIFIED && $existing) {
            return $existing;
        }

        if ($status !== '' && $status !== 'OK') {
            return $this->complete($payment, false, ['status' => $status]);
        }

        $amountRial = (int) (data_get($payment->raw, 'amount_rial') ?: ((int) $payment->amount_toman * 10));

        try {
            $remote = $this->zarinpal->verifyPayment($authority, $amountRial);
        } catch (ApiException $e) {
            if ($e->errorCode === 'PAYMENT_AMOUNT_MISMATCH') {
                $this->markPaymentReview($payment, ['code' => -50], 'AMOUNT_MISMATCH');
            }
            throw $e;
        }

        return $this->complete($payment, true, $remote);
    }

    /**
     * Inquiry before expireUnpaid: skip expiry when ZarinPal still has a live or paid session.
     */
    public function holdExpireForZarinpal(Order $order): bool
    {
        $payment = $order->payments()
            ->where('provider', 'zarinpal')
            ->whereNotNull('authority')
            ->latest('id')
            ->first();
        if (! $payment || ! $this->zarinpal->validAuthority((string) $payment->authority)) {
            return false;
        }

        if ($payment->status === Payment::STATUS_VERIFIED) {
            $this->complete($payment, true);

            return true;
        }

        try {
            $inquiry = $this->zarinpal->inquiry((string) $payment->authority);
        } catch (\Throwable) {
            return true;
        }

        $status = strtoupper((string) ($inquiry['status'] ?? ''));
        if (in_array($status, ['PAID', 'VERIFIED'], true)) {
            try {
                $this->verifyZarinpal([
                    'authority' => (string) $payment->authority,
                    'status' => 'OK',
                ]);
            } catch (ApiException) {
            }

            return true;
        }

        return $status === 'IN_BANK';
    }

    /**
     * @param  array<string, mixed>  $psp
     */
    public function complete(Payment $payment, bool $ok, array $psp = []): Order
    {
        $shouldFulfill = false;
        $pending = null;
        $order = DB::transaction(function () use ($payment, $ok, $psp, &$shouldFulfill, &$pending) {
            $locked = Payment::query()->where('id', $payment->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->where('id', $locked->order_id)->lockForUpdate()->firstOrFail();
            $raw = is_array($locked->raw) ? $locked->raw : [];
            $pspBag = $locked->provider === 'zarinpal' ? 'zarinpal' : 'zibal';
            $pspPublic = $locked->provider === 'zarinpal' ? $this->publicZarinpal($psp) : $this->publicZibal($psp);

            if ($order->status === Order::STATUS_PAID
                || $order->status === Order::STATUS_FULFILLING
                || $order->status === Order::STATUS_DELIVERED) {
                return $order;
            }

            if (in_array($order->status, [Order::STATUS_REFUND_PENDING, Order::STATUS_REFUNDED], true)
                && $locked->status === Payment::STATUS_VERIFIED) {
                return $order;
            }

            $expired = $order->expires_at && $order->expires_at->isPast();
            $payable = in_array($order->status, [Order::STATUS_DRAFT, Order::STATUS_PENDING_PAYMENT], true);

            if ($ok && ($expired || $order->status === Order::STATUS_EXPIRED)) {
                $this->markPaymentVerified($locked, $raw, $pspBag, $pspPublic, $psp);
                if ($order->status !== Order::STATUS_EXPIRED) {
                    $order->customer_message = CustomerError::message('PAYMENT_EXPIRED');
                    $order = app(\App\Modules\Orders\OrderStateMachine::class)
                        ->transition($order, Order::STATUS_EXPIRED, 'system', 'PAYMENT_EXPIRED');
                }
                $order->customer_message = 'پرداخت تأیید شد. سفارش منقضی بود و مبلغ برای بازگشت ثبت شد.';
                $order->save();

                return app(RefundService::class)->onDefinitiveFailure($order, 'LATE_PAYMENT_VERIFIED');
            }

            if (! $payable) {
                throw new ApiException(CustomerError::message('ORDER_NOT_PAYABLE'), 400, 'ORDER_NOT_PAYABLE');
            }

            if ($expired) {
                $locked->status = Payment::STATUS_EXPIRED;
                $locked->raw = array_merge($raw, ['result' => 'expired']);
                $locked->save();
                $order->customer_message = CustomerError::message('PAYMENT_EXPIRED');
                app(\App\Modules\Orders\OrderStateMachine::class)->transition($order, Order::STATUS_EXPIRED, 'system', 'PAYMENT_EXPIRED');
                $pending = new ApiException(CustomerError::message('PAYMENT_EXPIRED'), 410, 'PAYMENT_EXPIRED');

                return $order;
            }

            if (! $ok) {
                $locked->status = Payment::STATUS_FAILED;
                $locked->raw = array_merge($raw, ['result' => 'NOK', $pspBag => $pspPublic]);
                $locked->save();
                $order->customer_message = CustomerError::message('PAYMENT_FAILED');
                $order->appendEvent('payment_failed', ['code' => 'PAYMENT_FAILED']);
                $order->save();
                $pending = new ApiException(CustomerError::message('PAYMENT_FAILED'), 400, 'PAYMENT_FAILED');

                return $order;
            }

            $this->markPaymentVerified($locked, $raw, $pspBag, $pspPublic, $psp);

            $order->customer_message = null;
            app(\App\Modules\Orders\OrderStateMachine::class)->transition($order, Order::STATUS_PAID, 'system', 'PAID');

            SafeLog::info('order.paid', ['number' => $order->number]);
            $shouldFulfill = true;

            return $order;
        });

        if ($pending instanceof ApiException) {
            throw $pending;
        }

        if ($shouldFulfill) {
            if ($this->fulfillInline()) {
                FulfillPaidOrder::dispatchSync($order->id);
            } else {
                FulfillPaidOrder::dispatch($order->id)->afterCommit()->onQueue('fulfillment');
            }
        }

        return $order->fresh() ?? $order;
    }

    public function isMock(): bool
    {
        return (bool) config('charjino.orders.mock_payments');
    }

    public function driver(): string
    {
        return (string) config('payments.driver', config('charjino.orders.payment_driver', 'zibal'));
    }

    /**
     * @return array{redirectUrl: string, authority: string, provider: string, order: array<string, mixed>}
     */
    private function startMock(Order $order, ?string $front): array
    {
        $existing = $order->payments()->where('status', Payment::STATUS_REDIRECTED)->latest('id')->first();
        if ($existing && $order->status === Order::STATUS_PENDING_PAYMENT) {
            if ($front) {
                $raw = is_array($existing->raw) ? $existing->raw : [];
                $raw['front'] = $front;
                $existing->raw = $raw;
                $existing->save();
            }

            return $this->mockPayload($order, $existing);
        }

        $authority = 'MOCK-'.strtoupper(Str::random(16));
        $raw = ['startedAt' => now()->toIso8601String()];
        if ($front) {
            $raw['front'] = $front;
        }
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'mock',
            'amount_toman' => $order->amount_toman,
            'status' => Payment::STATUS_REDIRECTED,
            'authority' => $authority,
            'raw' => $raw,
        ]);

        app(\App\Modules\Orders\OrderStateMachine::class)->transition($order, Order::STATUS_AWAITING_PAYMENT, 'customer', 'PAY_STARTED');

        return $this->mockPayload($order, $payment);
    }

    /**
     * @return array{redirectUrl: string, authority: string, provider: string, order: array<string, mixed>}
     */
    private function startZibal(Order $order, ?string $front): array
    {
        if (! $this->zibal->configured()) {
            throw new ApiException('درگاه پرداخت واقعی هنوز فعال نیست.', 503, 'PSP_UNAVAILABLE');
        }

        $existing = $order->payments()
            ->where('provider', 'zibal')
            ->where('status', Payment::STATUS_REDIRECTED)
            ->latest('id')
            ->first();
        if ($existing && $order->status === Order::STATUS_PENDING_PAYMENT && filled($existing->authority)) {
            if ($front) {
                $raw = is_array($existing->raw) ? $existing->raw : [];
                $raw['front'] = $front;
                $existing->raw = $raw;
                $existing->save();
            }

            return $this->zibalPayload($order, $existing);
        }

        $shop = $front ?: rtrim((string) config('charjino.shop_url'), '/');
        $configuredCallback = trim((string) config('charjino.zibal.callback_url'));
        $callback = $configuredCallback !== '' ? $configuredCallback : $shop.'/payments/zibal';

        $order->loadMissing('user');
        $requested = $this->zibal->requestPayment(
            (int) $order->amount_toman,
            $callback,
            (string) $order->number,
            'خرید از چارژینو '.$order->number,
            $order->user?->phone,
        );

        $raw = [
            'startedAt' => now()->toIso8601String(),
            'callbackUrl' => $callback,
        ];
        if ($front) {
            $raw['front'] = $front;
        }

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'zibal',
            'amount_toman' => $order->amount_toman,
            'status' => Payment::STATUS_REDIRECTED,
            'authority' => $requested['trackId'],
            'raw' => $raw,
        ]);

        app(\App\Modules\Orders\OrderStateMachine::class)->transition($order, Order::STATUS_AWAITING_PAYMENT, 'customer', 'PAY_STARTED');

        return $this->zibalPayload($order, $payment);
    }

    /**
     * @return array{redirectUrl: string, authority: string, provider: string, order: array<string, mixed>}
     */
    private function startZarinpal(Order $order, ?string $front): array
    {
        if (! $this->zarinpal->configured()) {
            throw new ApiException('درگاه پرداخت واقعی هنوز فعال نیست.', 503, 'PSP_UNAVAILABLE');
        }

        $existing = $order->payments()
            ->where('provider', 'zarinpal')
            ->where('status', Payment::STATUS_REDIRECTED)
            ->latest('id')
            ->first();
        if ($existing && $order->status === Order::STATUS_PENDING_PAYMENT && filled($existing->authority)) {
            if ($front) {
                $raw = is_array($existing->raw) ? $existing->raw : [];
                $raw['front'] = $front;
                $existing->raw = $raw;
                $existing->save();
            }

            return $this->zarinpalPayload($order, $existing);
        }

        $shop = rtrim((string) config('charjino.shop_url'), '/');
        $configuredCallback = trim((string) config('payments.callback_url'));
        $callback = $configuredCallback !== '' ? $configuredCallback : $shop.'/payments/zarinpal';

        $amountRial = (int) $order->amount_toman * 10;
        $order->loadMissing('user');
        $requested = $this->zarinpal->requestPayment(
            $amountRial,
            $callback,
            (string) $order->number,
            'خرید از چارژینو '.$order->number,
            $order->user?->phone,
        );

        $raw = [
            'startedAt' => now()->toIso8601String(),
            'callbackUrl' => $callback,
            'amount_rial' => $amountRial,
            'currency' => 'IRR',
        ];
        if ($front) {
            $raw['front'] = $front;
        }
        if ($requested['fee'] !== null) {
            $raw['fee'] = $requested['fee'];
        }
        if ($requested['fee_type'] !== null) {
            $raw['fee_type'] = $requested['fee_type'];
        }

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'zarinpal',
            'amount_toman' => $order->amount_toman,
            'status' => Payment::STATUS_REDIRECTED,
            'authority' => $requested['authority'],
            'raw' => $raw,
        ]);

        app(\App\Modules\Orders\OrderStateMachine::class)->transition($order, Order::STATUS_AWAITING_PAYMENT, 'customer', 'PAY_STARTED');

        return $this->zarinpalPayload($order, $payment);
    }

    /**
     * @return array{redirectUrl: string, authority: string, provider: string, order: array<string, mixed>}
     */
    private function mockPayload(Order $order, Payment $payment): array
    {
        $query = [
            'authority' => (string) $payment->authority,
            'status' => 'OK',
        ];
        $front = ShopOrigin::allowed((string) data_get($payment->raw, 'front', ''));
        if ($front) {
            $query['front'] = $front;
        }

        return [
            'redirectUrl' => url('/api/payments/callback/mock').'?'.http_build_query($query),
            'authority' => (string) $payment->authority,
            'provider' => 'mock',
            'order' => $order->fresh()?->toCustomer() ?? $order->toCustomer(),
        ];
    }

    /**
     * @return array{redirectUrl: string, authority: string, provider: string, order: array<string, mixed>}
     */
    private function zibalPayload(Order $order, Payment $payment): array
    {
        return [
            'redirectUrl' => $this->zibal->startPayUrl((string) $payment->authority),
            'authority' => (string) $payment->authority,
            'provider' => 'zibal',
            'order' => $order->fresh()?->toCustomer() ?? $order->toCustomer(),
        ];
    }

    /**
     * @return array{redirectUrl: string, authority: string, provider: string, order: array<string, mixed>}
     */
    private function zarinpalPayload(Order $order, Payment $payment): array
    {
        return [
            'redirectUrl' => $this->zarinpal->startPayUrl((string) $payment->authority),
            'authority' => (string) $payment->authority,
            'provider' => 'zarinpal',
            'order' => $order->fresh()?->toCustomer() ?? $order->toCustomer(),
        ];
    }

    private function findZibalPayment(string $trackId, string $orderId): ?Payment
    {
        $payment = Payment::query()
            ->where('provider', 'zibal')
            ->where('authority', $trackId)
            ->latest('id')
            ->first();
        if ($payment) {
            return $payment;
        }

        if ($orderId === '') {
            return null;
        }

        $order = Order::query()->where('number', $orderId)->orWhere('id', $orderId)->first();
        if (! $order) {
            return null;
        }

        return $order->payments()->where('provider', 'zibal')->latest('id')->first();
    }

    /**
     * @param  array<string, mixed>  $psp
     * @return array<string, mixed>
     */
    private function publicZibal(array $psp): array
    {
        $amountRial = $psp['amount'] ?? null;

        return array_filter([
            'result' => $psp['result'] ?? null,
            'status' => $psp['status'] ?? null,
            'refNumber' => $psp['refNumber'] ?? $psp['ref'] ?? null,
            'cardNumber' => $psp['cardNumber'] ?? $psp['card_pan'] ?? null,
            'paidAt' => $psp['paidAt'] ?? null,
            'amountRial' => $amountRial,
            'amountToman' => is_numeric($amountRial) ? (int) round(((float) $amountRial) / 10) : null,
            'orderId' => $psp['orderId'] ?? null,
            'trackId' => $psp['trackId'] ?? null,
            'message' => $psp['message'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $psp
     * @return array<string, mixed>
     */
    private function publicZarinpal(array $psp): array
    {
        return array_filter([
            'code' => $psp['code'] ?? null,
            'message' => $psp['message'] ?? null,
            'ref_id' => $psp['ref_id'] ?? $psp['ref'] ?? null,
            'card_pan' => $psp['card_pan'] ?? null,
            'fee' => $psp['fee'] ?? null,
            'fee_type' => $psp['fee_type'] ?? null,
            'status' => $psp['status'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $pspPublic
     * @param  array<string, mixed>  $psp
     */
    private function markPaymentVerified(Payment $locked, array $raw, string $pspBag, array $pspPublic, array $psp): void
    {
        $ref = trim((string) ($psp['ref_id'] ?? $psp['refNumber'] ?? $psp['ref'] ?? $locked->authority));
        $locked->status = Payment::STATUS_VERIFIED;
        $locked->ref = $ref !== '' ? mb_substr($ref, 0, 64) : (string) $locked->authority;
        $locked->raw = array_merge($raw, [
            'result' => 'OK',
            $pspBag => $pspPublic,
        ]);
        $locked->save();
    }

    /**
     * @param  array<string, mixed>  $psp
     */
    private function markPaymentReview(Payment $payment, array $psp, string $reason): void
    {
        DB::transaction(function () use ($payment, $psp, $reason) {
            $locked = Payment::query()->where('id', $payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Payment::STATUS_VERIFIED) {
                return;
            }
            $raw = is_array($locked->raw) ? $locked->raw : [];
            $locked->status = Payment::STATUS_REVIEW;
            $locked->raw = array_merge($raw, [
                'result' => 'review',
                'reason' => $reason,
                'zarinpal' => $this->publicZarinpal($psp),
            ]);
            $locked->save();
        });
        app(AdminAlertService::class)->open('NEEDS_REVIEW', 'payment-review:'.$payment->id, [
            'order_number' => $payment->order?->number,
            'code' => $reason,
        ]);
    }

    /**
     * @return array{redirectUrl: string, authority: string, provider: string, order: array<string, mixed>}
     */
    public function payWithWallet(Order $order): array
    {
        if (! $order->isPayable()) {
            throw new ApiException(CustomerError::message('ORDER_NOT_PAYABLE'), 400, 'ORDER_NOT_PAYABLE');
        }
        $this->recheckBeforePay($order);
        $wallets = app(\App\Modules\Wallet\WalletService::class);
        $wallets->debit((string) $order->user_id, (int) $order->amount_toman, 'order_payment', 'pay:'.$order->id, $order->id);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'wallet',
            'amount_toman' => $order->amount_toman,
            'status' => Payment::STATUS_VERIFIED,
            'authority' => 'WALLET-'.$order->number,
            'ref' => $order->number,
            'raw' => ['method' => 'wallet'],
        ]);

        return [
            'redirectUrl' => '',
            'authority' => (string) $payment->authority,
            'provider' => 'wallet',
            'order' => $this->complete($payment, true)->toCustomer(),
        ];
    }

    private function recheckBeforePay(Order $order): void
    {
        if ((int) $order->amount_toman <= 0) {
            throw new ApiException(CustomerError::message('ORDER_NOT_PAYABLE'), 400, 'ORDER_NOT_PAYABLE');
        }
        if ($order->coupon_id) {
            app(\App\Modules\Coupons\CouponService::class)->assertStillValid($order);
        }
    }

    private function fulfillInline(): bool
    {
        return (string) config('queue.default') === 'sync';
    }
}

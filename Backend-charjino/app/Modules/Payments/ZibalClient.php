<?php

namespace App\Modules\Payments;

use App\Exceptions\ApiException;
use App\Support\DigitNormalizer;
use App\Support\SafeLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class ZibalClient
{
    public function configured(): bool
    {
        return $this->merchant() !== '';
    }

    public function merchant(): string
    {
        $merchant = trim((string) config('charjino.zibal.merchant'));
        if ($merchant !== '') {
            return $merchant;
        }

        $env = (string) config('app.env');
        if (in_array($env, ['local', 'testing', 'development'], true) || (bool) config('charjino.zibal.test_mode')) {
            return 'zibal';
        }

        return '';
    }

    public function startPayUrl(string $trackId): string
    {
        return rtrim((string) config('charjino.zibal.start_pay_url'), '/').'/'.$trackId;
    }

    /**
     * @return array{trackId: string}
     */
    public function requestPayment(int $amountToman, string $callbackUrl, string $orderId, string $description, ?string $mobile = null): array
    {
        if (! $this->configured()) {
            throw new ApiException('درگاه پرداخت واقعی هنوز فعال نیست.', 503, 'PSP_UNAVAILABLE');
        }

        $amountRial = $amountToman * 10;
        if ($amountRial < 1000) {
            throw new ApiException('مبلغ پرداخت نامعتبر است.', 400, 'PAYMENT_FAILED');
        }

        $payload = [
            'merchant' => $this->merchant(),
            'amount' => $amountRial,
            'callbackUrl' => $callbackUrl,
            'description' => $description,
            'orderId' => $orderId,
        ];
        $phone = DigitNormalizer::iranMobile($mobile);
        if (preg_match('/^09\d{9}$/', $phone) === 1) {
            $payload['mobile'] = $phone;
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->asJson()
                ->post($this->base().'request', $payload);
        } catch (ConnectionException $e) {
            SafeLog::warning('zibal.request_unreachable', ['orderId' => $orderId]);
            throw new ApiException('اتصال به درگاه پرداخت برقرار نشد.', 503, 'PSP_UNAVAILABLE');
        }

        $data = is_array($response->json()) ? $response->json() : [];
        $result = (int) ($data['result'] ?? 0);
        $trackId = DigitNormalizer::digits((string) ($data['trackId'] ?? ''));

        if (! $response->successful() || $result !== 100 || $trackId === '') {
            SafeLog::warning('zibal.request_failed', [
                'orderId' => $orderId,
                'http' => $response->status(),
                'result' => $result,
            ]);
            throw new ApiException(
                'خطا در درگاه پرداخت: '.(string) ($data['message'] ?? 'خطای نامشخص'),
                502,
                'PSP_UNAVAILABLE',
            );
        }

        return ['trackId' => $trackId];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayment(string $trackId): array
    {
        $trackId = DigitNormalizer::digits($trackId);
        if ($trackId === '') {
            throw new ApiException('شناسه تراکنش نامعتبر است.', 400, 'PAYMENT_FAILED');
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->asJson()
                ->post($this->base().'verify', [
                    'merchant' => $this->merchant(),
                    'trackId' => $trackId,
                ]);
        } catch (ConnectionException) {
            throw new ApiException('اتصال به درگاه پرداخت برقرار نشد.', 503, 'PSP_UNAVAILABLE');
        }

        $data = is_array($response->json()) ? $response->json() : [];
        $result = (int) ($data['result'] ?? 0);
        if (! $response->successful() || ! in_array($result, [100, 201], true)) {
            throw new ApiException(
                'پرداخت با خطا مواجه شد: '.(string) ($data['message'] ?? 'خطای نامشخص'),
                400,
                'PAYMENT_FAILED',
            );
        }

        $data['trackId'] = $trackId;
        $data['result'] = $result;

        return $data;
    }

    private function base(): string
    {
        return rtrim((string) config('charjino.zibal.base_url'), '/').'/';
    }
}

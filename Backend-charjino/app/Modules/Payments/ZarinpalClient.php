<?php

namespace App\Modules\Payments;

use App\Exceptions\ApiException;
use App\Support\CustomerError;
use App\Support\DigitNormalizer;
use App\Support\SafeLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class ZarinpalClient
{
    public function configured(): bool
    {
        return $this->merchantId() !== '';
    }

    public function merchantId(): string
    {
        return trim((string) config('payments.zarinpal.merchant_id'));
    }

    public function startPayUrl(string $authority): string
    {
        $base = $this->sandbox()
            ? (string) config('payments.zarinpal.sandbox_start_pay_url')
            : (string) config('payments.zarinpal.start_pay_url');

        return rtrim($base, '/').'/'.$authority;
    }

    /**
     * @return array{authority: string, fee: int|null, fee_type: string|null, raw: array<string, mixed>}
     */
    public function requestPayment(int $amountRial, string $callbackUrl, string $orderNumber, string $description, ?string $mobile = null): array
    {
        if (! $this->configured()) {
            throw new ApiException('درگاه پرداخت واقعی هنوز فعال نیست.', 503, 'PSP_UNAVAILABLE');
        }

        $min = (int) config('payments.min_rial', 10_000);
        $max = (int) config('payments.max_rial', 1_000_000_000);
        if ($amountRial < $min || $amountRial > $max) {
            throw new ApiException('مبلغ پرداخت نامعتبر است.', 400, 'PAYMENT_FAILED');
        }

        $metadata = [
            'order_id' => $orderNumber,
            'auto_verify' => false,
        ];
        $phone = DigitNormalizer::iranMobile($mobile);
        if (preg_match('/^09\d{9}$/', $phone) === 1) {
            $metadata['mobile'] = $phone;
        }

        $payload = [
            'merchant_id' => $this->merchantId(),
            'amount' => $amountRial,
            'currency' => 'IRR',
            'description' => mb_substr($description, 0, 500),
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ];

        $json = $this->post('request.json', $payload, $orderNumber);
        $code = $this->extractCode($json);
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $authority = trim((string) ($data['authority'] ?? ''));

        if ($code !== 100 || ! $this->validAuthority($authority)) {
            SafeLog::warning('zarinpal.request_failed', [
                'order_number' => $orderNumber,
                'code' => $code,
            ]);
            throw new ApiException(
                'خطا در درگاه پرداخت: '.$this->publicMessage($json),
                502,
                'PSP_UNAVAILABLE',
            );
        }

        return [
            'authority' => $authority,
            'fee' => isset($data['fee']) && is_numeric($data['fee']) ? (int) $data['fee'] : null,
            'fee_type' => isset($data['fee_type']) ? (string) $data['fee_type'] : null,
            'raw' => $this->redact($data),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayment(string $authority, int $amountRial): array
    {
        if (! $this->validAuthority($authority)) {
            throw new ApiException('شناسه تراکنش نامعتبر است.', 400, 'PAYMENT_FAILED');
        }

        $json = $this->post('verify.json', [
            'merchant_id' => $this->merchantId(),
            'amount' => $amountRial,
            'authority' => $authority,
        ], $authority);
        $code = $this->extractCode($json);
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $data['code'] = $code;

        if (in_array($code, [100, 101], true)) {
            return $data;
        }

        SafeLog::warning('zarinpal.verify_failed', [
            'authority_tail' => substr($authority, -6),
            'code' => $code,
        ]);

        if ($code === -50) {
            throw new ApiException(CustomerError::message('PAYMENT_AMOUNT_MISMATCH'), 400, 'PAYMENT_AMOUNT_MISMATCH');
        }

        throw new ApiException(
            'پرداخت با خطا مواجه شد: '.$this->publicMessage($json),
            400,
            'PAYMENT_FAILED',
        );
    }

    /**
     * @return array{status: string, code: int, raw: array<string, mixed>}
     */
    public function inquiry(string $authority): array
    {
        if (! $this->validAuthority($authority)) {
            throw new ApiException('شناسه تراکنش نامعتبر است.', 400, 'PAYMENT_FAILED');
        }

        $json = $this->post('inquiry.json', [
            'merchant_id' => $this->merchantId(),
            'authority' => $authority,
        ], $authority);
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $status = strtoupper(trim((string) ($data['status'] ?? '')));

        return [
            'status' => $status !== '' ? $status : 'UNKNOWN',
            'code' => $this->extractCode($json),
            'raw' => $this->redact(is_array($json) ? $json : []),
        ];
    }

    public function validAuthority(string $authority): bool
    {
        return preg_match('/^[AS][0-9A-Za-z]{35}$/', $authority) === 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, string $logRef): array
    {
        try {
            $response = Http::connectTimeout((int) config('payments.zarinpal.connect_timeout', 5))
                ->timeout((int) config('payments.zarinpal.timeout', 15))
                ->acceptJson()
                ->asJson()
                ->post($this->base().ltrim($path, '/'), $payload);
        } catch (ConnectionException) {
            SafeLog::warning('zarinpal.unreachable', ['ref' => $logRef]);
            throw new ApiException('اتصال به درگاه پرداخت برقرار نشد.', 503, 'PSP_UNAVAILABLE');
        }

        $json = $response->json();
        if (! is_array($json)) {
            SafeLog::warning('zarinpal.bad_response', [
                'ref' => $logRef,
                'http' => $response->status(),
            ]);
            throw new ApiException('پاسخ درگاه پرداخت نامعتبر است.', 502, 'PSP_UNAVAILABLE');
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractCode(array $json): int
    {
        $data = $json['data'] ?? null;
        if (is_array($data) && isset($data['code']) && is_numeric($data['code'])) {
            return (int) $data['code'];
        }
        $errors = $json['errors'] ?? null;
        if (is_array($errors) && isset($errors['code']) && is_numeric($errors['code'])) {
            return (int) $errors['code'];
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function publicMessage(array $json): string
    {
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $errors = $json['errors'] ?? null;
        $message = (string) ($data['message'] ?? '');
        if ($message === '' && is_array($errors)) {
            $message = (string) ($errors['message'] ?? '');
        }

        $clean = trim($message);

        return $clean !== '' ? $clean : 'خطای نامشخص';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        unset($data['merchant_id']);

        return $data;
    }

    private function sandbox(): bool
    {
        return (bool) config('payments.zarinpal.sandbox');
    }

    private function base(): string
    {
        $url = $this->sandbox()
            ? (string) config('payments.zarinpal.sandbox_base_url')
            : (string) config('payments.zarinpal.base_url');

        return rtrim($url, '/').'/';
    }
}

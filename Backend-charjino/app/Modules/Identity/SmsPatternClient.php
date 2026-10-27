<?php

namespace App\Modules\Identity;

use App\Support\Phone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * الگوی پیامک SMS Web Service — SendTokenSingle.
 * کلید در آدرس لاگ نمی‌شود.
 */
final class SmsPatternClient
{
    public function enabled(): bool
    {
        if (app()->environment('testing') && ! config('charjino.sms.allow_in_tests')) {
            return false;
        }

        return trim((string) config('charjino.sms.api_key')) !== '';
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function sendOtp(string $phoneNumber, string $code): array
    {
        $template = trim((string) config('charjino.sms.otp_template'));
        if ($template === '') {
            return ['success' => false, 'error' => 'قالب پیامک تنظیم نشده است.'];
        }

        return $this->sendToken($phoneNumber, $template, [$code]);
    }

    /**
     * @param  list<string>  $parameters
     * @return array{success: bool, error?: string}
     */
    public function sendToken(string $phoneNumber, string $templateKey, array $parameters): array
    {
        $apiKey = trim((string) config('charjino.sms.api_key'));
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'کلید پیامک تنظیم نشده است.'];
        }

        $destination = $this->destination($phoneNumber);
        if ($destination === 0) {
            return ['success' => false, 'error' => 'شماره موبایل نامعتبر است.'];
        }

        $query = [
            'ApiKey' => $apiKey,
            'TemplateKey' => $templateKey,
            'Destination' => $destination,
            'P1' => (string) ($parameters[0] ?? ''),
            'P2' => (string) ($parameters[1] ?? ''),
            'P3' => (string) ($parameters[2] ?? ''),
        ];
        $base = rtrim((string) config('charjino.sms.base_url'), '/').'/';
        $url = $base.'SendTokenSingle?'.http_build_query($query);

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->get($url);
        } catch (\Throwable $e) {
            Log::error('sms.pattern_unreachable', [
                'template' => $templateKey,
                'phone' => Phone::mask($phoneNumber),
            ]);

            return ['success' => false, 'error' => 'سرویس پیامک در دسترس نیست.'];
        }

        $raw = (string) $response->body();
        $ipDenied = $this->ipDeniedMessage($raw);
        if ($ipDenied !== null) {
            Log::error('sms.pattern_rejected', [
                'template' => $templateKey,
                'phone' => Phone::mask($phoneNumber),
                'status' => $response->status(),
                'error' => $ipDenied,
            ]);

            return ['success' => false, 'error' => $ipDenied];
        }

        $body = $response->json();
        if ($response->successful() && is_array($body) && $this->isSuccess($body)) {
            Log::info('sms.pattern_sent', [
                'template' => $templateKey,
                'phone' => Phone::mask($phoneNumber),
            ]);

            return ['success' => true];
        }

        $error = $this->apiError($body, $raw);
        Log::error('sms.pattern_rejected', [
            'template' => $templateKey,
            'phone' => Phone::mask($phoneNumber),
            'status' => $response->status(),
            'error' => $error,
        ]);

        return ['success' => false, 'error' => $error];
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function isSuccess(?array $body): bool
    {
        if ($body === null) {
            return false;
        }

        return filter_var($body['Success'] ?? $body['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function apiError(?array $body, string $raw): string
    {
        if (is_array($body)) {
            $error = trim((string) ($body['Error'] ?? $body['error'] ?? ''));
            $code = $body['ErrorCode'] ?? $body['errorCode'] ?? null;
            if ($error === '') {
                $error = 'ارسال پیامک رد شد.';
            }
            if ($code !== null && $code !== '') {
                $error .= ' (کد '.$code.')';
            }

            return $error;
        }

        $text = trim($raw);

        return $text !== '' ? mb_substr($text, 0, 240) : 'ارسال پیامک رد شد.';
    }

    private function ipDeniedMessage(string $raw): ?string
    {
        if (! str_contains($raw, 'IP address not allowed')) {
            return null;
        }
        $ip = '';
        if (preg_match('/Your Ip Is:\s*([0-9.]+)/i', $raw, $match) === 1) {
            $ip = $match[1];
        }

        $message = 'آی‌پی سرور در پنل پیامک مجاز نیست.';
        if ($ip !== '') {
            $message .= ' آی‌پی '.$ip.' را در بخش محدودیت آی‌پی پنل پیام‌رسان اضافه کنید.';
        }

        return $message;
    }

    private function destination(string $phoneNumber): int
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }
        if ($digits === '' || strlen($digits) < 10) {
            return 0;
        }

        return (int) $digits;
    }
}

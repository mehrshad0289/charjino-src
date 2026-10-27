<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;

final class FazerCardsError
{
    /**
     * @var array<string, array{0: int, 1: string}>
     */
    private const MAP = [
        'INVALID_API_KEY' => [401, 'کلید FazerCards نامعتبر است.'],
        'UNAUTHORIZED' => [401, 'کلید FazerCards نامعتبر است.'],
        'MISSING_FAZERCARDS_API_KEY' => [503, 'کلید FazerCards تنظیم نشده است.'],
        'SUPPLIER_NOT_CONFIGURED' => [503, 'کلید FazerCards تنظیم نشده است.'],
        'LIVE_ORDER_BLOCKED' => [403, 'سفارش زنده قفل است. FAZERCARDS_ALLOW_LIVE_ORDER باید true باشد.'],
        'LIVE_ORDER_LOCKED' => [403, 'سفارش زنده قفل است. FAZERCARDS_ALLOW_LIVE_ORDER باید true باشد.'],
        'LIVE_NOT_CONFIRMED' => [403, 'تأیید صریح برای درخواست پولی لازم است.'],
        'LIVE_ORDER_UNCONFIRMED' => [403, 'تأیید صریح برای درخواست پولی لازم است.'],
        'LIVE_BLOCKED_EMPTY_WALLET' => [400, 'موجودی کیف پول دلاری تأمین‌کننده کافی نیست.'],
        'LIVE_BLOCKED_INSUFFICIENT_WALLET' => [400, 'موجودی کیف پول دلاری تأمین‌کننده کافی نیست.'],
        'INSUFFICIENT_BALANCE' => [400, 'موجودی کیف پول دلاری تأمین‌کننده کافی نیست.'],
        'ID_VALIDATION_UNSUPPORTED' => [422, 'اعتبارسنجی شناسه برای این دسته در API موجود نیست. شناسه را در فروشگاه تأیید کن.'],
        'VALIDATION_UNSUPPORTED' => [422, 'اعتبارسنجی شناسه برای این دسته در API موجود نیست. شناسه را در فروشگاه تأیید کن.'],
        'PLAYER_NOT_FOUND' => [400, 'شناسهٔ بازیکن پیدا نشد.'],
        'UNKNOWN_ENDPOINT' => [404, 'این مسیر FazerCards در allowlist نیست.'],
        'PAID_BLOCKED_ON_FETCH' => [403, 'درخواست پولی باید از هلپر گیت‌شده عبور کند.'],
        'VALIDATE_USE_HELPER' => [403, 'اعتبارسنجی باید از هلپر نگاشت category_id عبور کند.'],
        'DUPLICATE_ORDER' => [409, 'این سفارش قبلاً ثبت شده است.'],
        'PRICE_INCREASED' => [400, 'قیمت این بسته تغییر کرده است.'],
        'PRICE_CHANGED' => [400, 'قیمت این بسته تغییر کرده است.'],
        'RATE_LIMIT_EXCEEDED' => [429, 'تعداد درخواست بیش از حد مجاز است.'],
        'ORDER_NOT_FOUND' => [404, 'سفارش تأمین‌کننده پیدا نشد.'],
        'OUT_OF_STOCK' => [400, 'این بسته فعلاً موجود نیست.'],
        'PRODUCT_UNAVAILABLE' => [409, 'این محصول در تأمین‌کننده در دسترس نیست.'],
        'FUNPAY_ADDON_REQUIRED' => [403, 'ادون FunPay روی این حساب فعال نیست.'],
        'SUBSCRIPTION_INACTIVE' => [403, 'اشتراک اصلی FazerCards فعال نیست.'],
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function throwFrom(array $payload, int $httpStatus, ?string $codeOverride = null, ?int $retryAfter = null): never
    {
        $code = $codeOverride ?: self::codeOf($payload);
        $raw = strtolower(self::errorText($payload));
        if ($code === '' || ! isset(self::MAP[$code])) {
            $code = self::fromHttp($httpStatus, $raw);
        }
        [$status, $message] = self::MAP[$code] ?? [
            $httpStatus >= 400 ? $httpStatus : 502,
            \App\Support\CustomerError::message($code !== '' ? $code : 'FAZER_FAILED'),
        ];
        if (preg_match('/^[A-Za-z]/', $message) === 1) {
            $message = \App\Support\CustomerError::message($code);
        }

        throw new ApiException($message, $status, $code !== '' ? $code : 'SUPPLIER_ERROR', $retryAfter);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function errorText(array $payload): string
    {
        $errorField = $payload['error'] ?? $payload['message'] ?? '';
        if (is_array($errorField)) {
            return (string) ($errorField['message'] ?? $errorField['code'] ?? '');
        }

        return (string) $errorField;
    }

    public static function codeOf(array $payload): string
    {
        $errorField = $payload['error'] ?? $payload['message'] ?? '';
        $nestedCode = '';
        if (is_array($errorField)) {
            $nestedCode = is_string($errorField['code'] ?? null) ? (string) $errorField['code'] : '';
        }
        $error = self::errorText($payload);
        if (stripos($error, 'funpay_integration') !== false) {
            return 'FUNPAY_ADDON_REQUIRED';
        }

        $code = $payload['code'] ?? $nestedCode;
        if (is_string($code) && $code !== '') {
            $normalized = strtoupper(trim($code));
            if ($normalized === 'VALIDATION_UNSUPPORTED') {
                return 'ID_VALIDATION_UNSUPPORTED';
            }

            return $normalized;
        }

        if (stripos($error, 'ID validation is not available') !== false) {
            return 'ID_VALIDATION_UNSUPPORTED';
        }

        return '';
    }

    private static function fromHttp(int $status, string $error): string
    {
        if ($status === 429) {
            return 'SUPPLIER_RATE_LIMITED';
        }
        if ($status === 401 || $status === 403) {
            return 'SUPPLIER_BLOCKED';
        }
        if ($status === 409) {
            return 'IDEMPOTENCY_CONFLICT';
        }
        if ($status === 404) {
            return 'PRODUCT_UNAVAILABLE';
        }
        if ($status === 422) {
            return 'ID_NOT_CONFIRMED';
        }
        if ($status >= 500) {
            return 'SUPPLIER_5XX';
        }
        if ($status === 400) {
            if (str_contains($error, 'stock')) {
                return 'OUT_OF_STOCK';
            }
            if (str_contains($error, 'amount') || str_contains($error, 'min') || str_contains($error, 'max')) {
                return 'AMOUNT_OUT_OF_RANGE';
            }
            if (str_contains($error, 'invite') || str_contains($error, 'url')) {
                return 'INVALID_INVITE_LINK';
            }
            if (str_contains($error, 'username')) {
                return 'TELEGRAM_USERNAME_INVALID';
            }
            if (str_contains($error, 'player') || str_contains($error, 'not found') || str_contains($error, 'invalid id')) {
                return 'PLAYER_NOT_FOUND';
            }
            if (str_contains($error, 'balance') || str_contains($error, 'insufficient')) {
                return 'INSUFFICIENT_BALANCE';
            }

            return 'SUPPLIER_REJECTED';
        }

        return 'FAZER_FAILED';
    }
}

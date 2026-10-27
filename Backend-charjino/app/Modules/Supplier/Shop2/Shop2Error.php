<?php

namespace App\Modules\Supplier\Shop2;

use App\Exceptions\ApiException;

final class Shop2Error
{
    /**
     * @var array<string, array{0: int, 1: string}>
     */
    private const MAP = [
        'INVALID_API_KEY' => [401, 'کلید تأمین‌کننده نامعتبر است. کلید را در پنل Shop2TopUp بررسی کن.'],
        'IP_NOT_ALLOWED' => [403, 'IP این سرور در allowlist تأمین‌کننده نیست.'],
        'MISSING_REQUIRED_FIELD' => [400, 'فیلدهای ورودی بازیکن ناقص است.'],
        'INVALID_UUID_FORMAT' => [400, 'شناسهٔ سفارش نامعتبر است.'],
        'PLAYER_NOT_FOUND' => [400, 'شناسهٔ بازیکن پیدا نشد.'],
        'PLAYER_CHECK_UNAVAILABLE' => [503, 'بررسی بازیکن الان در دسترس نیست. کمی بعد تلاش کن.'],
        'REGION_MISMATCH' => [400, 'منطقهٔ این بازیکن با این بسته هم‌خوان نیست.'],
        'INSUFFICIENT_BALANCE' => [400, 'موجودی کیف پول دلاری تأمین‌کننده کافی نیست.'],
        'OUT_OF_STOCK' => [400, 'این بسته فعلاً موجود نیست.'],
        'PRODUCT_UNAVAILABLE' => [409, 'این محصول در تأمین‌کننده در دسترس نیست.'],
        'DUPLICATE_ORDER' => [409, 'این سفارش قبلاً ثبت شده است.'],
        'PRICE_INCREASED' => [400, 'قیمت این بسته تغییر کرده است. صفحه را تازه کن و دوباره بخر.'],
        'PRICE_CHANGED' => [400, 'قیمت این بسته تغییر کرده است. صفحه را تازه کن و دوباره بخر.'],
        'RATE_LIMIT_EXCEEDED' => [429, 'تعداد درخواست بیش از حد مجاز است.'],
        'INTERNAL_ERROR' => [500, 'خطای داخلی تأمین‌کننده.'],
        'ORDER_NOT_FOUND' => [404, 'سفارش تأمین‌کننده پیدا نشد.'],
        'NO_PLAYER_VALIDATION_REQUIRED' => [400, 'این بسته نیاز به بررسی بازیکن ندارد.'],
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function throwFrom(array $payload, int $httpStatus): never
    {
        $code = self::codeOf($payload);
        [$status, $message] = self::MAP[$code] ?? [
            $httpStatus >= 400 ? $httpStatus : 502,
            (string) (data_get($payload, 'error.message') ?: data_get($payload, 'message') ?: 'خطای تأمین‌کننده'),
        ];

        throw new ApiException($message, $status, $code !== '' ? $code : 'SUPPLIER_ERROR');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function codeOf(array $payload): string
    {
        $code = $payload['error']['code'] ?? $payload['code'] ?? '';

        return is_string($code) ? strtoupper(trim($code)) : '';
    }
}

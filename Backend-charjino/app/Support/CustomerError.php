<?php

namespace App\Support;

final class CustomerError
{
    /**
     * Stable Persian copy for the shopper. Ops/supplier internals stay on error.code.
     */
    public static function message(?string $code, string $fallback = 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.'): string
    {
        $mapped = match ($code) {
            'PLAYER_NOT_FOUND' => 'شناسهٔ بازیکن پیدا نشد.',
            'REGION_MISMATCH' => 'منطقهٔ این بازیکن با این بسته هم‌خوان نیست.',
            'CODM_REGION_MISMATCH' => 'ریجن این اکانت کالاف با این محصول یکی نیست.',
            'PLAYER_ID_INVALID' => 'UID باید فقط عدد باشد (حدود ۲۰ رقم، زیر نیک‌نیم در Player Profile بازی).',
            'MISSING_REQUIRED_FIELD' => 'شناسهٔ بازیکن را کامل وارد کن.',
            'FORBIDDEN_BUYER_FIELD' => 'فقط شناسهٔ بازیکن لازم است. رمز بازی را وارد نکن.',
            'PLAYER_CHECK_UNAVAILABLE' => 'بررسی بازیکن الان در دسترس نیست. کمی بعد تلاش کن.',
            'ID_VALIDATION_UNSUPPORTED' => 'این بازی اعتبارسنجی خودکار ندارد. شناسه را دوباره بررسی کن.',
            'OUT_OF_STOCK', 'PRODUCT_UNAVAILABLE' => 'این بسته فعلاً موجود نیست.',
            'PRICE_INCREASED', 'PRICE_CHANGED' => 'قیمت این بسته تغییر کرده است. صفحه را تازه کن و دوباره بخر.',
            'INSUFFICIENT_BALANCE' => 'تحویل این سفارش با تأخیر مواجه شد. پشتیبانی پیگیری می‌کند.',
            'IP_NOT_ALLOWED', 'INVALID_API_KEY', 'SUPPLIER_NOT_CONFIGURED', 'SUPPLIER_UNREACHABLE',
            'LIVE_ORDER_LOCKED', 'LIVE_ORDER_UNCONFIRMED', 'INTERNAL_ERROR' => 'تحویل سفارش الان ممکن نیست. کمی بعد دوباره تلاش می‌کنیم.',
            'RATE_LIMIT_EXCEEDED' => 'تعداد درخواست بیش از حد مجاز است.',
            'IDEMPOTENCY_CONFLICT' => 'این کلید قبلاً برای سفارش دیگری استفاده شده است.',
            'ORDER_NOT_PAYABLE' => 'این سفارش قابل پرداخت نیست.',
            'PAYMENT_EXPIRED' => 'مهلت پرداخت این سفارش تمام شده است.',
            'PAYMENT_FAILED' => 'پرداخت ناموفق بود.',
            'PAYMENT_AMOUNT_MISMATCH' => 'پرداخت در حال بررسی است. با شمارهٔ سفارش به پشتیبانی پیام بده.',
            'PAYMENT_NOT_FOUND' => 'پرداخت پیدا نشد.',
            'PSP_UNAVAILABLE' => 'درگاه پرداخت در دسترس نیست.',
            'SHOP_PAYMENT_REQUIRED' => 'تحویل زنده فقط بعد از پرداخت در فروشگاه انجام می‌شود.',
            'SKU_UNAVAILABLE' => 'این بسته برای فروش فعال نیست.',
            'INVALID_QUANTITY' => 'تعداد این بسته مجاز نیست.',
            'AMOUNT_OUT_OF_RANGE' => 'مبلغ واردشده در بازهٔ مجاز نیست.',
            'AMOUNT_DECIMALS' => 'تعداد رقم اعشار این مبلغ مجاز نیست.',
            'INVALID_FORMAT' => 'قالب این فیلد درست نیست.',
            'ID_NOT_CONFIRMED' => 'شناسه تأیید نشد؛ دوباره بررسی کن یا با پشتیبانی تماس بگیر',
            'ID_ACK_REQUIRED' => 'نسخه و شناسه را تأیید کن.',
            'REGION_ACK_REQUIRED' => 'ریجن حساب استیم باید با همین محصول یکی باشد.',
            'STEAM_LOGIN_CANNOT_REFILL' => 'این لاگین استیم قابل شارژ نیست.',
            'SUPPLIER_BLOCKED' => 'اتصال به تأمین‌کننده ممکن نیست. پشتیبانی شارژینو پیگیری می‌کند.',
            'SUPPLIER_RATE_LIMITED' => 'تعداد درخواست بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',
            'SUPPLIER_TIMEOUT' => 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.',
            'SUPPLIER_5XX' => 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.',
            'SUPPLIER_REJECTED' => 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.',
            'INVALID_INVITE_LINK' => 'لینک دعوت استیم نامعتبر است.',
            'TELEGRAM_USERNAME_INVALID' => 'نام کاربری تلگرام نامعتبر است.',
            'TEMPORARILY_UNAVAILABLE' => 'این محصول فعلاً قابل خرید نیست.',
            'FAZER_FAILED' => 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.',
            'WALLET_INSUFFICIENT' => 'موجودی کیف پول شارژینو کافی نیست.',
            'COUPON_NOT_FOUND' => 'کد تخفیف معتبر نیست.',
            'COUPON_EXPIRED' => 'کد تخفیف منقضی شده است.',
            'COUPON_INACTIVE' => 'این کد تخفیف غیرفعال است.',
            'COUPON_USAGE_LIMIT' => 'سهمیهٔ استفاده از این کد تخفیف تمام شده است.',
            'COUPON_MIN_ORDER' => 'مبلغ خرید به حداقل این کد تخفیف نمی‌رسد.',
            'COUPON_INVALID' => 'این کد تخفیف برای این سفارش قابل استفاده نیست.',
            'ORDER_STATE_INVALID' => 'این وضعیت برای این عملیات مجاز نیست.',
            'WEBHOOK_INVALID_SIGNATURE' => 'امضای وب‌هوک نامعتبر است.',
            'WEBHOOK_NOT_CONFIGURED' => 'وب‌هوک تأمین‌کننده پیکربندی نشده است.',
            default => $fallback !== '' ? $fallback : 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.',
        };
        if ($mapped !== '' && preg_match('/^[A-Za-z]/', $mapped) === 1) {
            return 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.';
        }

        return $mapped;
    }
}

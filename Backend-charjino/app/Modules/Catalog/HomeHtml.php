<?php

namespace App\Modules\Catalog;

use App\Modules\Blog\BlogHtml;

final class HomeHtml
{
    public static function default(): string
    {
        return implode('', [
            '<p>شارژینو فروشگاه آنلاین محصولات دیجیتال و گیمینگ برای گیمرهای ایرانی است. اینجا می‌توانید <a href="/catalog/category/game-topup">خرید شارژ بازی</a>، <a href="/catalog/category/gift-card">خرید گیفت کارت</a> و <a href="/catalog/category/game-keys">کلید بازی</a> را با پرداخت ریالی انجام دهید. قیمت محصولات به تومان نمایش داده می‌شود و مبلغ نهایی سفارش پیش از پرداخت مشخص است.</p>',
            '<p>شارژ مستقیم بازی با Player ID یا User ID انجام می‌شود و برای ثبت سفارش نیازی به ارائه رمز اکانت ندارید. برای <a href="/catalog/category/game-topup">یوسی پابجی</a>، <a href="/catalog/category/call-of-duty-mobile">سی‌پی کالاف دیوتی</a> و <a href="/catalog/category/free-fire">الماس فری فایر</a> از شارژ بازی بخرید. <a href="/catalog/category/google-play">گیفت کارت گوگل پلی</a> و <a href="/catalog/category/steam-wallet">گیفت کارت استیم</a>/<a href="/catalog/category/playstation">پلی‌استیشن</a> هم از مسیر همان برند در فروشگاه است.</p>',
            '<p>پس از پرداخت موفق، سفارش طبق روش تحویل همان محصول پردازش می‌شود و می‌توانید وضعیت سفارش خود را از بخش <a href="/invoices">پیگیری سفارش</a> مشاهده کنید.</p>',
        ]);
    }

    public static function sanitize(?string $html): string
    {
        $clean = BlogHtml::sanitize((string) $html);
        if ($clean === '') {
            return self::default();
        }

        return $clean;
    }
}

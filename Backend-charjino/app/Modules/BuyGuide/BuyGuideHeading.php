<?php

namespace App\Modules\BuyGuide;

final class BuyGuideHeading
{
    public static function key(string $heading): ?string
    {
        $normalized = self::normalize($heading);
        foreach (self::aliases() as $key => $titles) {
            foreach ($titles as $title) {
                if ($normalized === $title || str_starts_with($normalized, $title)) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function isInternalSubheading(string $heading): bool
    {
        $normalized = self::normalize($heading);
        foreach (['سناریو', 'عناوین موجود', 'یادداشت ویراستار', 'نکتهٔ اختصاصی', 'فهرست خانواد', 'نگاشت url', 'جدول slug'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $heading): string
    {
        $text = strtr($heading, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $text = BuyGuideContentRules::cleanText($text);
        $text = preg_replace('/^\d+\.\s*/u', '', $text) ?? $text;

        return mb_strtolower($text);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function aliases(): array
    {
        return [
            'summary' => ['خلاصه', 'عنوان و خلاصه'],
            'before_buy' => ['قبل از خرید بدانید'],
            'fields' => ['اطلاعات لازم برای خرید', 'اطلاعات لازم'],
            'purchase_steps' => ['مراحل خرید در شارژینو', 'مراحل خرید'],
            'redeem' => ['مراحل استفاده/دریافت در بازی', 'مراحل استفاده/فعال‌سازی', 'مراحل فعال‌سازی', 'مراحل استفاده', 'فعال‌سازی'],
            'errors' => ['خطاهای رایج و راه‌حل', 'خطاهای رایج'],
            'faq' => ['سؤالات متداول', 'سوالات متداول', 'faq'],
            'videos' => ['ویدیو آموزشی', 'ویدیو'],
            'sources' => ['منابع'],
        ];
    }
}

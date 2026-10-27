<?php

namespace App\Modules\Catalog;

use App\Models\Setting;

final class HomeCopy
{
    /**
     * @return array{flashSaleTitle: string, flashSaleSubtitle: string, flashSaleEndsAt: string|null, popularTitle: string, popularSubtitle: string, introHtml: string}
     */
    public static function get(): array
    {
        return [
            'flashSaleTitle' => Setting::getValue('flash_sale_title', 'فروش ویژه') ?: 'فروش ویژه',
            'flashSaleSubtitle' => Setting::getValue('flash_sale_subtitle', 'همین حالا سفارش بده؛ موجودی محدود است.') ?: 'همین حالا سفارش بده؛ موجودی محدود است.',
            'flashSaleEndsAt' => Setting::getValue('flash_sale_ends_at'),
            'popularTitle' => Setting::getValue('popular_title', 'محبوب‌های امروز') ?: 'محبوب‌های امروز',
            'popularSubtitle' => Setting::getValue('popular_subtitle', 'چند محصول که همین حالا بیشترین تقاضا را دارند.') ?: 'چند محصول که همین حالا بیشترین تقاضا را دارند.',
            'introHtml' => HomeHtml::sanitize(Setting::getValue('home_intro_html')),
        ];
    }
}

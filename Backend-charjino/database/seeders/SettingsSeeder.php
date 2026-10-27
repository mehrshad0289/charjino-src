<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'margin_percent' => '5',
            'usd_toman_rate' => '0',
            'fx_mode' => 'auto',
            'usd_toman_source' => null,
            'usd_toman_updated_at' => null,
            'flash_sale_title' => 'فروش ویژه',
            'flash_sale_subtitle' => 'همین حالا سفارش بده؛ موجودی محدود است.',
            'flash_sale_ends_at' => null,
            'popular_title' => 'محبوب‌های امروز',
            'popular_subtitle' => 'چند محصول که همین حالا بیشترین تقاضا را دارند.',
        ];

        foreach ($defaults as $key => $value) {
            if (! Setting::query()->find($key)) {
                Setting::putValue($key, $value);
            }
        }
    }
}

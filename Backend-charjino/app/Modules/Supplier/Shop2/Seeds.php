<?php

namespace App\Modules\Supplier\Shop2;

final class Seeds
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function discovery(): array
    {
        return [
            'pubg' => [
                'key' => 'pubg',
                'label' => 'PUBG Mobile',
                'labelFa' => 'پابجی موبایل',
                'handle' => 'pubg-mobile',
                'bigCategoryId' => 1,
                'categoryId' => 2,
                'categoryName' => 'Direct Topup',
                'sampleItemId' => 12,
                'sampleName' => '10 UC',
                'requirements' => ['player_id'],
            ],
            'cod' => [
                'key' => 'cod',
                'label' => 'COD Mobile',
                'labelFa' => 'کالاف دیوتی موبایل',
                'handle' => 'cod-mobile',
                'bigCategoryId' => 260,
                'categoryId' => 467,
                'categoryName' => 'Direct Topup SG',
                'sampleItemId' => 1002,
                'sampleName' => '160 CP',
                'requirements' => ['player_id'],
                'regionWarningFa' => 'هشدار منطقه: توضیح دسته SG/MY است — قبل از فروش، region بازیکن ایران را چک کن.',
            ],
        ];
    }
}

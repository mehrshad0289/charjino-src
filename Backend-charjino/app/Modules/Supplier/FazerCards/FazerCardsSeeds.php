<?php

namespace App\Modules\Supplier\FazerCards;

final class FazerCardsSeeds
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function beachhead(): array
    {
        return [
            [
                'key' => 'pubg',
                'handle' => 'pubg-mobile',
                'titleFa' => 'پابجی موبایل',
                'publisher' => 'Tencent',
                'thumbnail' => '/image/catalog/pubg-mobile.svg',
                'categoryHint' => 'pubg_mobile_auto',
                'nameIncludes' => ['pubg'],
                'prefer' => '/auto/i',
                'offer' => '/60\s*uc/i',
                'skuCode' => 'uc-60',
                'skuName' => '۶۰ یوسی',
            ],
            [
                'key' => 'cod',
                'handle' => 'cod-mobile',
                'titleFa' => 'کال آو دیوتی موبایل',
                'publisher' => 'Activision',
                'thumbnail' => '/image/catalog/cod-mobile.svg',
                'categoryHint' => 'codm_activision_us',
                'nameIncludes' => ['call of duty', 'cod'],
                'prefer' => '/activision \(us\)|\(us\)/i',
                'offer' => '/88\s*cp|80\s*\+\s*8|80\+8/i',
                'skuCode' => 'cp-88',
                'skuName' => '۸۰+۸ CP',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function storefrontHint(string $categoryId): ?array
    {
        $id = trim($categoryId);
        foreach (self::beachhead() as $seed) {
            if ((string) ($seed['categoryHint'] ?? '') === $id) {
                return $seed;
            }
        }

        return null;
    }

    public static function isForbiddenStorefront(string $name): bool
    {
        return (bool) preg_match('/vpn|proxy|account|cash.?out|lottery|chance|\bbox\b/i', $name);
    }
}

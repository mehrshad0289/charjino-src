<?php

namespace Tests\Unit;

use App\Modules\Supplier\Shop2\SkuMap;
use PHPUnit\Framework\TestCase;

class SkuMapCheapestTest extends TestCase
{
    public function test_picks_cheapest_pubg_uc_and_cod_cp(): void
    {
        $ids = SkuMap::cheapestItemIds([
            [
                'game' => 'PUBG_MOBILE',
                'item_id' => 22,
                'unit_price_usd' => '445.851',
            ],
            [
                'game' => 'PUBG_MOBILE',
                'item_id' => 12,
                'unit_price_usd' => '0.312096',
            ],
            [
                'game' => 'COD_MOBILE',
                'item_id' => 1015,
                'unit_price_usd' => '58.86432',
            ],
            [
                'game' => 'COD_MOBILE',
                'item_id' => 1002,
                'unit_price_usd' => '1.962144',
            ],
        ]);

        $this->assertEqualsCanonicalizing([12, 1002], $ids);
        $this->assertEqualsCanonicalizing([2, 467], SkuMap::cheapestCategoryIds([
            [
                'game' => 'PUBG_MOBILE',
                'item_id' => 22,
                'category_id' => 2,
                'unit_price_usd' => '445.851',
            ],
            [
                'game' => 'PUBG_MOBILE',
                'item_id' => 12,
                'category_id' => 2,
                'unit_price_usd' => '0.312096',
            ],
            [
                'game' => 'COD_MOBILE',
                'item_id' => 1002,
                'category_id' => 467,
                'unit_price_usd' => '1.962144',
            ],
        ]));
    }
}

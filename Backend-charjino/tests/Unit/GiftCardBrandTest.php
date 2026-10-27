<?php

namespace Tests\Unit;

use App\Modules\Catalog\GiftCardBrand;
use PHPUnit\Framework\TestCase;

class GiftCardBrandTest extends TestCase
{
    public function test_splits_brand_and_region_from_fazer_id(): void
    {
        $this->assertSame(
            ['platform' => 'xbox', 'region' => 'US'],
            GiftCardBrand::fromRemote('xbox_us'),
        );
        $this->assertSame(
            ['platform' => 'xbox', 'region' => 'EU'],
            GiftCardBrand::fromRemote('giftcard:xbox_game_pass_eu'),
        );
        $this->assertSame(
            ['platform' => 'steam', 'region' => 'TR'],
            GiftCardBrand::fromRemote('steam_wallet_tr'),
        );
        $this->assertSame(
            ['platform' => 'itunes', 'region' => 'AE'],
            GiftCardBrand::fromRemote('app_store_itunes_ae'),
        );
        $this->assertSame(
            ['platform' => 'google-play', 'region' => 'US'],
            GiftCardBrand::fromRemote('google_play_us'),
        );
        $this->assertSame(
            ['platform' => 'playstation', 'region' => 'UK'],
            GiftCardBrand::fromRemote('playstation_uk'),
        );
        $this->assertSame(
            ['platform' => 'steam', 'region' => ''],
            GiftCardBrand::fromRemote('steam'),
        );
        $this->assertSame(
            ['platform' => 'zing-card', 'region' => 'VN'],
            GiftCardBrand::fromRemote('zing_card_vn'),
        );
        $this->assertSame(
            ['platform' => 'pubg-mobile', 'region' => ''],
            GiftCardBrand::fromRemote('pubg_mobile'),
        );
        $this->assertSame(
            ['platform' => 'xbox', 'region' => ''],
            GiftCardBrand::fromRemote('xbox_ir'),
        );
    }
}

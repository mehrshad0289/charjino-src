<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftQuotes;
use PHPUnit\Framework\TestCase;

class FazerCardsSteamGiftQuotesTest extends TestCase
{
    public function test_regions_become_one_sku_each(): void
    {
        $offers = FazerCardsSteamGiftQuotes::regionOffers([
            'ok' => true,
            'appid' => 570,
            'offers' => [
                [
                    'sub_id' => 123,
                    'name' => 'Dota 2',
                    'regions' => [
                        ['region' => 'US', 'price' => '4.99'],
                        ['region' => 'EU', 'price' => '5.49'],
                        ['region' => 'RU', 'price' => '0'],
                    ],
                ],
                ['sub_id' => 0, 'name' => 'Broken', 'regions' => [['region' => 'US', 'price' => '1']]],
            ],
        ]);

        $this->assertCount(2, $offers);
        $this->assertSame('123|US', $offers[0]['offer_id']);
        $this->assertSame('123|EU', $offers[1]['offer_id']);
        $this->assertSame('4.99', FazerCardsSteamGiftQuotes::usdForOffer([
            'offers' => [
                ['sub_id' => 123, 'name' => 'Dota 2', 'regions' => [['region' => 'US', 'price' => '4.99']]],
            ],
        ], '123|US'));
    }

    public function test_invite_url_accepts_steam_short_link(): void
    {
        $this->assertTrue(FazerCardsSteamGiftQuotes::inviteAllowed('https://s.team/p/abcd-efgh'));
        $this->assertTrue(FazerCardsSteamGiftQuotes::inviteAllowed('s.team/p/abcd-efgh'));
        $this->assertSame('https://s.team/p/abcd-efgh', FazerCardsSteamGiftQuotes::normalizeInviteUrl('s.team/p/abcd-efgh'));
        $this->assertFalse(FazerCardsSteamGiftQuotes::inviteAllowed('https://steamcommunity.com/id/x'));
        $this->assertFalse(FazerCardsSteamGiftQuotes::inviteAllowed(''));
    }
}

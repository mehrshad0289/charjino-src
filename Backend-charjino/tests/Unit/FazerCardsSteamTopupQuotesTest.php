<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsSteamTopupQuotes;
use PHPUnit\Framework\TestCase;

class FazerCardsSteamTopupQuotesTest extends TestCase
{
    public function test_wallet_is_a_single_free_amount_offer(): void
    {
        $offers = FazerCardsSteamTopupQuotes::offers([
            'ok' => true,
            'base' => 'USD',
            'rates' => [
                'USD' => 1,
                'RUB' => 80,
                'UAH' => 40,
                'KZT' => 400,
            ],
        ]);
        $this->assertCount(1, $offers);
        $this->assertSame(FazerCardsSteamTopupQuotes::FREE_OFFER_ID, $offers[0]['offer_id']);
        $this->assertSame('0.15', $offers[0]['price_usd']);
        $this->assertSame('12.50', FazerCardsSteamTopupQuotes::usdForAmount([
            'rates' => ['USD' => 1, 'RUB' => 80, 'UAH' => 40, 'KZT' => 400],
        ], 'RUB', '1000'));
        $this->assertSame('12.50', FazerCardsSteamTopupQuotes::usdForOffer([
            'rates' => ['USD' => 1, 'RUB' => 80, 'UAH' => 40, 'KZT' => 400],
        ], 'RUB|1000'));
        $this->assertFalse(FazerCardsSteamTopupQuotes::loginAllowed([
            'ok' => true,
            'can_refill' => false,
            'unverified' => false,
        ]));
        $this->assertTrue(FazerCardsSteamTopupQuotes::loginAllowed([
            'ok' => true,
            'can_refill' => false,
            'unverified' => true,
        ]));
        $this->assertTrue(FazerCardsSteamTopupQuotes::loginAllowed([
            'ok' => true,
            'can_refill' => true,
        ]));
    }
}

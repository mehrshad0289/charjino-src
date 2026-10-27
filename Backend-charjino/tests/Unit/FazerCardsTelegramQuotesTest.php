<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsTelegramQuotes;
use PHPUnit\Framework\TestCase;

class FazerCardsTelegramQuotesTest extends TestCase
{
    public function test_stars_quote_becomes_packs_inside_min_max(): void
    {
        $offers = FazerCardsTelegramQuotes::starOffers([
            'ok' => true,
            'kind' => 'telegram_stars',
            'price_per_star' => '0.0152625',
            'min_amount' => 50,
            'max_amount' => 10000,
        ]);
        $qty = array_map(fn (array $row) => (int) $row['quantity'], $offers);
        $this->assertContains(50, $qty);
        $this->assertContains(10000, $qty);
        $this->assertNotContains(25000, $qty);
        $this->assertSame('0.763125', $offers[0]['price_usd']);
        $this->assertSame('0.763125', FazerCardsTelegramQuotes::starUsd([
            'price_per_star' => '0.0152625',
            'min_amount' => 50,
            'max_amount' => 10000,
        ], 50));
        $this->assertFalse(FazerCardsTelegramQuotes::quantityAllowed(49));
        $this->assertFalse(FazerCardsTelegramQuotes::quantityAllowed(10001));
    }

    public function test_premium_reads_plans_not_items(): void
    {
        $offers = FazerCardsTelegramQuotes::premiumOffers([
            'ok' => true,
            'kind' => 'telegram_premium',
            'plans' => [
                ['months' => 3, 'price_usd' => '12.1999'],
                ['months' => 24, 'price_usd' => '99'],
            ],
        ]);
        $this->assertCount(1, $offers);
        $this->assertSame(3, $offers[0]['months']);
        $this->assertSame('12.1999', $offers[0]['price_usd']);
    }
}

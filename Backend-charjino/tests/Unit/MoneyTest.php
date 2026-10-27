<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_round_toman_uses_thousands(): void
    {
        $this->assertSame(0, Money::roundToman(0));
        $this->assertSame(1000, Money::roundToman(500));
        $this->assertSame(220000, Money::roundToman(220100));
        $this->assertSame(221000, Money::roundToman(220600));
        $this->assertSame(127000, Money::roundToman(127100));
        $this->assertSame(7359000, Money::roundToman(7358900));
    }

    public function test_credible_sale_rejects_ninety_percent_off(): void
    {
        $this->assertTrue(Money::isCredibleSale(220000, 257100));
        $this->assertTrue(Money::isCredibleSale(100000, 150000));
        $this->assertFalse(Money::isCredibleSale(12000, 127100));
        $this->assertFalse(Money::isCredibleSale(220000, 220000));
        $this->assertFalse(Money::isCredibleSale(0, 100000));
    }
}

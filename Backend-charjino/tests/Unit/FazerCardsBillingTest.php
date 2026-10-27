<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Modules\Supplier\FazerCards\FazerCardsBilling;
use PHPUnit\Framework\TestCase;

class FazerCardsBillingTest extends TestCase
{
    public function test_plan_is_case_insensitive_and_rejects_unknown(): void
    {
        $this->assertSame('bronze', FazerCardsBilling::plan('Bronze'));
        $this->assertSame('silver', FazerCardsBilling::plan('SILVER'));
        $this->assertSame('gold', FazerCardsBilling::plan('gold'));
        $this->expectException(ApiException::class);
        FazerCardsBilling::plan('platinum');
    }

    public function test_orders_page_is_one_based_and_limit_caps_at_100(): void
    {
        $this->assertSame(1, FazerCardsBilling::page(0));
        $this->assertSame(1, FazerCardsBilling::page(-3));
        $this->assertSame(2, FazerCardsBilling::page(2));
        $this->assertSame(50, FazerCardsBilling::limit(0));
        $this->assertSame(100, FazerCardsBilling::limit(200));
        $this->assertSame(25, FazerCardsBilling::limit(25));
    }

    public function test_auto_renew_flag_and_upgrade_rank(): void
    {
        $this->assertTrue(FazerCardsBilling::flag(true));
        $this->assertTrue(FazerCardsBilling::flag('true'));
        $this->assertFalse(FazerCardsBilling::flag('false'));
        $this->assertTrue(FazerCardsBilling::isUpgrade('bronze', 'gold'));
        $this->assertFalse(FazerCardsBilling::isUpgrade('gold', 'silver'));
        $this->assertFalse(FazerCardsBilling::isUpgrade('gold', 'gold'));
        $this->assertFalse(FazerCardsBilling::isUpgrade('', 'gold'));
    }
}

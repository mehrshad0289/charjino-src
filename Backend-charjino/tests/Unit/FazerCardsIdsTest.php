<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsIds;
use PHPUnit\Framework\TestCase;

class FazerCardsIdsTest extends TestCase
{
    public function test_topup_key_stays_raw_and_other_families_are_prefixed(): void
    {
        $this->assertSame('pubg_mobile_auto', FazerCardsIds::productKey(FazerCardsIds::TOPUP, 'pubg_mobile_auto'));
        $this->assertSame('giftcard:steam', FazerCardsIds::productKey(FazerCardsIds::GIFTCARD, 'steam'));
        $this->assertSame('steam', FazerCardsIds::rawCategory('giftcard:steam'));
        $this->assertSame('pubg_mobile_auto', FazerCardsIds::rawCategory('pubg_mobile_auto'));
        $this->assertSame(FazerCardsIds::GIFTCARD, FazerCardsIds::familyOf('giftcard', 'giftcard:steam'));
        $this->assertSame(FazerCardsIds::TOPUP, FazerCardsIds::familyOf(null, 'pubg_mobile_auto'));
    }
}

<?php

namespace Tests\Unit;

use App\Modules\Catalog\StorefrontCopy;
use PHPUnit\Framework\TestCase;

class StorefrontCopyTest extends TestCase
{
    public function test_generic_charge_group_uses_original_game_name(): void
    {
        $this->assertSame('PUBG Mobile', StorefrontCopy::groupTitle('شارژ بازی', 'PUBG Mobile'));
        $this->assertSame('شارژ بازی', StorefrontCopy::groupTitle('شارژ بازی', 'شارژ بازی'));
        $this->assertSame('Telegram', StorefrontCopy::groupTitle('Telegram', 'Telegram'));
    }

    public function test_direct_topup_product_uses_group_title(): void
    {
        $this->assertSame('Telegram', StorefrontCopy::productTitle('Direct Topup', 'Telegram'));
        $this->assertSame('Telegram', StorefrontCopy::productTitle('Direct Topup SG', 'Telegram'));
        $this->assertSame('Direct Topup LATAM', StorefrontCopy::productTitle('Direct Topup LATAM', 'Free Fire'));
        $this->assertSame('پابجی موبایل', StorefrontCopy::productTitle('پابجی موبایل', 'شارژ بازی'));
    }

    public function test_supplier_publisher_is_hidden(): void
    {
        $this->assertSame('', StorefrontCopy::publisher('Shop2TopUp'));
        $this->assertSame('', StorefrontCopy::publisher('FazerCards'));
        $this->assertSame('Tencent', StorefrontCopy::publisher('Tencent'));
    }
}

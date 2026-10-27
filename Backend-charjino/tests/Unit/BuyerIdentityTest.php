<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Modules\Catalog\BuyerIdentity;
use Tests\TestCase;

class BuyerIdentityTest extends TestCase
{
    public function test_telegram_is_user_and_pubg_is_player(): void
    {
        $telegram = new Product([
            'title' => 'Direct Topup',
            'handle' => 'direct-topup-1736',
            'category_name' => 'Telegram',
            'name_en' => 'Telegram',
        ]);
        $telegram->setRelation('catalogGroup', new CatalogGroup([
            'title' => 'Telegram',
            'name' => 'Telegram',
            'slug' => 'telegram-1103',
        ]));

        $pubg = new Product([
            'title' => 'پابجی موبایل',
            'handle' => 'pubg-mobile',
            'category_name' => 'شارژ بازی',
        ]);

        $this->assertSame(BuyerIdentity::USER, BuyerIdentity::kind($telegram));
        $this->assertSame(BuyerIdentity::PLAYER, BuyerIdentity::kind($pubg));
        $this->assertSame('@ali_reza', BuyerIdentity::displayName(BuyerIdentity::USER, 'ali_reza'));
        $this->assertSame('بررسی کاربر', BuyerIdentity::checkLabel(BuyerIdentity::USER));
        $this->assertSame('بررسی بازیکن', BuyerIdentity::checkLabel(BuyerIdentity::PLAYER));
    }
}

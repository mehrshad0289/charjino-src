<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsCatalogScopes;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use PHPUnit\Framework\TestCase;

class FazerCardsCatalogScopesTest extends TestCase
{
    public function test_public_list_matches_fazer_catalog_families(): void
    {
        $ids = array_column(FazerCardsCatalogScopes::publicList(), 'id');
        $this->assertSame(
            ['steam-topup', 'steam-gifts', 'telegram', 'gamekeys', 'funpay', 'manual', 'giftcards', 'topups'],
            $ids,
        );
        $telegram = FazerCardsCatalogScopes::resolve('telegram');
        $this->assertSame([FazerCardsIds::TELEGRAM_STARS, FazerCardsIds::TELEGRAM_PREMIUM], $telegram['families']);
        $this->assertTrue(FazerCardsCatalogScopes::isKnown('giftcards'));
        $this->assertFalse(FazerCardsCatalogScopes::isKnown('vpn'));
    }
}

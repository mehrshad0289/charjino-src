<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsMedia;
use Tests\TestCase;

class FazerCardsMediaTest extends TestCase
{
    public function test_relative_cover_uses_fazer_origin(): void
    {
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);

        $this->assertSame('https://api.fzr.cards/covers/a.png', FazerCardsMedia::absolute('/covers/a.png'));
        $this->assertSame('https://api.fzr.cards/covers/a.png', FazerCardsMedia::absolute('covers/a.png'));
        $this->assertSame('/image/catalog/steam.svg', FazerCardsMedia::absolute('/image/catalog/steam.svg'));
        $this->assertSame('/storage/banners/x.jpg', FazerCardsMedia::absolute('/storage/banners/x.jpg'));
        $this->assertSame('https://cdn.example/a.jpg', FazerCardsMedia::absolute('https://cdn.example/a.jpg'));
        $this->assertSame(
            'https://cdn.cloudflare.steamstatic.com/steam/apps/570/header.jpg',
            FazerCardsMedia::steamHeader('570'),
        );
        $this->assertTrue(FazerCardsMedia::isLegacySteamHeader(
            'https://cdn.cloudflare.steamstatic.com/steam/apps/3807690/header.jpg',
        ));
        $this->assertTrue(FazerCardsMedia::isHashedSteamHeader(
            'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/3807690/f35f5e8e75853b550aaf5eca93eaa8883225fceb/header.jpg?t=1',
        ));
        $this->assertSame(
            'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/1/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/header.jpg',
            FazerCardsMedia::iranSafeSteamUrl(
                'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/1/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/header.jpg',
            ),
        );
        $this->assertSame(
            'https://api.fzr.cards/covers/a.png',
            \App\Modules\Catalog\CatalogMedia::catalogSrc('/covers/a.png'),
        );
        $this->assertSame('', \App\Modules\Catalog\CatalogMedia::catalogSrc('/image/catalog/pubg-mobile.svg'));
    }
}

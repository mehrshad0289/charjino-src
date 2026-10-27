<?php

namespace Tests\Unit;

use App\Modules\Catalog\GameKeyPlatform;
use PHPUnit\Framework\TestCase;

class GameKeyPlatformTest extends TestCase
{
    public function test_canonical_aliases_and_fazer_row(): void
    {
        $this->assertSame('steam', GameKeyPlatform::canonical('Steam'));
        $this->assertSame('ea', GameKeyPlatform::canonical('origin'));
        $this->assertSame('xbox', GameKeyPlatform::canonical('Xbox Series X'));
        $this->assertSame('rockstar', GameKeyPlatform::canonical('Rockstar Games Launcher'));
        $this->assertNull(GameKeyPlatform::canonical(''));
        $this->assertSame('playstation', GameKeyPlatform::canonical('PlayStation 5'));
        $this->assertSame('playstation', GameKeyPlatform::canonical('PSN'));
        $this->assertSame('nintendo', GameKeyPlatform::canonical('Nintendo Switch'));
        $this->assertSame('epic', GameKeyPlatform::canonical('Epic Games Store'));
        $this->assertSame('ubisoft', GameKeyPlatform::canonical('Ubisoft Connect'));
        $this->assertSame('battlenet', GameKeyPlatform::canonical('Battle.net'));
        $this->assertSame('custom-launcher', GameKeyPlatform::canonical('Custom Launcher'));
        $this->assertSame('playstation', GameKeyPlatform::fromFazer(['platform' => 'PlayStation', 'appid' => 570]));
        $this->assertSame('steam', GameKeyPlatform::fromFazer(['appid' => 570]));
        $this->assertSame(['steam', 'epic'], GameKeyPlatform::mergeFacets(['Epic', 'Steam']));
        $this->assertSame('pc', GameKeyPlatform::storefront('Steam'));
        $this->assertSame('pc', GameKeyPlatform::storefront('EA App'));
        $this->assertSame('pc', GameKeyPlatform::storefront('Rockstar Games Launcher'));
        $this->assertSame('xbox', GameKeyPlatform::storefront('Xbox Series X'));
        $this->assertSame('playstation', GameKeyPlatform::storefront('PS5'));
        $this->assertSame('pc', GameKeyPlatform::canonical('PC'));
        $this->assertSame(['playstation', 'xbox', 'pc'], GameKeyPlatform::mergeStorefrontFacets(['EA', 'Xbox', 'Steam', 'PlayStation']));
    }
}

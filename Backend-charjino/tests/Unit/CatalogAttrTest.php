<?php

namespace Tests\Unit;

use App\Modules\Catalog\CatalogAttr;
use PHPUnit\Framework\TestCase;

class CatalogAttrTest extends TestCase
{
    public function test_iran_is_not_a_catalog_region(): void
    {
        $this->assertTrue(CatalogAttr::isIgnoredRegion('IR'));
        $this->assertTrue(CatalogAttr::isIgnoredRegion('Iran'));
        $this->assertTrue(CatalogAttr::isIgnoredRegion('ایران'));
        $this->assertNull(CatalogAttr::publicRegion('Iran'));
        $this->assertSame('Global', CatalogAttr::publicRegion('Global'));
        $this->assertSame(['EU', 'Global'], CatalogAttr::uniqueSorted(['Global', 'IR', 'iran', 'EU', 'Global'], true));
        $this->assertSame('steam', CatalogAttr::publicPlatform('Steam'));
        $this->assertSame('ea', CatalogAttr::publicPlatform('EA App'));
        $this->assertSame('xbox', CatalogAttr::publicPlatform('Xbox'));
        $this->assertSame('rockstar', CatalogAttr::publicPlatform('Rockstar Games'));
        $this->assertNull(CatalogAttr::publicPlatform('all'));
    }

    public function test_multiline_keeps_line_breaks(): void
    {
        $this->assertSame("خط یک\nخط دو", CatalogAttr::multiline("خط یک\r\nخط دو\n"));
        $this->assertNull(CatalogAttr::multiline("  \n  "));
    }

    public function test_order_qty_caps_at_one_hundred(): void
    {
        $this->assertSame(1, CatalogAttr::minOrderQty(0));
        $this->assertSame(5, CatalogAttr::minOrderQty(5));
        $this->assertSame(100, CatalogAttr::maxOrderQty(500, 1));
        $this->assertSame(8, CatalogAttr::maxOrderQty(8, 1));
        $this->assertSame(5, CatalogAttr::maxOrderQty(3, 5));
    }
}

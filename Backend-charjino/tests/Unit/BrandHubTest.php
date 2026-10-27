<?php

namespace Tests\Unit;

use App\Modules\Catalog\BrandHub;
use PHPUnit\Framework\TestCase;

class BrandHubTest extends TestCase
{
    public function test_known_hub_slugs(): void
    {
        $this->assertTrue(BrandHub::is('google-play'));
        $this->assertTrue(BrandHub::is('call-of-duty-mobile'));
        $this->assertFalse(BrandHub::is('missing-brand'));
        $this->assertContains('valorant', BrandHub::slugs());
    }

    public function test_nav_payload_has_indexable_seo(): void
    {
        $row = BrandHub::toNavApi('playstation', 3);
        $this->assertSame('playstation', $row['slug']);
        $this->assertSame('پلی استیشن', $row['title']);
        $this->assertStringContainsString('PlayStation', (string) $row['seoTitle']);
        $this->assertSame(3, $row['productsCount']);
    }
}

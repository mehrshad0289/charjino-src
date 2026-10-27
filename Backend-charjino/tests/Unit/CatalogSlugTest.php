<?php

namespace Tests\Unit;

use App\Modules\Catalog\CatalogSlug;
use PHPUnit\Framework\TestCase;

class CatalogSlugTest extends TestCase
{
    public function test_persian_seed_titles_become_stable_ascii_slugs(): void
    {
        $this->assertSame('game-topup', CatalogSlug::base('شارژ بازی'));
        $this->assertSame('gift-card', CatalogSlug::base('گیفت‌کارت'));
        $this->assertSame('mobile-games', CatalogSlug::base('Mobile Games'));
    }
}

<?php

namespace Tests\Unit;

use App\Modules\Catalog\CatalogMedia;
use PHPUnit\Framework\TestCase;

class CatalogMediaTest extends TestCase
{
    public function test_storage_absolute_url_becomes_path(): void
    {
        $this->assertSame(
            '/storage/catalog/cover.png',
            CatalogMedia::publicSrc('http://127.0.0.1:3000/storage/catalog/cover.png'),
        );
        $this->assertSame(
            '/storage/catalog/cover.png',
            CatalogMedia::publicSrc('https://api.charjino.ir/storage/catalog/cover.png'),
        );
    }

    public function test_remote_https_stays(): void
    {
        $this->assertSame(
            'https://cdn.example.com/pubg.png',
            CatalogMedia::publicSrc('https://cdn.example.com/pubg.png'),
        );
    }
}

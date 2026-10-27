<?php

namespace Tests\Unit;

use App\Modules\Catalog\CodmCatalog;
use PHPUnit\Framework\TestCase;

class CodmCatalogTest extends TestCase
{
    public function test_persian_labels_for_seven_categories(): void
    {
        $this->assertSame('Activision آمریکا', CodmCatalog::label('codm_activision_us'));
        $this->assertSame('Activision کانادا', CodmCatalog::label('codm_activision_ca'));
        $this->assertSame('Activision هند', CodmCatalog::label('codm_activision_in'));
        $this->assertSame('Activision قزاقستان', CodmCatalog::label('codm_activision_kz'));
        $this->assertSame('Activision عربستان', CodmCatalog::label('codm_activision_sa'));
        $this->assertSame('Garena سنگاپور/مالزی', CodmCatalog::label('codm_garena_sgmy'));
        $this->assertSame('Garena اندونزی', CodmCatalog::label('codm_garena_indonesia'));
        $this->assertCount(7, CodmCatalog::ids());
        $this->assertFalse(CodmCatalog::is('pubg_mobile'));
    }

    public function test_digits_only_strips_spaces_and_rejects_letters(): void
    {
        $this->assertSame('73871234', CodmCatalog::digitsOnly(' 7387 1234 '));
        $this->assertTrue(CodmCatalog::looksLikeId('73871234'));
        $this->assertFalse(CodmCatalog::looksLikeId(''));
        $this->assertFalse(CodmCatalog::looksLikeId('abc123'));
    }
}

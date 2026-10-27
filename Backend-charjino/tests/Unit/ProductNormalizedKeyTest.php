<?php

namespace Tests\Unit;

use App\Modules\Catalog\ProductNormalizedKey;
use PHPUnit\Framework\TestCase;

class ProductNormalizedKeyTest extends TestCase
{
    public function test_article_and_digit_hyphen_fingerprints_match(): void
    {
        $this->assertSame(
            ProductNormalizedKey::fingerprint('topup-division-resurgence'),
            ProductNormalizedKey::fingerprint('topup-the-division-resurgence'),
        );
        $this->assertSame(
            ProductNormalizedKey::fingerprint('gamekey-warhammer-40000-space-marine-2-global'),
            ProductNormalizedKey::fingerprint('gamekey-warhammer-40-000-space-marine-2-global'),
        );
        $this->assertSame('topupdivisionresurgence', ProductNormalizedKey::fingerprint('topup-the-division-resurgence'));
        $this->assertSame(
            'gamekeywarhammer40000spacemarine2global',
            ProductNormalizedKey::fingerprint('gamekey-warhammer-40-000-space-marine-2-global'),
        );
    }

    public function test_kind_and_region_are_part_of_identity(): void
    {
        $a = ProductNormalizedKey::fromHandle('topup-the-division-resurgence', 'topup', null);
        $b = ProductNormalizedKey::fromHandle('topup-division-resurgence', 'topup', null);
        $this->assertSame($a['unique'], $b['unique']);
        $this->assertSame('topup', $a['kind']);
        $this->assertSame('', $a['region']);

        $c = ProductNormalizedKey::fromHandle('gamekey-warhammer-40-000-space-marine-2-global', 'gamekey', 'Global');
        $d = ProductNormalizedKey::fromHandle('gamekey-warhammer-40000-space-marine-2-global', 'gamekey', null);
        $this->assertSame($c['kind'], $d['kind']);
        $this->assertSame('global', $c['region']);
        $this->assertSame('global', $d['region']);
        $this->assertSame($c['key'], $d['key']);
    }
}

<?php

namespace Tests\Unit;

use App\Modules\Fulfillment\FazerIdempotencyKey;
use Tests\TestCase;

class IdempotencyKeyTest extends TestCase
{
    public function test_format_length_and_uniqueness(): void
    {
        $a = FazerIdempotencyKey::make('CH261007ABC', 12, 1);
        $b = FazerIdempotencyKey::make('CH261007ABC', 12, 1);
        $c = FazerIdempotencyKey::make('CH261007ABC', 12, 2);

        $this->assertMatchesRegularExpression('/^cj-CH261007ABC-12-a1-[0-9a-f-]{36}$/i', $a);
        $this->assertLessThanOrEqual(255, strlen($a));
        $this->assertNotSame($a, $b);
        $this->assertStringContainsString('-a2-', $c);
    }
}

<?php

namespace Tests\Unit;

use App\Support\Utf8;
use PHPUnit\Framework\TestCase;

class Utf8Test extends TestCase
{
    public function test_repairs_truncated_trademark_bytes(): void
    {
        $broken = hex2bin('84a2');
        $this->assertNotFalse($broken);
        $this->assertFalse(mb_check_encoding($broken, 'UTF-8'));
        $fixed = Utf8::string($broken);
        $this->assertTrue(mb_check_encoding($fixed, 'UTF-8'));
        json_encode(['edition' => $fixed], JSON_THROW_ON_ERROR);
    }

    public function test_keeps_valid_trademark(): void
    {
        $this->assertSame('A Game About Digging A Hole™', Utf8::string('A Game About Digging A Hole™'));
        $this->assertSame('skate.', Utf8::string('skate.'));
    }
}

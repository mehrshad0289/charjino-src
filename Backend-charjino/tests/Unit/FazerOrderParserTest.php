<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerOrderParser;
use PHPUnit\Framework\TestCase;

class FazerOrderParserTest extends TestCase
{
    public function test_nested_snake_case(): void
    {
        $parsed = FazerOrderParser::parse([
            'order' => [
                'id' => 'ord-1',
                'status' => 'completed',
                'kind' => 'gift_card',
                'cards' => ['AAAA-BBBB'],
                'fail_reason' => null,
            ],
        ]);

        $this->assertSame('ord-1', $parsed['orderId']);
        $this->assertSame('completed', $parsed['status']);
        $this->assertSame(['AAAA-BBBB'], $parsed['cards']);
    }

    public function test_flat_camel_case_keys_and_fail_reason(): void
    {
        $parsed = FazerOrderParser::parse([
            'orderId' => 'ord-2',
            'status' => 'failed',
            'keys' => ['KEY-1'],
            'failReason' => 'out of stock',
        ], 'fallback');

        $this->assertSame('ord-2', $parsed['orderId']);
        $this->assertSame(['KEY-1'], $parsed['keys']);
        $this->assertSame('out of stock', $parsed['failReason']);
    }
}

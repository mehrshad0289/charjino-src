<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsRedactor;
use PHPUnit\Framework\TestCase;

class FazerCardsRedactorTest extends TestCase
{
    public function test_masks_secret_fields_and_keeps_the_rest(): void
    {
        $out = FazerCardsRedactor::scrub([
            'ok' => true,
            'secret' => 'whsec_abcdef1234',
            'apiKey' => 'fp_live_zzzz',
            'password' => 'hunter2',
            'nested' => ['token' => 'abcd1234ef', 'email' => 'ops@example.com'],
        ]);

        $this->assertTrue($out['ok']);
        $this->assertSame('****1234', $out['secret']);
        $this->assertSame('****zzzz', $out['apiKey']);
        $this->assertSame('****ter2', $out['password']);
        $this->assertSame('****34ef', $out['nested']['token']);
        $this->assertSame('ops@example.com', $out['nested']['email']);
    }
}

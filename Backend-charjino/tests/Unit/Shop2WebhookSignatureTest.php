<?php

namespace Tests\Unit;

use App\Modules\Fulfillment\Shop2WebhookSignature;
use PHPUnit\Framework\TestCase;

class Shop2WebhookSignatureTest extends TestCase
{
    public function test_hmac_is_sha256_hex_over_raw_body(): void
    {
        $body = '{"event":"order.completed","order_id":"abc"}';
        $secret = 'test-webhook-secret';
        $expected = 'sha256='.hash_hmac('sha256', $body, $secret);

        $this->assertSame($expected, Shop2WebhookSignature::expected($body, $secret));
        $this->assertTrue(Shop2WebhookSignature::matches($body, $expected, $secret));
        $this->assertFalse(Shop2WebhookSignature::matches($body, $expected, 'other-secret'));
        $this->assertFalse(Shop2WebhookSignature::matches($body, 'sha256=deadbeef', $secret));
        $this->assertFalse(Shop2WebhookSignature::matches($body, $expected, ''));
    }
}

<?php

namespace Tests\Unit;

use App\Modules\Fulfillment\FazerCardsWebhookSignature;
use PHPUnit\Framework\TestCase;

class FazerCardsWebhookSignatureTest extends TestCase
{
    public function test_hmac_matches_sha256_prefix_and_raw_hex(): void
    {
        $body = '{"event":"webhook.test"}';
        $secret = 'fc-hook';
        $expected = FazerCardsWebhookSignature::expected($body, $secret);

        $this->assertTrue(FazerCardsWebhookSignature::matches($body, $expected, $secret));
        $this->assertTrue(FazerCardsWebhookSignature::matches($body, hash_hmac('sha256', $body, $secret), $secret));
        $this->assertFalse(FazerCardsWebhookSignature::matches($body, $expected, 'other'));
        $this->assertFalse(FazerCardsWebhookSignature::matches($body, $expected, ''));
    }
}

<?php

namespace Tests\Feature;

use App\Modules\Supplier\FazerCards\FazerCardsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FazerRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_retries_on_429_then_succeeds(): void
    {
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);
        Http::fake([
            'https://api.fzr.cards/api/v2/me' => Http::sequence()
                ->push(['ok' => false, 'code' => 'RATE_LIMIT_EXCEEDED'], 429, ['Retry-After' => '1'])
                ->push(['ok' => false, 'code' => 'RATE_LIMIT_EXCEEDED'], 429, ['Retry-After' => '1'])
                ->push(['ok' => true, 'email' => 'ops@example.com'], 200),
        ]);

        $result = $this->app->make(FazerCardsClient::class)->fetch('me');
        $this->assertSame(200, $result['status']);
        $this->assertSame('ops@example.com', $result['data']['email'] ?? null);
        Http::assertSentCount(3);
    }

    public function test_post_does_not_retry_on_429(): void
    {
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);
        Http::fake([
            'https://api.fzr.cards/api/v2/giftcards/order' => Http::response(
                ['ok' => false, 'code' => 'RATE_LIMIT_EXCEEDED'],
                429,
                ['Retry-After' => '8'],
            ),
        ]);

        try {
            $this->app->make(FazerCardsClient::class)->request(
                'giftcards/order',
                'POST',
                [],
                ['category_id' => 'steam', 'card_id' => 'steam-10', 'quantity' => 1],
                ['Idempotency-Key' => 'cj-test-key'],
            );
            $this->fail('expected rate limit');
        } catch (\App\Exceptions\ApiException $e) {
            $this->assertSame('RATE_LIMIT_EXCEEDED', $e->errorCode);
            $this->assertSame(8, $e->retryAfter);
        }
        Http::assertSent(fn (Request $request) => $request->method() === 'POST');
        Http::assertSentCount(1);
    }
}

<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Modules\Supplier\FazerCards\FazerCardsError;
use Tests\TestCase;

class FazerErrorMapTest extends TestCase
{
    public function test_latin_payload_never_reaches_customer(): void
    {
        foreach ([400, 401, 403, 409, 422, 429, 500, 503] as $status) {
            try {
                FazerCardsError::throwFrom(['error' => 'Internal supplier boom', 'message' => 'Rate limit exceeded'], $status);
                $this->fail('expected ApiException');
            } catch (ApiException $e) {
                $this->assertDoesNotMatchRegularExpression('/^[A-Za-z]/', $e->getMessage());
                $this->assertNotSame('Internal supplier boom', $e->getMessage());
            }
        }
    }
}

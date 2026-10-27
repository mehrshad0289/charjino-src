<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsAllowlist;
use App\Modules\Supplier\FazerCards\FazerCardsOpenApi;
use PHPUnit\Framework\TestCase;

class FazerCardsOpenApiCoverageTest extends TestCase
{
    public function test_every_openapi_operation_is_on_the_allowlist(): void
    {
        $orphans = [];
        foreach (FazerCardsOpenApi::operations() as [$method, $template]) {
            if (! FazerCardsAllowlist::allows($method, FazerCardsOpenApi::sampleParts($template))) {
                $orphans[] = $method.' /'.$template;
            }
        }

        $this->assertSame([], $orphans, 'OpenAPI orphans: '.implode(', ', $orphans));
        $this->assertCount(51, FazerCardsOpenApi::operations());
    }
}

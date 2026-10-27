<?php

namespace Tests\Unit;

use App\Modules\Catalog\CatalogMeiliHandleLookup;
use Meilisearch\Client;
use Mockery;
use Tests\TestCase;

class CatalogMeiliHandleLookupTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_database_driver_returns_null(): void
    {
        config(['smart-search.driver' => 'database']);
        $this->assertNull((new CatalogMeiliHandleLookup())->handles('pubg'));
    }

    public function test_short_query_returns_null(): void
    {
        config(['smart-search.driver' => 'failover']);
        $this->assertNull((new CatalogMeiliHandleLookup())->handles('پ'));
    }

    public function test_search_params_include_limit_and_optional_group_filter(): void
    {
        $plain = CatalogMeiliHandleLookup::searchParams('pubg');
        $this->assertSame(CatalogMeiliHandleLookup::LIMIT, $plain['limit']);
        $this->assertSame(['handle', 'id'], $plain['attributesToRetrieve']);
        $this->assertArrayNotHasKey('filter', $plain);

        $withGroup = CatalogMeiliHandleLookup::searchParams('xbox', 'gift-card');
        $this->assertSame('group_slug = "gift-card"', $withGroup['filter']);
    }

    public function test_handles_from_hits_dedupe_handle_and_id(): void
    {
        $this->assertSame(
            ['pubg-mobile', 'pubg-uc'],
            CatalogMeiliHandleLookup::handlesFromHits([
                ['handle' => 'pubg-mobile'],
                ['id' => 'pubg-uc'],
                ['handle' => 'pubg-mobile'],
            ]),
        );
    }

    public function test_meili_error_returns_null_for_like_fallback(): void
    {
        config(['smart-search.driver' => 'failover']);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('index')->andThrow(new \RuntimeException('down'));

        $this->assertNull((new CatalogMeiliHandleLookup($client))->handles('pubg'));
    }
}

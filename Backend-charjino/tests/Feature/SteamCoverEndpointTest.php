<?php

namespace Tests\Feature;

use App\Modules\Supplier\FazerCards\SteamCoverResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteamCoverEndpointTest extends TestCase
{
    public function test_steam_cover_returns_hashed_akamai_url(): void
    {
        Http::fake([
            'https://store.steampowered.com/api/appdetails*' => Http::response([
                '3807690' => [
                    'success' => true,
                    'data' => [
                        'header_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/3807690/f35f5e8e75853b550aaf5eca93eaa8883225fceb/header.jpg?t=1790082999',
                    ],
                ],
            ], 200),
        ]);

        $res = $this->getJson('/api/catalog/steam-cover/3807690');

        $res->assertOk();
        $this->assertSame(
            'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/3807690/f35f5e8e75853b550aaf5eca93eaa8883225fceb/header.jpg?t=1790082999',
            $res->json('data.url'),
        );
        $this->assertSame(
            'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/3807690/f35f5e8e75853b550aaf5eca93eaa8883225fceb/header.jpg?t=1790082999',
            $this->app->make(SteamCoverResolver::class)->url(3807690),
        );
    }

    public function test_steam_cover_404_when_store_misses(): void
    {
        Http::fake([
            'https://store.steampowered.com/api/appdetails*' => Http::response(['1' => ['success' => false]], 200),
        ]);

        $this->getJson('/api/catalog/steam-cover/1')->assertNotFound();
    }
}

<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsMedia;
use App\Modules\Supplier\FazerCards\SteamCoverResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteamCoverResolverTest extends TestCase
{
    public function test_missing_classic_header_uses_steam_store_image(): void
    {
        Http::fake([
            'https://store.steampowered.com/api/appdetails*' => Http::response([
                '3028330' => [
                    'success' => true,
                    'data' => [
                        'header_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/3028330/abcabcabcabcabcabcabcabcabcabcabcabcabca/header.jpg?t=1',
                    ],
                ],
            ], 200),
        ]);

        $url = $this->app->make(SteamCoverResolver::class)->url(3028330);

        $this->assertSame(
            'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/3028330/abcabcabcabcabcabcabcabcabcabcabcabcabca/header.jpg?t=1',
            $url,
        );
    }

    public function test_prefers_hashed_akamai_even_when_classic_would_exist(): void
    {
        Http::fake([
            'https://cdn.cloudflare.steamstatic.com/steam/apps/730/header.jpg' => Http::response('', 200),
            'https://store.steampowered.com/api/appdetails*' => Http::response([
                '730' => [
                    'success' => true,
                    'data' => [
                        'header_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/730/ffffffffffffffffffffffffffffffffffffffff/header.jpg?t=9',
                    ],
                ],
            ], 200),
        ]);

        $url = $this->app->make(SteamCoverResolver::class)->url(730);

        $this->assertSame(
            'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/730/ffffffffffffffffffffffffffffffffffffffff/header.jpg?t=9',
            $url,
        );
    }

    public function test_rewrites_cloudflare_header_image_to_akamai(): void
    {
        Http::fake([
            'https://store.steampowered.com/api/appdetails*' => Http::response([
                '3807690' => [
                    'success' => true,
                    'data' => [
                        'header_image' => 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/3807690/f35f5e8e75853b550aaf5eca93eaa8883225fceb/header.jpg?t=1790082999',
                    ],
                ],
            ], 200),
        ]);

        $url = $this->app->make(SteamCoverResolver::class)->url(3807690);

        $this->assertSame(
            'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/3807690/f35f5e8e75853b550aaf5eca93eaa8883225fceb/header.jpg?t=1790082999',
            $url,
        );
        $this->assertTrue(FazerCardsMedia::isHashedSteamHeader($url));
        $this->assertFalse(FazerCardsMedia::isCloudflareSteam($url));
    }

    public function test_empty_when_store_has_no_usable_header(): void
    {
        Http::fake([
            'https://store.steampowered.com/api/appdetails*' => Http::response([
                '1' => [
                    'success' => true,
                    'data' => [
                        'header_image' => 'https://cdn.cloudflare.steamstatic.com/steam/apps/1/header.jpg',
                    ],
                ],
            ], 200),
        ]);

        $this->assertSame('', $this->app->make(SteamCoverResolver::class)->url(1));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Modules\Catalog\FazerCardsCoverSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FazerCardsCoverSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['charjino.supplier.fazercards_key' => 'fc_test_secret_key_abcd']);
        config(['charjino.supplier.fazercards_base' => 'https://api.fzr.cards/api/v2']);
    }

    public function test_local_covers_steam_gift_and_telegram(): void
    {
        $gift = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $gift->fulfillment_type = 'steam-gift';
        $gift->fazercards_category_id = 'steam-gift:730';
        $gift->thumbnail = '/image/catalog/pubg-mobile.svg';
        $gift->image_url = null;
        $gift->save();

        $stars = Product::query()->create([
            'handle' => 'telegram-stars-test',
            'code' => 'telegram-stars-test',
            'title' => 'ستاره',
            'name_en' => 'Stars',
            'publisher' => '',
            'category_name' => 'تلگرام',
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fulfillment_type' => 'telegram-stars',
            'fazercards_category_id' => 'telegram-stars:stars',
            'visible' => true,
            'status' => 'active',
        ]);

        $result = $this->app->make(FazerCardsCoverSync::class)->applyLocal();

        $this->assertGreaterThanOrEqual(2, $result);
        $gift->refresh();
        $stars->refresh();
        $this->assertSame('https://cdn.cloudflare.steamstatic.com/steam/apps/730/header.jpg', $gift->thumbnail);
        $this->assertSame('/image/catalog/telegram.svg', $stars->thumbnail);
    }

    public function test_remote_covers_use_include_ui_imageurl(): void
    {
        Http::fake([
            'https://api.fzr.cards/api/v2/topups*' => Http::response([
                'ok' => true,
                'items' => [
                    ['category_id' => 'blood_strike', 'imageurl' => '/covers/blood.jpg'],
                ],
                'meta' => ['has_more' => false],
            ], 200),
            'https://api.fzr.cards/api/v2/giftcards*' => Http::response([
                'ok' => true,
                'items' => [
                    ['category_id' => 'steam', 'imageurl' => 'https://cdn.example/steam-card.jpg'],
                ],
                'meta' => ['has_more' => false],
            ], 200),
            'https://api.fzr.cards/api/v2/gamekeys*' => Http::response([
                'ok' => true,
                'items' => [
                    ['game_id' => 'elden', 'imageurl' => 'https://cdn.example/elden.jpg', 'appid' => 1245620],
                ],
                'meta' => ['has_more' => false],
            ], 200),
            'https://api.fzr.cards/api/v2/manual-services*' => Http::response([
                'ok' => true,
                'items' => [
                    ['id' => 'ms-boost', 'imageurl' => 'https://cdn.example/manual.jpg'],
                ],
            ], 200),
        ]);

        $blood = Product::query()->create([
            'handle' => 'blood-cover',
            'code' => 'blood-cover',
            'title' => 'Blood',
            'name_en' => 'Blood Strike',
            'publisher' => '',
            'category_name' => 'شارژ بازی',
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fulfillment_type' => 'topup',
            'fazercards_category_id' => 'blood_strike',
            'visible' => true,
            'status' => 'active',
        ]);
        $gift = Product::query()->create([
            'handle' => 'gift-cover',
            'code' => 'gift-cover',
            'title' => 'Steam card',
            'name_en' => 'Steam',
            'publisher' => '',
            'category_name' => 'گیفت کارت',
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fulfillment_type' => 'giftcard',
            'fazercards_category_id' => 'giftcard:steam',
            'visible' => true,
            'status' => 'active',
        ]);
        $key = Product::query()->create([
            'handle' => 'key-cover',
            'code' => 'key-cover',
            'title' => 'Elden',
            'name_en' => 'Elden Ring',
            'publisher' => '',
            'category_name' => 'کلید بازی',
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fulfillment_type' => 'gamekey',
            'fazercards_category_id' => 'gamekey:elden',
            'visible' => true,
            'status' => 'active',
        ]);
        $manual = Product::query()->create([
            'handle' => 'manual-cover',
            'code' => 'manual-cover',
            'title' => 'Boost',
            'name_en' => 'Boost',
            'publisher' => '',
            'category_name' => 'سرویس دستی',
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'fulfillment_type' => 'manual',
            'fazercards_category_id' => 'manual:ms-boost',
            'visible' => true,
            'status' => 'active',
        ]);

        $updated = $this->app->make(FazerCardsCoverSync::class)->applyRemote();
        $this->assertSame(4, $updated);

        $this->assertSame('https://api.fzr.cards/covers/blood.jpg', $blood->fresh()->thumbnail);
        $this->assertSame('https://cdn.example/steam-card.jpg', $gift->fresh()->thumbnail);
        $this->assertSame('https://cdn.example/elden.jpg', $key->fresh()->thumbnail);
        $this->assertSame(1245620, $key->fresh()->requirements['appid']);
        $this->assertSame('https://cdn.example/manual.jpg', $manual->fresh()->thumbnail);
    }
}

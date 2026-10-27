<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Product;
use App\Models\Setting;
use App\Modules\Catalog\StorefrontHomeCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontHomeCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_keeps_cached_payload_until_a_write_busts_it(): void
    {
        $this->seed();
        $first = $this->getJson('/api/catalog/home')->assertOk();
        $bannerCount = count($first->json('data.banners'));

        Banner::withoutEvents(function (): void {
            Banner::query()->create([
                'src' => '/storage/banners/ghost.jpg',
                'title' => 'بنر بدون ایونت',
                'href' => '/',
                'description' => 'تست کش',
                'visible' => true,
                'sort_order' => 50,
            ]);
        });

        $cached = $this->getJson('/api/catalog/home')->assertOk();
        $this->assertCount($bannerCount, $cached->json('data.banners'));

        $product = Product::query()->where('handle', 'pubg-mobile')->firstOrFail();
        $product->is_popular = true;
        $product->save();

        $fresh = $this->getJson('/api/catalog/home')->assertOk();
        $this->assertCount($bannerCount + 1, $fresh->json('data.banners'));
        $this->assertContains('pubg-mobile', collect($fresh->json('data.popular'))->pluck('slug')->all());
    }

    public function test_home_copy_setting_replaces_cached_home(): void
    {
        $this->seed();
        $this->getJson('/api/catalog/home')->assertOk();
        Setting::putValue('popular_title', 'محبوب‌های تست');
        $home = $this->getJson('/api/catalog/home')->assertOk();
        $this->assertSame('محبوب‌های تست', $home->json('data.popularTitle'));
    }

    public function test_defer_holds_stale_home_then_replaces_once(): void
    {
        $this->seed();
        $before = $this->getJson('/api/catalog/home')->assertOk();
        $count = count($before->json('data.banners'));

        StorefrontHomeCache::defer(function () use ($count): void {
            Banner::query()->create([
                'src' => '/storage/banners/deferred.jpg',
                'title' => 'بنر داخل بچ',
                'href' => '/',
                'description' => 'تست defer',
                'visible' => true,
                'sort_order' => 80,
            ]);
            $during = $this->getJson('/api/catalog/home')->assertOk();
            $this->assertCount($count, $during->json('data.banners'));
        });

        $after = $this->getJson('/api/catalog/home')->assertOk();
        $this->assertCount($count + 1, $after->json('data.banners'));
        $titles = collect($after->json('data.banners'))->pluck('title')->all();
        $this->assertContains('بنر داخل بچ', $titles);
    }
}

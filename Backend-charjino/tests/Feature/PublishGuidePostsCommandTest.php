<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PublishGuidePostsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_command_publishes_guides_and_lists_them_in_sitemap(): void
    {
        $this->assertSame(0, Artisan::call('charjino:publish-guide-posts'));
        $this->assertSame(11, BlogPost::query()->published()->count());
        $this->assertSame(0, Artisan::call('charjino:publish-guide-posts'));
        $this->assertSame(11, BlogPost::query()->published()->count());

        $slug = 'buy-pubg-uc';
        $this->getJson('/api/blog/posts/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.post.slug', $slug)
            ->assertJsonPath('data.post.author.name', 'شارژینو');
        $this->assertNotEmpty($this->getJson('/api/blog/posts/'.$slug)->json('data.post.faq'));
        $this->assertNotEmpty($this->getJson('/api/blog/posts/'.$slug)->json('data.post.publishedAt'));

        $sitemap = $this->getJson('/api/blog/sitemap')->assertOk();
        $slugs = collect($sitemap->json('data.posts'))->pluck('slug')->all();
        foreach ([
            'buy-pubg-uc',
            'buy-cod-cp',
            'buy-freefire-diamonds',
            'buy-telegram-premium',
            'steam-gift-redeem',
            'steam-wallet-vs-gift',
            'buy-clash-gems',
            'buy-8-ball-pool-coins',
            'buy-valorant-points',
            'game-topup-iran',
            'codm-region',
        ] as $expected) {
            $this->assertContains($expected, $slugs);
        }
        $this->assertContains('guides', collect($sitemap->json('data.categories'))->pluck('slug')->all());
        $this->getJson('/api/blog/posts/iran-game-topup-methods')
            ->assertStatus(301)
            ->assertJsonPath('data.to', '/blog/game-topup-iran');
    }
}

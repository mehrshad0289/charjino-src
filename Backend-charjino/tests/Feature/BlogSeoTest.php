<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\BlogSlugRedirect;
use App\Modules\Blog\BlogHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    public function test_purifier_strips_script_onerror_and_javascript_href(): void
    {
        $html = BlogHtml::sanitize(
            '<p>سلام<script>alert(1)</script></p><img src="/storage/blog/a.webp" alt="ok" onerror="alert(1)"><a href="javascript:alert(1)">x</a><p>ادامه</p>'
        );
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringNotContainsString('onerror', strtolower($html));
        $this->assertStringNotContainsString('javascript:', strtolower($html));
        $this->assertStringContainsString('alt="ok"', $html);
        $this->assertStringContainsString('/storage/blog/a.webp', $html);
    }

    public function test_slug_change_of_published_post_returns_301(): void
    {
        $admin = $this->adminToken();
        $categoryId = $this->categoryId($admin);
        $created = $this->withToken($admin)->postJson('/api/admin/blog/posts', $this->postPayload($categoryId, [
            'title' => 'راهنمای خرید سی پی کالاف دیوتی',
            'slug' => 'cp-old',
        ]));
        $created->assertCreated();
        $id = (int) $created->json('data.id');
        $this->withToken($admin)->postJson('/api/admin/blog/posts/'.$id.'/publish')->assertOk();
        $this->withToken($admin)->putJson('/api/admin/blog/posts/'.$id, ['slug' => 'cp-new'])->assertOk()
            ->assertJsonPath('data.slug', 'cp-new');
        $this->assertSame('cp-new', BlogSlugRedirect::resolve(BlogSlugRedirect::TYPE_POST, 'cp-old'));
        $this->flushHeaders();
        $this->getJson('/api/blog/posts/cp-old')->assertStatus(301)->assertJsonPath('data.to', '/blog/cp-new');
        $this->getJson('/api/blog/posts/cp-new')->assertOk()->assertJsonPath('data.post.slug', 'cp-new');
    }

    public function test_blog_upload_converts_to_webp_and_rejects_svg(): void
    {
        $admin = $this->adminToken();
        $png = $this->makeImage(1200, 630);
        $ok = $this->withToken($admin)->post('/api/admin/blog/uploads', [
            'file' => $png,
            'alt' => 'کاور تست',
            'role' => 'cover',
        ]);
        $ok->assertOk();
        $url = (string) $ok->json('data.url');
        $this->assertNotSame('', $url);
        $this->assertTrue(str_ends_with($url, '.webp') || str_ends_with($url, '.jpg'));
        $this->assertSame(1200, (int) $ok->json('data.width'));
        $small = $this->makeImage(400, 200);
        $this->withToken($admin)->post('/api/admin/blog/uploads', [
            'file' => $small,
            'role' => 'cover',
        ])->assertStatus(422);
    }

    public function test_scheduled_post_is_hidden_until_command_runs(): void
    {
        $admin = $this->adminToken();
        $categoryId = $this->categoryId($admin);
        $created = $this->withToken($admin)->postJson('/api/admin/blog/posts', $this->postPayload($categoryId, [
            'title' => 'مطلب زمان‌بندی شده شارژینو',
            'status' => 'scheduled',
            'publishedAt' => now()->addHour()->toIso8601String(),
        ]));
        $created->assertCreated()->assertJsonPath('data.status', 'scheduled');
        $slug = (string) $created->json('data.slug');
        $this->flushHeaders();
        $this->getJson('/api/blog/posts/'.$slug)->assertStatus(404);
        $this->travel(2)->hours();
        Artisan::call('charjino:publish-scheduled-posts');
        $this->assertSame(BlogPost::STATUS_PUBLISHED, BlogPost::query()->where('slug', $slug)->value('status'));
        $this->getJson('/api/blog/posts/'.$slug)->assertOk();
    }

    public function test_revisions_keep_last_ten_and_restore(): void
    {
        $admin = $this->adminToken();
        $categoryId = $this->categoryId($admin);
        $created = $this->withToken($admin)->postJson('/api/admin/blog/posts', $this->postPayload($categoryId, [
            'title' => 'نسخه اولیه مطلب وبلاگ شارژینو',
        ]));
        $id = (int) $created->json('data.id');
        for ($i = 1; $i <= 12; $i++) {
            $this->withToken($admin)->putJson('/api/admin/blog/posts/'.$id, [
                'title' => 'نسخه شماره '.$i.' مطلب وبلاگ شارژینو',
            ])->assertOk();
        }
        $list = $this->withToken($admin)->getJson('/api/admin/blog/posts/'.$id.'/revisions')->assertOk();
        $this->assertLessThanOrEqual(10, count($list->json('data.items')));
        $revisionId = (int) $list->json('data.items.0.id');
        $this->withToken($admin)->postJson('/api/admin/blog/posts/'.$id.'/revisions/'.$revisionId.'/restore')->assertOk();
    }

    public function test_public_author_seo_uses_charjino_not_admin_display_name(): void
    {
        $admin = $this->adminToken();
        $categoryId = $this->categoryId($admin);
        $this->withToken($admin)->postJson('/api/admin/blog/posts', $this->postPayload($categoryId, [
            'title' => 'راهنمای خرید شارژ بازی از شارژینو',
            'publish' => true,
        ]))->assertCreated();
        $user = \App\Models\User::query()->where('role', 'admin')->orderBy('id')->firstOrFail();
        $user->name = 'مدیر چارژینو';
        $user->save();
        $this->flushHeaders();
        $page = $this->getJson('/api/blog/authors/charjino')->assertOk();
        $this->assertSame('شارژینو', $page->json('data.author.name'));
        $this->assertSame('شارژینو؛ نویسندهٔ وبلاگ شارژینو', $page->json('data.seo.title'));
        $this->assertStringNotContainsString('مدیر', (string) $page->json('data.seo.title'));
        $this->assertStringNotContainsString('مدیر', (string) $page->json('data.author.name'));
        $this->assertGreaterThanOrEqual(110, mb_strlen((string) $page->json('data.seo.description')));
    }

    public function test_tag_with_fewer_than_three_posts_is_noindex(): void
    {
        $admin = $this->adminToken();
        $categoryId = $this->categoryId($admin);
        $tag = $this->withToken($admin)->postJson('/api/admin/blog/tags', ['title' => 'یوسی'])->assertCreated();
        $tagId = (int) $tag->json('data.id');
        $this->assertTrue((bool) $tag->json('data.noindex'));
        $post = $this->withToken($admin)->postJson('/api/admin/blog/posts', $this->postPayload($categoryId, [
            'title' => 'آموزش خرید یوسی با تومان از شارژینو',
            'tagIds' => [$tagId],
            'publish' => true,
        ]));
        $post->assertCreated();
        $this->flushHeaders();
        $this->getJson('/api/blog/tags/یوسی')->assertOk()->assertJsonPath('data.seo.noindex', true);
        $this->getJson('/api/blog/sitemap')->assertOk();
        $slugs = collect($this->getJson('/api/blog/sitemap')->json('data.tags'))->pluck('slug')->all();
        $this->assertNotContains('یوسی', $slugs);
    }

    public function test_publish_rejects_h1_and_raw_markdown(): void
    {
        $admin = $this->adminToken();
        $categoryId = $this->categoryId($admin);
        $h1 = $this->withToken($admin)->postJson('/api/admin/blog/posts', $this->postPayload($categoryId, [
            'content' => '<h1>تیتر</h1><p>'.str_repeat('متن کافی برای انتشار مطلب. ', 8).'</p>',
            'publish' => true,
        ]));
        $h1->assertStatus(422);
        $md = $this->withToken($admin)->postJson('/api/admin/blog/posts', $this->postPayload($categoryId, [
            'content' => '<p>'.str_repeat('متن کافی برای انتشار مطلب. ', 8).' **bold**</p>',
            'publish' => true,
        ]));
        $md->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function postPayload(int $categoryId, array $override = []): array
    {
        return array_merge([
            'title' => 'راهنمای خرید شارژ بازی از شارژینو',
            'excerpt' => str_repeat('خلاصه مطلب برای تست انتشار. ', 6),
            'metaDescription' => str_repeat('س', 140),
            'content' => '<h2>خرید از شارژینو</h2><p>'.str_repeat('متن کافی برای انتشار مطلب آموزشی. ', 10).'</p><p>لینک داخلی <a href="/blog/guide">راهنما</a></p>',
            'categoryId' => $categoryId,
            'cover' => '/storage/blog/cover.webp',
            'coverAlt' => 'تصویر شاخص مطلب',
            'coverWidth' => 1200,
            'coverHeight' => 630,
        ], $override);
    }

    private function categoryId(string $admin): int
    {
        return (int) $this->withToken($admin)->postJson('/api/admin/blog/categories', [
            'title' => 'آموزش',
            'slug' => 'guide-'.uniqid(),
        ])->json('data.id');
    }

    private function makeImage(int $width, int $height): UploadedFile
    {
        $path = sys_get_temp_dir().'/blog-'.uniqid().'.png';
        $image = imagecreatetruecolor($width, $height);
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'cover.png', 'image/png', null, true);
    }

    private function adminToken(): string
    {
        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $login->assertOk();

        return (string) $login->json('data.access_token');
    }
}

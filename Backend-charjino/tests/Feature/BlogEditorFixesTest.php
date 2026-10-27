<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Modules\Blog\BlogFaq;
use App\Modules\Blog\BlogSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BlogEditorFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    public function test_faq_persists_from_pipe_lines_and_array_and_appears_on_public_post(): void
    {
        $admin = $this->adminToken();
        $created = $this->withToken($admin)->postJson('/api/admin/blog/posts', [
            'title' => 'راهنمای خرید یوسی پابجی از شارژینو',
            'content' => '<h2>خرید یوسی</h2><p>'.str_repeat('متن کافی برای پیش‌نویس مطلب آموزشی. ', 6).'</p>',
            'faq' => "هزینه ارسال چقدر است؟|ارسال نداریم، تحویل دیجیتال است.\nپشتیبانی کجاست؟||از صفحه تماس پیام بده.",
            'autosave' => true,
        ]);
        $created->assertCreated();
        $this->assertCount(2, $created->json('data.faq'));
        $this->assertSame('هزینه ارسال چقدر است؟', $created->json('data.faq.0.question'));
        $id = (int) $created->json('data.id');
        $this->withToken($admin)->getJson('/api/admin/blog/posts/'.$id)
            ->assertOk()
            ->assertJsonPath('data.faq.1.answer', 'از صفحه تماس پیام بده.');

        $this->withToken($admin)->putJson('/api/admin/blog/posts/'.$id, [
            'faq' => [
                ['question' => 'آیا رمز اکانت می‌گیرید؟', 'answer' => 'خیر، فقط Player ID.'],
            ],
        ])->assertOk()->assertJsonPath('data.faq.0.question', 'آیا رمز اکانت می‌گیرید؟');

        $categoryId = $this->categoryId($admin);
        $this->withToken($admin)->putJson('/api/admin/blog/posts/'.$id, [
            'categoryId' => $categoryId,
            'cover' => '/storage/blog/cover.webp',
            'coverAlt' => 'خرید یوسی پابجی',
            'coverWidth' => 1200,
            'coverHeight' => 630,
            'metaDescription' => str_repeat('س', 140),
            'publish' => true,
        ])->assertOk();

        $slug = (string) BlogPost::query()->findOrFail($id)->slug;
        $this->flushHeaders();
        $this->getJson('/api/blog/posts/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.post.faq.0.question', 'آیا رمز اکانت می‌گیرید؟');
    }

    public function test_draft_can_be_saved_without_category_but_publish_requires_it(): void
    {
        $admin = $this->adminToken();
        $draft = $this->withToken($admin)->postJson('/api/admin/blog/posts', [
            'title' => 'پیش‌نویس بدون دسته شارژینو',
            'content' => '<p>'.str_repeat('متن پیش‌نویس برای ذخیره بدون دسته. ', 5).'</p>',
            'autosave' => true,
        ]);
        $draft->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertNull($draft->json('data.categoryId'));
        $id = (int) $draft->json('data.id');
        $this->withToken($admin)->postJson('/api/admin/blog/posts/'.$id.'/publish')->assertStatus(422);
        $this->withToken($admin)->putJson('/api/admin/blog/posts/'.$id, [
            'publish' => true,
        ])->assertStatus(422);
    }

    public function test_inactive_categories_are_listed_for_admin(): void
    {
        $admin = $this->adminToken();
        $this->withToken($admin)->postJson('/api/admin/blog/categories', [
            'title' => 'آموزش خرید',
            'slug' => 'buy-guide-cat',
            'isActive' => false,
        ])->assertCreated();
        $list = $this->withToken($admin)->getJson('/api/admin/blog/categories')->assertOk();
        $titles = collect($list->json('data.items'))->pluck('title')->all();
        $this->assertContains('آموزش خرید', $titles);
        $inactive = collect($list->json('data.items'))->firstWhere('title', 'آموزش خرید');
        $this->assertFalse((bool) $inactive['isActive']);
    }

    public function test_autosave_creates_draft_with_short_content_and_preview_token(): void
    {
        $admin = $this->adminToken();
        $created = $this->withToken($admin)->postJson('/api/admin/blog/posts', [
            'title' => 'عنوان کوتاه',
            'content' => '<p>شروع</p>',
            'autosave' => true,
        ]);
        $created->assertCreated()->assertJsonPath('data.status', 'draft');
        $token = (string) $created->json('data.previewToken');
        $this->assertNotSame('', $token);
        $this->flushHeaders();
        $this->getJson('/api/blog/preview/'.$token)
            ->assertOk()
            ->assertJsonPath('data.preview', true)
            ->assertJsonPath('data.post.title', 'عنوان کوتاه');
    }

    public function test_social_fields_and_secondary_keywords_round_trip(): void
    {
        $admin = $this->adminToken();
        $created = $this->withToken($admin)->postJson('/api/admin/blog/posts', [
            'title' => 'عنوان اجتماعی مطلب وبلاگ شارژینو',
            'content' => '<p>'.str_repeat('متن کافی برای ذخیره مطلب آموزشی. ', 5).'</p>',
            'ogTitle' => 'عنوان OG شارژینو',
            'ogDescription' => str_repeat('ت', 120),
            'twitterTitle' => 'عنوان توییتر',
            'secondaryKeywords' => 'یوسی, سی پی',
            'autosave' => true,
        ]);
        $created->assertCreated()
            ->assertJsonPath('data.ogTitle', 'عنوان OG شارژینو')
            ->assertJsonPath('data.twitterTitle', 'عنوان توییتر');
        $this->assertSame(['یوسی', 'سی پی'], $created->json('data.secondaryKeywords'));
    }

    public function test_unused_blog_images_older_than_a_day_are_pruned(): void
    {
        $keep = (string) Str::uuid();
        $drop = (string) Str::uuid();
        Storage::disk('public')->put('blog/2026/10/'.$keep.'.webp', 'keep');
        Storage::disk('public')->put('blog/2026/10/'.$keep.'-480.webp', 'keep-v');
        Storage::disk('public')->put('blog/2026/10/'.$drop.'.webp', 'drop');
        BlogPost::query()->create([
            'title' => 'مطلب با تصویر شاخص برای پاکسازی',
            'slug' => 'prune-keep',
            'content' => '<p>متن</p>',
            'cover' => '/storage/blog/2026/10/'.$keep.'.webp',
            'status' => BlogPost::STATUS_DRAFT,
        ]);
        $this->travel(25)->hours();
        Artisan::call('charjino:prune-blog-images');
        Storage::disk('public')->assertExists('blog/2026/10/'.$keep.'.webp');
        Storage::disk('public')->assertExists('blog/2026/10/'.$keep.'-480.webp');
        Storage::disk('public')->assertMissing('blog/2026/10/'.$drop.'.webp');
    }

    public function test_admin_forgot_password_resets_login(): void
    {
        Mail::fake();
        $this->postJson('/api/auth/admin/forgot-password', [
            'email' => 'admin@charjino.local',
        ])->assertOk();
        $token = (string) Cache::get('admin-reset:admin@charjino.local');
        $this->assertNotSame('', $token);
        $this->postJson('/api/auth/admin/reset-password', [
            'email' => 'admin@charjino.local',
            'token' => $token,
            'password' => 'new-admin-pass',
        ])->assertOk();
        $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ])->assertStatus(401);
        $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'new-admin-pass',
        ])->assertOk();
    }

    public function test_faq_parser_accepts_single_and_double_pipe(): void
    {
        $items = BlogFaq::parse("یک|دو\nسه||چهار");
        $this->assertSame([
            ['question' => 'یک', 'answer' => 'دو'],
            ['question' => 'سه', 'answer' => 'چهار'],
        ], $items);
    }

    public function test_latinize_slug_maps_persian_letters(): void
    {
        $this->assertSame('amvzsh-khryd', BlogSlug::latinize('آموزش خرید'));
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryId(string $admin): int
    {
        return (int) $this->withToken($admin)->postJson('/api/admin/blog/categories', [
            'title' => 'آموزش',
            'slug' => 'guide-'.uniqid(),
        ])->json('data.id');
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

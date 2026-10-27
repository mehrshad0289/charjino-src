<?php

namespace Tests\Feature;

use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_publishes_post_and_shop_can_read_search_comment_and_like(): void
    {
        $admin = $this->adminToken();
        $category = $this->withToken($admin)->postJson('/api/admin/blog/categories', [
            'title' => 'آموزش',
            'slug' => 'guide',
        ]);
        $category->assertCreated()->assertJsonPath('data.slug', 'guide');
        $categoryId = (int) $category->json('data.id');

        $draft = $this->withToken($admin)->postJson('/api/admin/blog/posts', [
            'title' => 'چطور یوسی پابجی بخریم',
            'excerpt' => 'راهنمای خرید یوسی پابجی در شارژینو بدون رمز اکانت و بدون نیاز به ورود به اکانت بازی برای گیمرهای ایرانی.',
            'metaDescription' => str_repeat('س', 140),
            'content' => '<p>'.str_repeat('خرید یوسی با تومان از شارژینو. ', 8).'</p>',
            'categoryId' => $categoryId,
            'cover' => '/storage/blog/cover.webp',
            'coverAlt' => 'خرید یوسی پابجی از شارژینو',
            'coverWidth' => 1200,
            'coverHeight' => 630,
        ]);
        $draft->assertCreated()->assertJsonPath('data.status', 'draft');
        $postId = (int) $draft->json('data.id');
        $slug = (string) $draft->json('data.slug');

        $this->flushHeaders();
        $this->getJson('/api/blog/posts/'.$slug)->assertStatus(404);

        $this->withToken($admin)->postJson('/api/admin/blog/posts/'.$postId.'/publish')->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->flushHeaders();
        $this->getJson('/api/blog')->assertOk()->assertJsonPath('data.latest.0.slug', $slug);
        $this->getJson('/api/blog/posts?q=یوسی')->assertOk()->assertJsonPath('data.items.0.slug', $slug);
        $this->getJson('/api/blog/categories/guide')->assertOk()->assertJsonPath('data.items.0.slug', $slug);
        $this->flushHeaders();
        $this->getJson('/api/blog/posts/'.$slug)->assertOk()->assertJsonPath('data.post.title', 'چطور یوسی پابجی بخریم');

        $guest = $this->postJson('/api/blog/posts/'.$slug.'/comments', ['body' => 'مطلب خوبی بود']);
        $guest->assertStatus(401);

        $token = $this->shopToken('09121112233');
        $comment = $this->withToken($token)->postJson('/api/blog/posts/'.$slug.'/comments', [
            'body' => 'مطلب خوبی بود ممنون',
        ]);
        $comment->assertCreated()->assertJsonPath('data.status', BlogComment::STATUS_PENDING);
        $commentId = (int) $comment->json('data.id');

        $this->withToken($token)->getJson('/api/blog/posts/'.$slug.'/comments')
            ->assertOk()
            ->assertJsonPath('data.items', []);
        $this->withToken($token)->postJson('/api/blog/comments/'.$commentId.'/like')->assertStatus(404);
        $this->withToken($token)->postJson('/api/blog/posts/'.$slug.'/comments', [
            'body' => 'پاسخ روی نظر در انتظار',
            'parentId' => $commentId,
        ])->assertStatus(422);

        $this->withToken($admin)->getJson('/api/admin/blog/comments?status=pending')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $commentId);
        $this->withToken($admin)->patchJson('/api/admin/blog/comments/'.$commentId, [
            'status' => BlogComment::STATUS_APPROVED,
        ])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame(1, (int) BlogPost::query()->find($postId)?->comments_count);

        $reply = $this->withToken($token)->postJson('/api/blog/posts/'.$slug.'/comments', [
            'body' => 'پاسخ روی نظر اصلی',
            'parentId' => $commentId,
        ]);
        $reply->assertCreated()->assertJsonPath('data.parentId', $commentId)->assertJsonPath('data.status', BlogComment::STATUS_PENDING);
        $replyId = (int) $reply->json('data.id');

        $this->withToken($token)->postJson('/api/blog/posts/'.$slug.'/like')->assertOk()->assertJsonPath('data.liked', true);
        $this->withToken($token)->postJson('/api/blog/comments/'.$commentId.'/like')->assertOk()->assertJsonPath('data.liked', true);

        $thread = $this->withToken($token)->getJson('/api/blog/posts/'.$slug.'/comments');
        $thread->assertOk()->assertJsonPath('data.items.0.liked', true);
        $this->assertSame([], $thread->json('data.items.0.replies'));

        $this->withToken($admin)->patchJson('/api/admin/blog/comments/'.$replyId, [
            'status' => BlogComment::STATUS_APPROVED,
        ])->assertOk();
        $this->assertNotEmpty($this->withToken($token)->getJson('/api/blog/posts/'.$slug.'/comments')->json('data.items.0.replies'));

        $this->withToken($admin)->patchJson('/api/admin/blog/comments/'.$commentId, [
            'status' => BlogComment::STATUS_HIDDEN,
        ])->assertOk()->assertJsonPath('data.status', 'hidden');

        $this->assertSame(BlogPost::STATUS_PUBLISHED, BlogPost::query()->find($postId)?->status);
    }

    public function test_draft_stays_out_of_public_hub(): void
    {
        $admin = $this->adminToken();
        $categoryId = (int) $this->withToken($admin)->postJson('/api/admin/blog/categories', [
            'title' => 'اخبار',
        ])->json('data.id');
        $this->withToken($admin)->postJson('/api/admin/blog/posts', [
            'title' => 'پیش‌نویس دیده نشود در فروشگاه',
            'content' => str_repeat('متن پیش‌نویس برای تست عمومی. ', 6),
            'categoryId' => $categoryId,
        ])->assertCreated();

        $hub = $this->getJson('/api/blog')->assertOk();
        $this->assertSame([], $hub->json('data.latest'));
    }

    private function shopToken(string $phone): string
    {
        $this->postJson('/api/auth/request-otp', ['phoneNumber' => $phone])->assertOk();
        $verify = $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => $phone,
            'code' => '123456',
        ]);
        $verify->assertOk();

        return (string) $verify->json('data.access_token');
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

<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\ProductComment;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_shop_can_comment_reply_like_and_admin_can_hide(): void
    {
        $product = $this->makeProduct('steam-wallet-comment', 'استیم والت');

        $this->flushHeaders();
        $guest = $this->postJson('/api/catalog/products/'.$product->handle.'/comments', [
            'body' => 'محصول خوبی بود ممنون',
        ]);
        $guest->assertStatus(401);

        $this->flushHeaders();
        $empty = $this->getJson('/api/catalog/products/'.$product->handle.'/comments');
        $empty->assertOk()->assertJsonPath('data.items', []);

        $token = $this->shopToken('09121112233');
        $comment = $this->withToken($token)->postJson('/api/catalog/products/'.$product->handle.'/comments', [
            'body' => 'محصول خوبی بود ممنون',
        ]);
        $comment->assertCreated()->assertJsonPath('data.status', ProductComment::STATUS_PENDING);
        $commentId = (int) $comment->json('data.id');

        $this->withToken($token)->getJson('/api/catalog/products/'.$product->handle.'/comments')
            ->assertOk()
            ->assertJsonPath('data.items', []);
        $this->withToken($token)->postJson('/api/catalog/comments/'.$commentId.'/like')->assertStatus(404);
        $this->withToken($token)->postJson('/api/catalog/products/'.$product->handle.'/comments', [
            'body' => 'پاسخ روی نظر در انتظار',
            'parentId' => $commentId,
        ])->assertStatus(422);
        $this->assertSame(0, (int) Product::query()->find($product->id)?->comments_count);

        $admin = $this->adminToken();
        $this->withToken($admin)->getJson('/api/admin/catalog/comments?status=pending')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $commentId);
        $this->withToken($admin)->patchJson('/api/admin/catalog/comments/'.$commentId, [
            'status' => ProductComment::STATUS_APPROVED,
        ])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame(1, (int) Product::query()->find($product->id)?->comments_count);

        $reply = $this->withToken($token)->postJson('/api/catalog/products/'.$product->handle.'/comments', [
            'body' => 'پاسخ روی نظر اصلی',
            'parentId' => $commentId,
        ]);
        $reply->assertCreated()->assertJsonPath('data.parentId', $commentId)->assertJsonPath('data.status', ProductComment::STATUS_PENDING);
        $replyId = (int) $reply->json('data.id');

        $nested = $this->withToken($token)->postJson('/api/catalog/products/'.$product->handle.'/comments', [
            'body' => 'پاسخ روی پاسخ نباید ثبت شود',
            'parentId' => $replyId,
        ]);
        $nested->assertStatus(422);

        $this->withToken($token)->postJson('/api/catalog/comments/'.$commentId.'/like')
            ->assertOk()
            ->assertJsonPath('data.liked', true);

        $thread = $this->withToken($token)->getJson('/api/catalog/products/'.$product->handle.'/comments');
        $thread->assertOk()->assertJsonPath('data.items.0.liked', true);
        $this->assertSame([], $thread->json('data.items.0.replies'));

        $this->withToken($admin)->patchJson('/api/admin/catalog/comments/'.$replyId, [
            'status' => ProductComment::STATUS_APPROVED,
        ])->assertOk();
        $this->assertNotEmpty($this->withToken($token)->getJson('/api/catalog/products/'.$product->handle.'/comments')->json('data.items.0.replies'));
        $this->assertSame(2, (int) Product::query()->find($product->id)?->comments_count);

        $this->withToken($admin)->patchJson('/api/admin/catalog/comments/'.$commentId, [
            'status' => ProductComment::STATUS_HIDDEN,
        ])->assertOk()->assertJsonPath('data.status', 'hidden');

        $this->flushHeaders();
        $hidden = $this->getJson('/api/catalog/products/'.$product->handle.'/comments');
        $hidden->assertOk()->assertJsonPath('data.items', []);
        $this->assertSame(1, (int) Product::query()->find($product->id)?->comments_count);
    }

    public function test_unknown_product_has_no_public_comments(): void
    {
        $this->flushHeaders();
        $this->getJson('/api/catalog/products/no-such-product/comments')->assertStatus(404);
    }

    private function makeProduct(string $handle, string $title): Product
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'comment-test-cards'],
            ['name' => 'Comment test', 'title' => 'تست نظر', 'visible' => true, 'sort_order' => 99],
        );

        return Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $title,
            'publisher' => '',
            'category_name' => $group->title,
            'subtitle' => '',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => FazerCardsIds::GIFTCARD,
            'inputs' => [],
            'sort_order' => 10,
        ]);
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

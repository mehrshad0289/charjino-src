<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_shop_user_can_set_display_name_and_see_it_on_me(): void
    {
        $token = $this->shopToken('09123456789');

        $this->withToken($token)
            ->patchJson('/api/auth/profile', ['name' => 'علی'])
            ->assertOk()
            ->assertJsonPath('data.name', 'علی')
            ->assertJsonPath('data.phoneNumber', '09123456789')
            ->assertJsonMissingPath('data.nationalId');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'علی')
            ->assertJsonPath('data.phoneNumber', '09123456789');
    }

    public function test_admin_cannot_patch_shop_profile(): void
    {
        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $login->assertOk();

        $this->withToken((string) $login->json('data.access_token'))
            ->patchJson('/api/auth/profile', ['name' => 'ادمین'])
            ->assertStatus(403);
    }

    public function test_owner_order_includes_player_id_and_other_user_cannot_see_it(): void
    {
        $owner = $this->shopToken('09123456789');
        $other = $this->shopToken('09120000000');
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();

        $create = $this->withToken($owner)->postJson('/api/orders', [
            'skuId' => $sku->id,
            'quantity' => 1,
            'requirements' => ['playerId' => '5123456789'],
        ], ['Idempotency-Key' => 'profile-order-player-id']);
        $create->assertOk()
            ->assertJsonPath('data.playerId', '5123456789')
            ->assertJsonMissingPath('data.requirements');

        $number = (string) $create->json('data.number');
        $this->withToken($owner)
            ->getJson('/api/orders/'.$number)
            ->assertOk()
            ->assertJsonPath('data.playerId', '5123456789');

        $this->withToken($other)
            ->getJson('/api/orders/'.$number)
            ->assertStatus(404);
    }

    public function test_tickets_are_owned_and_staff_can_answer(): void
    {
        $owner = $this->shopToken('09123456789');
        $other = $this->shopToken('09120000000');

        $created = $this->withToken($owner)->postJson('/api/tickets', [
            'subject' => 'سفارش نرسید',
            'body' => 'پرداخت کردم ولی شارژ نیامد.',
            'category' => 'delivery',
        ]);
        $created->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_OPEN)
            ->assertJsonPath('data.messages.0.sender', 'user');

        $id = (string) $created->json('data.id');
        $this->withToken($other)
            ->getJson('/api/tickets/'.$id)
            ->assertStatus(404);

        $this->withToken($other)
            ->postJson('/api/tickets', [
                'subject' => 'سفارش دیگران',
                'body' => 'می‌خواهم به سفارش دیگری وصل شوم.',
                'orderNumber' => Order::query()->value('number') ?? 'CH000000XXXXXX',
            ])
            ->assertStatus(422);

        $admin = $this->adminToken();
        $this->withToken($admin)
            ->postJson('/api/admin/tickets/'.$id.'/messages', ['body' => 'وضعیت را بررسی کردیم.'])
            ->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_ANSWERED)
            ->assertJsonPath('data.messages.1.sender', 'staff');

        $this->withToken($owner)
            ->getJson('/api/tickets/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_ANSWERED)
            ->assertJsonPath('data.messages.1.body', 'وضعیت را بررسی کردیم.');

        $this->withToken($owner)
            ->postJson('/api/tickets/'.$id.'/messages', ['body' => 'ممنون، هنوز نرسیده.'])
            ->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_OPEN);

        $this->withToken($admin)
            ->postJson('/api/admin/tickets/'.$id.'/status', ['status' => Ticket::STATUS_CLOSED])
            ->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_CLOSED);

        $this->withToken($owner)
            ->postJson('/api/tickets/'.$id.'/messages', ['body' => 'بازش کنید'])
            ->assertStatus(400);
    }

    public function test_favorites_add_list_and_remove(): void
    {
        $token = $this->shopToken('09123456789');
        $product = Product::query()->where('status', 'active')->where('visible', true)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/favorites', ['productId' => $product->id])
            ->assertOk()
            ->assertJsonPath('data.favorited', true)
            ->assertJsonPath('data.item.product.handle', $product->handle);

        $this->withToken($token)
            ->getJson('/api/favorites?productId='.$product->id)
            ->assertOk()
            ->assertJsonPath('data.favorited', true);

        $this->withToken($token)
            ->getJson('/api/favorites?idsOnly=1')
            ->assertOk()
            ->assertJsonPath('data.productIds.0', $product->id);

        $list = $this->withToken($token)->getJson('/api/favorites');
        $list->assertOk();
        $this->assertSame($product->id, $list->json('data.items.0.productId'));

        $this->withToken($token)
            ->deleteJson('/api/favorites/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.favorited', false);

        $this->withToken($token)
            ->getJson('/api/favorites?productId='.$product->id)
            ->assertOk()
            ->assertJsonPath('data.favorited', false);
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

<?php

namespace Tests\Feature;

use App\Models\CatalogGroup;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_is_ok(): void
    {
        $this->seed();

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.app', 'charjino-api')
            ->assertJsonPath('data.stack', 'laravel')
            ->assertJsonPath('data.language', 'fa')
            ->assertJsonPath('data.database', 'sqlite');
    }

    public function test_shop_otp_and_catalog_contract(): void
    {
        $this->seed();

        $otp = $this->postJson('/api/auth/request-otp', [
            'phoneNumber' => '09123456789',
        ]);
        $otp->assertOk()->assertJsonPath('success', true);

        $verify = $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => '09123456789',
            'code' => '123456',
        ]);
        $verify->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.phoneNumber', '09123456789');

        $this->assertNotEmpty($verify->json('data.access_token'));

        $home = $this->getJson('/api/catalog/home');
        $home->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'banners',
                    'flashSale',
                    'groups',
                    'popular',
                ],
            ]);

        $search = $this->getJson('/api/catalog/products?q=پابجی');
        $search->assertOk()->assertJsonPath('success', true);
        $this->assertIsArray($search->json('data'));

        $handle = $this->getJson('/api/catalog/products');
        $firstHandle = $handle->json('data.0.handle');
        if (is_string($firstHandle) && $firstHandle !== '') {
            $detail = $this->getJson('/api/catalog/products/'.$firstHandle);
            $detail->assertOk()
                ->assertJsonPath('data.handle', $firstHandle)
                ->assertJsonMissingPath('data.skus.0.costUsd')
                ->assertJsonMissingPath('data.skus.0.liveCostUsd');
            $this->assertIsArray($detail->json('data.inputFields'));
            $this->assertNotEmpty($detail->json('data.inputFields'));
            $this->assertArrayHasKey('name', $detail->json('data.inputFields.0'));
        }

        $groups = $home->json('data.groups');
        $this->assertIsArray($groups);
        $this->assertNotEmpty($groups);
        $this->assertArrayHasKey('id', $groups[0]);
        $this->assertNotEmpty($groups[0]['slug']);

        $slug = (string) $groups[0]['slug'];
        $category = $this->getJson('/api/catalog/categories/'.$slug);
        $category->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.category.slug', $slug)
            ->assertJsonStructure([
                'data' => [
                    'category' => ['id', 'slug', 'title', 'productsCount'],
                    'products',
                    'meta' => ['page', 'perPage', 'total', 'totalPages'],
                ],
            ]);
        $this->assertNotEmpty($category->json('data.products'));

        $list = $this->getJson('/api/catalog/categories');
        $list->assertOk();
        $slugs = array_column($list->json('data'), 'slug');
        $this->assertContains($slug, $slugs);

        $paged = $this->getJson('/api/catalog/products?category='.$slug.'&page=1&perPage=2');
        $paged->assertOk()
            ->assertJsonPath('data.meta.page', 1)
            ->assertJsonPath('data.meta.perPage', 2);
        $this->assertLessThanOrEqual(2, count($paged->json('data.items')));
    }

    public function test_hidden_category_is_not_public(): void
    {
        $this->seed();

        $group = CatalogGroup::query()->where('slug', 'game-topup')->firstOrFail();
        $group->update(['visible' => false]);

        $this->getJson('/api/catalog/categories/game-topup')
            ->assertNotFound()
            ->assertJsonPath('message', 'دسته پیدا نشد.');

        $listed = $this->getJson('/api/catalog/categories');
        $listed->assertOk();
        $this->assertNotContains('game-topup', array_column($listed->json('data'), 'slug'));

        $filtered = $this->getJson('/api/catalog/products?category=game-topup&page=1');
        $filtered->assertOk();
        $this->assertSame([], $filtered->json('data.items'));
    }

    public function test_admin_lists_and_pricing(): void
    {
        $this->seed();

        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $token = $login->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)
            ->putJson('/api/admin/settings', [
                'usdTomanRate' => 100000,
                'marginPercent' => 5,
                'fxMode' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('data.usdTomanRate', '100000')
            ->assertJsonPath('data.marginPercent', '5')
            ->assertJsonPath('data.sample.toman', 105000);

        $this->withToken($token)
            ->getJson('/api/admin/catalog/products')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)
            ->postJson('/api/admin/banners', [
                'src' => '/image/banners/banner-1.svg',
                'title' => 'اسلاید تست',
                'href' => '/catalog/pubg-mobile',
                'description' => 'لینک محصول',
            ])
            ->assertOk()
            ->assertJsonPath('data.mediaType', 'image');

        $sku = Sku::query()->first();
        $this->assertNotNull($sku);

        $this->withToken($token)
            ->putJson('/api/admin/home/copy', [
                'flashSaleTitle' => 'فروش ویژه تست',
                'flashSaleSubtitle' => 'موجودی محدود',
            ])
            ->assertOk()
            ->assertJsonPath('data.flashSaleTitle', 'فروش ویژه تست');

        $this->getJson('/api/catalog/home')
            ->assertOk()
            ->assertJsonPath('data.flashSaleTitle', 'فروش ویژه تست');
    }

    public function test_admin_login(): void
    {
        $this->seed();

        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@charjino.local')
            ->assertJsonPath('data.user.role', 'admin');
    }
}

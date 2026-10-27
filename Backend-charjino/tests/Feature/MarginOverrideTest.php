<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Sku;
use App\Modules\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarginOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Setting::putValue('usd_toman_rate', '100000');
        Setting::putValue('margin_percent', '5');
    }

    public function test_product_and_sku_margin_overrides_reprice_without_locking_toman(): void
    {
        $token = $this->adminToken();
        $a = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $b = Sku::query()->where('code', 'uc-325')->firstOrFail();
        foreach ([$a, $b] as $sku) {
            $sku->cost_usd = '1';
            $sku->live_cost_usd = '1';
            $sku->price_manual = false;
            $sku->save();
        }

        $this->withToken($token)
            ->putJson('/api/admin/catalog/products/'.$a->product_id, ['marginPercent' => 4])
            ->assertOk()
            ->assertJsonPath('data.marginPercent', '4')
            ->assertJsonPath('data.globalMarginPercent', '5');

        $a->refresh();
        $b->refresh();
        $this->assertSame(104000, (int) $a->price);
        $this->assertSame(104000, (int) $b->price);

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$a->id, ['marginPercent' => 7])
            ->assertOk()
            ->assertJsonPath('data.marginPercent', '7')
            ->assertJsonPath('data.effectiveMarginPercent', '7')
            ->assertJsonPath('data.marginSource', 'sku')
            ->assertJsonPath('data.priceManual', false)
            ->assertJsonPath('data.price', 107000);

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$b->id, ['marginPercent' => 3])
            ->assertOk()
            ->assertJsonPath('data.price', 103000)
            ->assertJsonPath('data.marginSource', 'sku');

        $this->app->make(PricingService::class)->repriceAllSkus();
        $this->assertSame(107000, (int) $a->fresh()->price);
        $this->assertSame(103000, (int) $b->fresh()->price);

        $this->withToken($token)
            ->putJson('/api/admin/catalog/products/'.$a->product_id, [
                'marginPercent' => 4,
                'clearSkuMargins' => true,
            ])
            ->assertOk();

        $this->assertSame(104000, (int) $a->fresh()->price);
        $this->assertSame(104000, (int) $b->fresh()->price);
        $this->assertNull($a->fresh()->margin_percent);
        $this->assertNull($b->fresh()->margin_percent);

        $this->withToken($token)
            ->putJson('/api/admin/catalog/products/'.$a->product_id, ['marginPercent' => null])
            ->assertOk()
            ->assertJsonPath('data.marginPercent', null);

        $this->assertSame(105000, (int) $a->fresh()->price);
        $this->assertSame(105000, (int) $b->fresh()->price);
    }

    public function test_sku_margin_unlocks_manual_price_and_rejects_out_of_range(): void
    {
        $token = $this->adminToken();
        $sku = Sku::query()->where('code', 'uc-60')->firstOrFail();
        $sku->cost_usd = '1';
        $sku->live_cost_usd = '1';
        $sku->save();

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, ['price' => 150000])
            ->assertOk()
            ->assertJsonPath('data.priceManual', true)
            ->assertJsonPath('data.price', 150000);

        $this->app->make(PricingService::class)->repriceAllSkus();
        $this->assertSame(150000, (int) $sku->fresh()->price);

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, ['marginPercent' => 7])
            ->assertOk()
            ->assertJsonPath('data.priceManual', false)
            ->assertJsonPath('data.price', 107000);

        $this->withToken($token)
            ->putJson('/api/admin/catalog/skus/'.$sku->id, ['marginPercent' => 101])
            ->assertStatus(422);
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

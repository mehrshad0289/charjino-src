<?php

namespace Tests\Unit;

use App\Modules\Catalog\CatalogExclusion;
use App\Modules\Catalog\PurchaseSchema;
use App\Modules\Catalog\RegionNote;
use App\Modules\Catalog\TopupFieldCatalog;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use PHPUnit\Framework\TestCase;

class CatalogExclusionTest extends TestCase
{
    public function test_structured_exclusion_reasons(): void
    {
        $this->assertSame(
            CatalogExclusion::MANUAL,
            CatalogExclusion::reason(FazerCardsIds::MANUAL, 'Boost', []),
        );
        $this->assertSame(
            CatalogExclusion::LOGIN_REQUIRED,
            CatalogExclusion::reason(FazerCardsIds::TOPUP, 'PUBG Via Login', []),
        );
        $this->assertSame(
            CatalogExclusion::LOGIN_REQUIRED,
            CatalogExclusion::reason(FazerCardsIds::TOPUP, 'UC Pack', [], ['category_id' => 'pubg_mobile_login']),
        );
        $this->assertSame(
            CatalogExclusion::CREDENTIAL_FIELD,
            CatalogExclusion::reason(FazerCardsIds::TOPUP, 'Valorant', [['key' => 'password']]),
        );
        $this->assertSame(
            CatalogExclusion::CREDENTIAL_FIELD,
            CatalogExclusion::reason(FazerCardsIds::TOPUP, 'Mail', [['key' => 'email']]),
        );
        $this->assertNull(CatalogExclusion::reason(FazerCardsIds::TOPUP, 'PUBG Mobile', [['key' => 'player_id']]));
        $this->assertNull(CatalogExclusion::reason(
            FazerCardsIds::STEAM_GIFT,
            'THE LAST LOGIN',
            [],
            ['category_id' => 'steam-gift:4773940', 'id' => '4773940', 'name' => 'THE LAST LOGIN'],
        ));
        $this->assertFalse(CatalogExclusion::isLoginProduct('THE LAST LOGIN', [
            'category_id' => 'steam-gift:4773940',
            'name' => 'THE LAST LOGIN',
        ]));
        $this->assertTrue(CatalogExclusion::isLoginProduct('MLBB (Login)', []));
        $this->assertTrue(CatalogExclusion::isLoginProduct('Genshin Login Required', []));
        $this->assertTrue(CatalogExclusion::isLoginProduct('UC Pack', ['category_id' => 'pubg_mobile_login']));
        $this->assertTrue(CatalogExclusion::isLoginProduct('Topup', ['delivery_type' => 'via_login']));
        $this->assertTrue(CatalogExclusion::shouldSkipIngest('VPN Proxy', []));
        $this->assertTrue(CatalogExclusion::shouldSkipIngest('FEMBOY FUTA BEACH HOUSE', [
            'category_id' => 'steam-gift:5041000',
            'name' => 'FEMBOY FUTA BEACH HOUSE',
        ]));
        $this->assertFalse(CatalogExclusion::shouldSkipIngest('PUBG Via Login', []));
        $this->assertFalse(CatalogExclusion::shouldSkipIngest('Counter-Strike 2', [
            'category_id' => 'steam-gift:730',
            'name' => 'Counter-Strike 2',
        ]));
        $this->assertFalse(CatalogExclusion::isCredentialKey('player_id'));
        $this->assertFalse(CatalogExclusion::isCredentialKey('riot_id'));
    }

    public function test_topup_field_catalog_keeps_fazer_keys_and_select_options(): void
    {
        $riot = TopupFieldCatalog::enrich(['key' => 'riot_id', 'label' => 'Riot ID']);
        $this->assertSame('riot_id', $riot['key']);
        $this->assertSame('^.{3,16}#[A-Za-z0-9]{2,5}$', $riot['pattern']);
        $this->assertNull(TopupFieldCatalog::enrich(['key' => 'password']));
        $server = TopupFieldCatalog::enrich([
            'key' => 'server',
            'options' => [
                ['label' => 'America', 'value' => 'america'],
                ['label' => 'Asia', 'value' => 'asia'],
                ['label' => 'Europe', 'value' => 'europe'],
                ['label' => 'TW/HK/MO', 'value' => 'tw_hk_mo'],
            ],
        ]);
        $this->assertSame('select', $server['type']);
        $this->assertSame('america', $server['options'][0]['value']);
    }

    public function test_purchase_schema_and_region_note(): void
    {
        $gift = PurchaseSchema::build(FazerCardsIds::GIFTCARD, [['name' => 'playerId']]);
        $this->assertSame([], $gift['fields']);
        $this->assertTrue($gift['quantity']['enabled']);
        $steam = PurchaseSchema::build(FazerCardsIds::STEAM_TOPUP);
        $this->assertSame('steamLogin', $steam['fields'][0]['key']);
        $this->assertTrue($steam['amount']['enabled']);
        $stars = PurchaseSchema::build(FazerCardsIds::TELEGRAM_STARS);
        $this->assertSame('telegram_username', $stars['fields'][0]['key']);
        $this->assertSame(
            'برای حساب‌های ریجن اندونزی و برزیل نیست. چند بسته برای MY/SG/PH/ID/RU در دسترس نیست.',
            RegionNote::fa('Region: Global', 'Mobile Legends Bang Bang'),
        );
        $this->assertSame('این محصول برای ریجن Vietnam است.', RegionNote::fa("Region: Vietnam\nWild Rift", 'Wild Rift VN'));
    }
}

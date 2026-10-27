<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsValidateId;
use PHPUnit\Framework\TestCase;

class FazerCardsValidateIdTest extends TestCase
{
    public function test_maps_pubg_auto_to_validate_category(): void
    {
        $supported = FazerCardsValidateId::SUPPORTED;

        $this->assertSame('pubg_mobile', FazerCardsValidateId::resolve('pubg_mobile_auto', $supported));
        $this->assertSame('pubg_mobile', FazerCardsValidateId::resolve('pubg_mobile_fast', $supported));
        $this->assertSame('free_fire', FazerCardsValidateId::resolve('free_fire_id', $supported));
        $this->assertSame('mobile_legends', FazerCardsValidateId::resolve('mobile_legends_global', $supported));
        $this->assertNull(FazerCardsValidateId::resolve('mobile_legends_adventure', $supported));
        $this->assertNull(FazerCardsValidateId::resolve('codm_activision_us', $supported));
    }

    public function test_mode_is_manual_for_cod(): void
    {
        $this->assertSame('manual', FazerCardsValidateId::mode('codm_activision_us'));
        $this->assertSame('manual', FazerCardsValidateId::mode(null));
        $this->assertFalse(FazerCardsValidateId::needsAck('codm_activision_us'));
        $this->assertNull(FazerCardsValidateId::resolve('pubg_mobile', []));
        $this->assertSame('pubg_mobile', FazerCardsValidateId::resolve('pubg_mobile', FazerCardsValidateId::SUPPORTED));
    }

    public function test_pick_fields_accepts_playerId_alias(): void
    {
        $fields = FazerCardsValidateId::pickFields(['player_id'], ['playerId' => '5123456789']);

        $this->assertSame(['player_id' => '5123456789'], $fields);
    }

    public function test_pick_fields_maps_uid_from_player_id(): void
    {
        $fields = FazerCardsValidateId::pickFields(['uid'], ['player_id' => '5123456789']);

        $this->assertSame(['uid' => '5123456789'], $fields);
    }
}

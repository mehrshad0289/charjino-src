<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Models\Product;
use App\Modules\Catalog\PurchaseSchema;
use App\Modules\Orders\PurchaseInputValidator;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Tests\TestCase;

class PurchaseInputValidatorTest extends TestCase
{
    public function test_table_keys_accept_values_that_look_like_secrets(): void
    {
        $cases = [
            'player_id' => 'user-12.3',
            'user_id' => 'abc_99',
            'uid' => 'uid-001',
            'server_id' => '1234',
            'bigo_id' => 'bigo.user',
            'role_id' => 'r1',
            'character_id' => 'char_2',
            'character_name' => 'Pink Cat',
            'account_id' => 'acc@1',
            'open_id' => 'open_1',
            'sid' => 's1',
            'imo_id' => 'imo-2',
            'support_id' => 'sup_3',
            'tiktok_user_id' => 'tt_user',
        ];
        foreach ($cases as $key => $value) {
            $product = $this->product(FazerCardsIds::TOPUP, PurchaseSchema::build(FazerCardsIds::TOPUP, [['key' => $key]]));
            $out = PurchaseInputValidator::validate($product, [$key => $value]);
            $this->assertSame($value, $out[$key], $key);
        }
    }

    public function test_riot_id_accepts_hash_tag_and_rejects_password_key(): void
    {
        $product = $this->product(FazerCardsIds::TOPUP, PurchaseSchema::build(FazerCardsIds::TOPUP, [
            ['key' => 'riot_id', 'required' => true],
        ]));
        $out = PurchaseInputValidator::validate($product, ['riot_id' => 'Spinner#EUW']);
        $this->assertSame('Spinner#EUW', $out['riot_id']);
        try {
            PurchaseInputValidator::validate($product, ['riot_id' => 'Spinner#EUW', 'password' => 'x']);
            $this->fail('password key');
        } catch (ApiException $e) {
            $this->assertSame('FORBIDDEN_BUYER_FIELD', $e->errorCode);
        }
        try {
            PurchaseInputValidator::validate($product, ['riot_id' => 'Spinner#EUW', 'email' => 'a@b.c']);
            $this->fail('email key');
        } catch (ApiException $e) {
            $this->assertSame('FORBIDDEN_BUYER_FIELD', $e->errorCode);
        }
    }

    public function test_telegram_strips_at_prefix(): void
    {
        $product = $this->product(FazerCardsIds::TELEGRAM_STARS, PurchaseSchema::build(FazerCardsIds::TELEGRAM_STARS));
        $out = PurchaseInputValidator::validate($product, ['telegram_username' => '@pinkcat']);
        $this->assertSame('pinkcat', $out['telegram_username']);
    }

    public function test_giftcard_rejects_any_buyer_field(): void
    {
        $product = $this->product(FazerCardsIds::GIFTCARD, PurchaseSchema::build(FazerCardsIds::GIFTCARD, [['name' => 'playerId']]));
        $this->assertSame([], PurchaseInputValidator::validate($product, []));
        try {
            PurchaseInputValidator::validate($product, ['player_id' => '1']);
            $this->fail('gift field');
        } catch (ApiException $e) {
            $this->assertSame('FORBIDDEN_BUYER_FIELD', $e->errorCode);
        }
    }

    public function test_invite_url_is_normalized(): void
    {
        $product = $this->product(FazerCardsIds::STEAM_GIFT, PurchaseSchema::build(FazerCardsIds::STEAM_GIFT));
        $out = PurchaseInputValidator::validate($product, ['invite_url' => 's.team/p/abcd-efgh']);
        $this->assertSame('https://s.team/p/abcd-efgh', $out['invite_url']);
    }

    public function test_select_options_must_match_value(): void
    {
        $product = $this->product(FazerCardsIds::TOPUP, PurchaseSchema::build(FazerCardsIds::TOPUP, [
            ['key' => 'player_id'],
            ['key' => 'server', 'options' => [
                ['label' => 'America', 'value' => 'america'],
                ['label' => 'Asia', 'value' => 'asia'],
            ]],
        ]));
        $out = PurchaseInputValidator::validate($product, ['player_id' => '12345678', 'server' => 'asia']);
        $this->assertSame('asia', $out['server']);
        try {
            PurchaseInputValidator::validate($product, ['player_id' => '12345678', 'server' => 'eu']);
            $this->fail('bad option');
        } catch (ApiException $e) {
            $this->assertSame('INVALID_FORMAT', $e->errorCode);
        }
    }

    private function product(string $family, array $schema): Product
    {
        $product = new Product;
        $product->fulfillment_type = $family;
        $product->fazercards_category_id = $family.':x';
        $product->purchase_schema = $schema;
        $product->inputs = [];
        $product->title = 'Test';
        $product->name_en = 'Test';

        return $product;
    }
}

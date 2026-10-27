<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Modules\Supplier\FazerCards\SteamTopupAmount;
use PHPUnit\Framework\TestCase;

class SteamTopupAmountTest extends TestCase
{
    public function test_usd_two_decimals_and_rub_converted_range(): void
    {
        $rates = ['rates' => ['USD' => 1, 'RUB' => 80, 'UAH' => 40, 'KZT' => 400]];
        $usd = SteamTopupAmount::validate('USD', '10.50', $rates);
        $this->assertSame('10.50', $usd['usd']);
        $rub = SteamTopupAmount::validate('RUB', '500.5', $rates);
        $this->assertSame('6.26', $rub['usd']);
        try {
            SteamTopupAmount::validate('USD', '10.555', $rates);
            $this->fail('decimals');
        } catch (ApiException $e) {
            $this->assertSame('AMOUNT_DECIMALS', $e->errorCode);
        }
        try {
            SteamTopupAmount::validate('USD', '0.01', $rates);
            $this->fail('range');
        } catch (ApiException $e) {
            $this->assertSame('AMOUNT_OUT_OF_RANGE', $e->errorCode);
        }
    }
}

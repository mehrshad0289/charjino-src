<?php

namespace Tests\Unit;

use App\Modules\Supplier\FazerCards\FazerCardsAllowlist;
use PHPUnit\Framework\TestCase;

class FazerCardsAllowlistTest extends TestCase
{
    public function test_allows_documented_reads_and_gated_writes(): void
    {
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['me']));
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['balance']));
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['subscription']));
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['subscription', 'plans']));
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['payments', 'methods']));
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['topups', 'validate-id']));
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['orders', 'abc']));
        $this->assertTrue(FazerCardsAllowlist::allows('POST', ['topups', 'order']));
        $this->assertTrue(FazerCardsAllowlist::allows('POST', ['payments', 'abc', 'verify']));
        $this->assertTrue(FazerCardsAllowlist::allows('PATCH', ['subscription', 'auto-renew']));
        $this->assertTrue(FazerCardsAllowlist::allows('PUT', ['account', 'webhook']));
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['funpay', 'me']));
        $this->assertTrue(FazerCardsAllowlist::allows('POST', ['funpay', 'login']));
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['manual-services', 'ms-1', 'offers']));
        $this->assertTrue(FazerCardsAllowlist::allows('GET', ['manual-services', 'orders', 'ord-1', 'chat']));
        $this->assertTrue(FazerCardsAllowlist::allows('POST', ['manual-services', 'orders', 'ord-1', 'chat']));
        $this->assertTrue(FazerCardsAllowlist::requiresConfirm('POST', 'funpay/login'));
        $this->assertTrue(FazerCardsAllowlist::requiresConfirm('POST', 'account/webhook/secret/regenerate'));
        $this->assertFalse(FazerCardsAllowlist::allows('GET', ['secret']));
        $this->assertFalse(FazerCardsAllowlist::allows('POST', ['me']));
        $this->assertTrue(FazerCardsAllowlist::isDangerousPost('funpay/login'));
        $this->assertFalse(FazerCardsAllowlist::isPaidPost('funpay/login'));
        $this->assertTrue(FazerCardsAllowlist::isPaidPost('topups/order'));
        $this->assertTrue(FazerCardsAllowlist::isInvoicePost('payments/create'));
    }
}

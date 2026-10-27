<?php

namespace Tests\Feature\Codm;

use App\Modules\Codm\CodmRegionCheck;
use App\Modules\Codm\CodmRegionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class CodmRegionCheckTest extends TestCase
{
    private const UID = '7387097367248044034';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'charjino.codm_region_check.enabled' => true,
            'charjino.codm_region_check.device_id' => '11111111-2222-4333-8444-555555555555',
            'charjino.codm_region_check.timeout' => 4,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function svc(): CodmRegionCheck
    {
        return $this->app->make(CodmRegionCheck::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function mismatchBody(string $home): array
    {
        return [
            'success' => false,
            'errorCode' => -200,
            'errorMsg' => 'The player’s game account country does not match the country of this product.',
            'homeBaseCountry2Name' => $home,
        ];
    }

    public function test_match_returns_nickname_and_short_id_and_sends_store_shaped_request(): void
    {
        Http::fake([
            'order-us.codashop.com/validate' => Http::response([
                'success' => true,
                'result' => ['type' => 'SUCCESS', 'nickname' => 'badboy.2013', 'shortId' => '2M2MXJ', 'countryId' => 840],
            ]),
        ]);

        $r = $this->svc()->check(' 7387 0973 6724 8044 034 ', 'us');

        $this->assertSame(CodmRegionResult::MATCH, $r->status);
        $this->assertSame('badboy.2013', $r->nickname);
        $this->assertSame('2M2MXJ', $r->shortId);
        $this->assertSame('US', $r->accountCountry);
        $this->assertSame('US', $r->suggestedVariant);

        Http::assertSent(function (Request $req) {
            return $req->url() === 'https://order-us.codashop.com/validate'
                && $req->method() === 'POST'
                && $req['country'] === 'US'
                && $req['voucherTypeName'] === 'CALL_OF_DUTY_MOBILE_WL'
                && $req['whiteLabelId'] === '1'
                && $req['deviceId'] === '11111111-2222-4333-8444-555555555555'
                && $req['userId'] === self::UID
                && $req['zoneId'] === ''
                && $req->hasHeader('Origin', 'https://store.callofdutymobile.com')
                && $req->hasHeader('Referer', 'https://store.callofdutymobile.com/en-us/codm/')
                && str_contains($req->header('Content-Type')[0] ?? '', 'application/json');
        });
    }

    public function test_match_shape_without_success_flag_as_seen_live(): void
    {
        Http::fake(['order-eu.codashop.com/validate' => Http::response(['result' => ['type' => 'SUCCESS', 'nickname' => 'kz_player', 'shortId' => 'ABC123', 'countryId' => 398]])]);

        $r = $this->svc()->check(self::UID, 'KZ');

        $this->assertTrue($r->isMatch());
        $this->assertSame('kz_player', $r->nickname);
    }

    public function test_mismatch_ir_has_no_suggestion(): void
    {
        Http::fake(['order-us.codashop.com/validate' => Http::response($this->mismatchBody('IR'))]);

        $r = $this->svc()->check(self::UID, 'US');

        $this->assertSame(CodmRegionResult::MISMATCH, $r->status);
        $this->assertSame('IR', $r->accountCountry);
        $this->assertNull($r->suggestedVariant);
        $this->assertNull($r->nickname);
    }

    public function test_mismatch_ca_suggests_ca_variant(): void
    {
        Http::fake(['order-sa.codashop.com/validate' => Http::response($this->mismatchBody('CA'))]);

        $r = $this->svc()->check(self::UID, 'IN');

        $this->assertTrue($r->isMismatch());
        $this->assertSame('CA', $r->accountCountry);
        $this->assertSame('CA', $r->suggestedVariant);
        $this->assertSame('CA', CodmRegionCheck::suggestVariant('ca'));
        $this->assertNull(CodmRegionCheck::suggestVariant('IR'));
    }

    public function test_timeout_is_unknown_and_not_cached(): void
    {
        $calls = 0;
        Http::fake(['order-sg.codashop.com/validate' => function () use (&$calls) {
            $calls++;
            throw new ConnectionException('cURL error 28: Operation timed out');
        }]);

        $r = $this->svc()->check(self::UID, 'SA');

        $this->assertTrue($r->isUnknown());
        $this->assertSame('timeout', $r->reason);
        $this->assertFalse(Cache::has($this->svc()->cacheKey(self::UID, 'SA')));
        $this->assertSame(1, $calls);
    }

    public function test_malformed_json_is_unknown(): void
    {
        Http::fake(['order-sg.codashop.com/validate' => Http::response('<html>Just a moment...</html>', 200, ['Content-Type' => 'text/html'])]);

        $r = $this->svc()->check(self::UID, 'CA');

        $this->assertTrue($r->isUnknown());
        $this->assertSame('malformed_json', $r->reason);
    }

    public function test_unexpected_shapes_and_5xx_are_unknown(): void
    {
        Http::fake([
            'order-us.codashop.com/validate' => Http::sequence()
                ->push(['success' => false, 'errorCode' => -100, 'errorMsg' => 'Invalid user'])
                ->push(['success' => true, 'result' => ['type' => 'SUCCESS']])
                ->push('oops', 503),
        ]);

        $this->assertSame('error_-100', $this->svc()->check(self::UID, 'US', true)->reason);
        $this->assertSame('success_without_nickname', $this->svc()->check(self::UID, 'US', true)->reason);
        $this->assertSame('http_503', $this->svc()->check(self::UID, 'US', true)->reason);
    }

    public function test_disabled_flag_returns_unknown_without_http(): void
    {
        config(['charjino.codm_region_check.enabled' => false]);
        Http::fake();

        $r = $this->svc()->check(self::UID, 'US');

        $this->assertTrue($r->isUnknown());
        $this->assertSame('disabled', $r->reason);
        Http::assertNothingSent();
    }

    public function test_cache_hit_skips_http_for_24h(): void
    {
        Http::fake(['order-us.codashop.com/validate' => Http::response($this->mismatchBody('IR'))]);

        $first = $this->svc()->check(self::UID, 'US');
        $second = $this->svc()->check('7387097367248044034', 'US');

        $this->assertFalse($first->fromCache);
        $this->assertTrue($second->fromCache);
        $this->assertSame('IR', $second->accountCountry);
        Http::assertSentCount(1);

        $this->travel(86401)->seconds();
        $this->svc()->check(self::UID, 'US');
        Http::assertSentCount(2);
    }

    public function test_cache_is_per_country(): void
    {
        Http::fake([
            'order-us.codashop.com/validate' => Http::response($this->mismatchBody('IR')),
            'order-eu.codashop.com/validate' => Http::response($this->mismatchBody('IR')),
        ]);

        $this->svc()->check(self::UID, 'US');
        $this->svc()->check(self::UID, 'KZ');

        Http::assertSentCount(2);
    }

    public function test_uid_sanitization_rejects_non_digits(): void
    {
        $this->assertSame(self::UID, CodmRegionCheck::sanitizeUid("7387 0973\t67248044034"));
        foreach (['', '   ', '7387-0973', 'abc123456', '۷۳۸۷۰۹۷۳۶۷', '12'] as $bad) {
            try {
                CodmRegionCheck::sanitizeUid($bad);
                $this->fail("accepted [{$bad}]");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_unsupported_variant_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->svc()->check(self::UID, 'IR');
    }

    public function test_variant_mapping_helpers(): void
    {
        $this->assertSame('US', CodmRegionCheck::variantForHandle('cod-mobile'));
        $this->assertSame('KZ', CodmRegionCheck::variantForHandle('topup-codm-activision-kz'));
        $this->assertSame('SA', CodmRegionCheck::variantForFazerCategory('codm_activision_sa'));
        $this->assertNull(CodmRegionCheck::variantForFazerCategory('codm_garena_sgmy'));
        $this->assertSame('https://order-sa.codashop.com/validate', $this->svc()->endpointFor('IN'));
        $this->assertSame('https://order-eu.codashop.com/validate', $this->svc()->endpointFor('KZ'));
    }
}

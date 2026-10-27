<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_dev_otp_skips_sms_and_accepts_zeros(): void
    {
        config([
            'app.env' => 'local',
            'charjino.otp.dev_code' => '0',
            'charjino.sms.api_key' => 'test-key',
            'charjino.sms.otp_template' => 'charjinonet',
            'charjino.sms.allow_in_tests' => true,
        ]);

        Http::fake([
            'https://api.sms-webservice.com/*' => Http::response(
                'آی پی درخواست دهنده در لیست آی پی های معتبر نیست - آدرس IP مجاز نیست. (کد 10)',
                403,
            ),
        ]);

        $this->postJson('/api/auth/request-otp', ['phoneNumber' => '09123456789'])
            ->assertOk()
            ->assertJsonPath('data.message', 'کد تأیید ارسال شد.');

        Http::assertNothingSent();

        $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => '09123456789',
            'code' => '000000',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_request_otp_sends_pattern_when_sms_is_enabled(): void
    {
        config([
            'charjino.sms.api_key' => 'test-key',
            'charjino.sms.otp_template' => 'charjinonet',
            'charjino.sms.allow_in_tests' => true,
            'charjino.otp.dev_code' => '654321',
        ]);

        Http::fake([
            'https://api.sms-webservice.com/*' => Http::response(['Success' => true], 200),
        ]);

        $this->postJson('/api/auth/request-otp', ['phoneNumber' => '09123456789'])
            ->assertOk()
            ->assertJsonPath('data.message', 'کد تأیید ارسال شد.');

        Http::assertSent(function ($request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), 'SendTokenSingle')
                && ($query['TemplateKey'] ?? '') === 'charjinonet'
                && ($query['Destination'] ?? '') === '9123456789'
                && ($query['P1'] ?? '') === '654321'
                && ($query['P2'] ?? '') === ''
                && ($query['P3'] ?? '') === ''
                && ($query['ApiKey'] ?? '') === 'test-key';
        });
    }

    public function test_request_otp_explains_sms_ip_block(): void
    {
        config([
            'charjino.sms.api_key' => 'test-key',
            'charjino.sms.otp_template' => 'charjinonet',
            'charjino.sms.allow_in_tests' => true,
            'charjino.otp.dev_code' => '654321',
        ]);

        Http::fake([
            'https://api.sms-webservice.com/*' => Http::response(
                'IP address not allowed.(Global).Your Ip Is: 194.62.43.211',
                403,
            ),
        ]);

        $this->postJson('/api/auth/request-otp', ['phoneNumber' => '09123456789'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'آی‌پی سرور در پنل پیامک مجاز نیست. آی‌پی 194.62.43.211 را در بخش محدودیت آی‌پی پنل پیام‌رسان اضافه کنید.');
    }

    public function test_request_otp_does_not_keep_code_when_pattern_is_rejected(): void
    {
        config([
            'charjino.sms.api_key' => 'test-key',
            'charjino.sms.otp_template' => 'charjinonet',
            'charjino.sms.allow_in_tests' => true,
            'charjino.otp.dev_code' => '654321',
        ]);

        Http::fake([
            'https://api.sms-webservice.com/*' => Http::response([
                'Success' => false,
                'Error' => 'قالب تایید نشده است',
            ], 200),
        ]);

        $this->postJson('/api/auth/request-otp', ['phoneNumber' => '09120000000'])
            ->assertStatus(503);

        $this->postJson('/api/auth/verify-otp', [
            'phoneNumber' => '09120000000',
            'code' => '654321',
        ])->assertStatus(401);
    }
}

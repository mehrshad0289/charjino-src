<?php

namespace App\Modules\Codm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Read-only CODM (Activision global) account-region check.
 *
 * Uses the same "validate profile" call the official store (store.callofdutymobile.com, run by Codashop)
 * makes before checkout. It never creates an order or payment.
 *
 * Validator behaviour observed live (2026-10-10):
 *  - same country    → {"result":{"type":"SUCCESS","nickname":"…","shortId":"…","countryId":<ISO numeric>,…}}
 *  - other country   → {"success":false,"errorCode":-200,"errorMsg":"…does not match…","homeBaseCountry2Name":"IR"}
 */
final class CodmRegionCheck
{
    /** Activision variants sold on Charjino (country => storefront product handle). */
    public const VARIANTS = [
        'US' => 'cod-mobile',
        'CA' => 'topup-codm-activision-ca',
        'IN' => 'topup-codm-activision-in',
        'KZ' => 'topup-codm-activision-kz',
        'SA' => 'topup-codm-activision-sa',
    ];

    /** FazerCards category per variant (for mapping SKU → variant). */
    public const FAZER_CATEGORIES = [
        'codm_activision_us' => 'US',
        'codm_activision_ca' => 'CA',
        'codm_activision_in' => 'IN',
        'codm_activision_kz' => 'KZ',
        'codm_activision_sa' => 'SA',
    ];

    /** @var array<string, string> */
    public const COUNTRY_FA = [
        'US' => 'آمریکا',
        'CA' => 'کانادا',
        'IN' => 'هند',
        'KZ' => 'قزاقستان',
        'SA' => 'عربستان',
        'IR' => 'ایران',
    ];

    /** Store locale path per variant (Origin/Referer the official store sends). */
    private const STORE_LOCALE = [
        'US' => 'en-us',
        'CA' => 'en-ca',
        'IN' => 'en-in',
        'KZ' => 'ru-kz',
        'SA' => 'en-sa',
    ];

    /** ISO 3166 numeric → alpha-2 for the countryId the validator echoes on success. */
    private const ISO_NUMERIC = [
        840 => 'US', 124 => 'CA', 356 => 'IN', 398 => 'KZ', 682 => 'SA', 364 => 'IR',
    ];

    private const MISMATCH_ERROR_CODE = -200;

    public function enabled(): bool
    {
        return (bool) config('charjino.codm_region_check.enabled', false);
    }

    /**
     * Digits only; whitespace is stripped. Anything else is rejected.
     *
     * @throws InvalidArgumentException
     */
    public static function sanitizeUid(string $uid): string
    {
        $clean = preg_replace('/\s+/u', '', $uid) ?? '';
        if ($clean === '' || ! preg_match('/^\d{5,25}$/', $clean)) {
            throw new InvalidArgumentException('CODM UID must contain digits only.');
        }

        return $clean;
    }

    public static function latinDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $lat = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace([...$fa, ...$ar], [...$lat, ...$lat], $value);
    }

    public static function isVariant(string $country): bool
    {
        return isset(self::VARIANTS[strtoupper(trim($country))]);
    }

    public static function variantForFazerCategory(?string $categoryId): ?string
    {
        return self::FAZER_CATEGORIES[strtolower(trim((string) $categoryId))] ?? null;
    }

    public static function variantForHandle(?string $handle): ?string
    {
        $country = array_search((string) $handle, self::VARIANTS, true);

        return $country === false ? null : $country;
    }

    public static function handleForVariant(?string $country): ?string
    {
        $c = strtoupper(trim((string) $country));

        return self::VARIANTS[$c] ?? null;
    }

    /**
     * The Charjino variant to suggest for an account country, or null if we don't sell that region.
     */
    public static function suggestVariant(?string $accountCountry): ?string
    {
        $c = strtoupper(trim((string) $accountCountry));

        return isset(self::VARIANTS[$c]) ? $c : null;
    }

    public static function countryFa(?string $country): string
    {
        $c = strtoupper(trim((string) $country));
        if ($c === '') {
            return '';
        }

        return self::COUNTRY_FA[$c] ?? $c;
    }

    public static function mismatchMessage(CodmRegionResult $result): string
    {
        $account = self::countryFa($result->accountCountry) ?: (string) $result->accountCountry;
        $variant = self::countryFa($result->variantCountry) ?: $result->variantCountry;
        if ($result->suggestedVariant) {
            $suggest = self::countryFa($result->suggestedVariant);

            return "این اکانت مربوط به ریجن {$account} است و این محصول برای ریجن {$variant} است. برای این اکانت از محصول «سی‌پی کالاف دیوتی موبایل — {$suggest}» استفاده کنید.";
        }

        return "این اکانت در ریجن {$account} ثبت شده و شارژ با شناسه (UID) برای این ریجن در شارژینو امکان‌پذیر نیست. لطفاً خرید نکنید؛ برای راهنمایی با پشتیبانی تماس بگیرید.";
    }

    /**
     * @throws InvalidArgumentException on a malformed UID or unsupported variant
     */
    public function check(string $uid, string $variantCountry, bool $fresh = false): CodmRegionResult
    {
        $uid = self::sanitizeUid($uid);
        $country = strtoupper(trim($variantCountry));
        if (! self::isVariant($country)) {
            throw new InvalidArgumentException("Unsupported CODM variant [{$variantCountry}].");
        }

        if (! $this->enabled()) {
            return CodmRegionResult::unknown($country, 'disabled');
        }

        $cacheKey = $this->cacheKey($uid, $country);
        if (! $fresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return CodmRegionResult::fromArray($cached, true);
            }
        }

        $result = $this->probe($uid, $country);

        if (! $result->isUnknown()) {
            Cache::put($cacheKey, $result->toArray(), (int) config('charjino.codm_region_check.cache_ttl', 86400));
        }

        Log::info('codm.region_check', [
            'variant' => $country,
            'status' => $result->status,
            'accountCountry' => $result->accountCountry,
            'reason' => $result->reason,
            'http' => $result->httpStatus,
        ]);

        return $result;
    }

    public function cacheKey(string $uid, string $country): string
    {
        return 'codm_region:'.strtoupper($country).':'.$uid;
    }

    public function endpointFor(string $country): ?string
    {
        $hosts = (array) config('charjino.codm_region_check.hosts', []);
        $region = $hosts[strtoupper($country)] ?? null;
        if (! is_string($region) || ! preg_match('/^[a-z]{2,4}$/', $region)) {
            return null;
        }

        return "https://order-{$region}.codashop.com/validate";
    }

    private function probe(string $uid, string $country): CodmRegionResult
    {
        $url = $this->endpointFor($country);
        if ($url === null) {
            return CodmRegionResult::unknown($country, 'host_not_configured');
        }
        $deviceId = (string) config('charjino.codm_region_check.device_id', '');
        if ($deviceId === '') {
            return CodmRegionResult::unknown($country, 'device_id_missing');
        }
        $timeout = max(1, (int) config('charjino.codm_region_check.timeout', 4));
        $storeBase = rtrim((string) config('charjino.codm_region_check.store_origin', 'https://store.callofdutymobile.com'), '/');
        $locale = self::STORE_LOCALE[$country];

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Origin' => $storeBase,
                    'Referer' => "{$storeBase}/{$locale}/codm/",
                    'User-Agent' => (string) config('charjino.codm_region_check.user_agent'),
                ])
                ->post($url, [
                    'country' => $country,
                    'voucherTypeName' => 'CALL_OF_DUTY_MOBILE_WL',
                    'whiteLabelId' => '1',
                    'deviceId' => $deviceId,
                    'userId' => $uid,
                    'zoneId' => '',
                ]);
        } catch (ConnectionException) {
            return CodmRegionResult::unknown($country, 'timeout');
        } catch (Throwable) {
            return CodmRegionResult::unknown($country, 'transport_error');
        }

        $status = $response->status();
        if (! $response->successful()) {
            return CodmRegionResult::unknown($country, 'http_'.$status, $status);
        }

        $body = json_decode($response->body(), true);
        if (! is_array($body)) {
            return CodmRegionResult::unknown($country, 'malformed_json', $status);
        }

        return $this->interpret($country, $body, $status);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function interpret(string $country, array $body, int $httpStatus): CodmRegionResult
    {
        $result = $body['result'] ?? null;
        $successFlag = ($body['success'] ?? null) === true
            || (is_array($result) && strtoupper((string) ($result['type'] ?? '')) === 'SUCCESS');

        if ($successFlag && is_array($result)) {
            $nickname = trim((string) ($result['nickname'] ?? ''));
            if ($nickname === '') {
                return CodmRegionResult::unknown($country, 'success_without_nickname', $httpStatus, $body);
            }
            $shortId = trim((string) ($result['shortId'] ?? '')) ?: null;
            $echoed = self::ISO_NUMERIC[(int) ($result['countryId'] ?? 0)] ?? null;
            if ($echoed !== null && $echoed !== $country) {
                return new CodmRegionResult(CodmRegionResult::MISMATCH, $country, $nickname, $shortId, $echoed, self::suggestVariant($echoed), 'country_echo', $httpStatus, $body);
            }

            return new CodmRegionResult(CodmRegionResult::MATCH, $country, $nickname, $shortId, $country, $country, null, $httpStatus, $body);
        }

        if (($body['success'] ?? null) === false && (int) ($body['errorCode'] ?? 0) === self::MISMATCH_ERROR_CODE) {
            $home = strtoupper(trim((string) ($body['homeBaseCountry2Name'] ?? '')));
            if (preg_match('/^[A-Z]{2}$/', $home) && $home !== $country) {
                return new CodmRegionResult(CodmRegionResult::MISMATCH, $country, null, null, $home, self::suggestVariant($home), 'country_mismatch', $httpStatus, $body);
            }
        }

        $code = isset($body['errorCode']) ? 'error_'.(string) $body['errorCode'] : 'unexpected_body';

        return CodmRegionResult::unknown($country, $code, $httpStatus, $body);
    }
}

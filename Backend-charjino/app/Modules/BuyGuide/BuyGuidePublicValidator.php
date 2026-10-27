<?php

namespace App\Modules\BuyGuide;

use App\Exceptions\ApiException;

final class BuyGuidePublicValidator
{
    /** @var list<string> */
    private const COMPETITORS = [
        'bazelio',
        'codashop',
        'iranmojo',
        'iranicard',
        'gifteto',
        'igift24',
        'giftro',
        'play360',
        'cdkey.ir',
        'nikogem',
        'matrixgame',
        'mojogift',
        'seagm',
        'recharge.com',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function assertPayload(array $payload): void
    {
        $haystack = BuyGuideContentRules::flatten([
            $payload['title'] ?? '',
            $payload['summary'] ?? '',
            strip_tags((string) ($payload['intro'] ?? '')),
            $payload['seo_title'] ?? $payload['seoTitle'] ?? '',
            $payload['seo_description'] ?? $payload['seoDescription'] ?? '',
            $payload['sections'] ?? [],
        ]);
        self::assertText($haystack);
        self::assertUrls($payload);
    }

    public static function assertText(string $text): void
    {
        BuyGuideContentRules::assertText($text);
        if (str_contains($text, '**') || str_contains($text, '`')) {
            throw new ApiException('نشانهٔ Markdown (** یا بک‌تیک) در خروجی عمومی مجاز نیست.', 422);
        }
        if (preg_match('/^\s{0,3}#{1,6}\s/m', $text) === 1) {
            throw new ApiException('تیتر Markdown نباید در خروجی عمومی بماند.', 422);
        }
        if (preg_match('/^>/m', $text) === 1) {
            throw new ApiException('نقل‌قول خام Markdown در خروجی عمومی مجاز نیست.', 422);
        }
        if (preg_match('/\|[-: ]+\|/', $text) === 1) {
            throw new ApiException('جدول Markdown نباید در خروجی عمومی بماند.', 422);
        }
        if (preg_match('/INDEX\.md|\bslug\b|\bappid\b|طبق فرم محصول/iu', $text) === 1) {
            throw new ApiException('خروجی عمومی نباید INDEX، slug، appid یا ارجاع «طبق فرم محصول» داشته باشد.', 422);
        }
        $lower = mb_strtolower($text);
        foreach (self::COMPETITORS as $name) {
            if (str_contains($lower, mb_strtolower($name))) {
                throw new ApiException('نام یا دامنهٔ رقیب در خروجی عمومی مجاز نیست.', 422);
            }
        }
    }

    /**
     * @param  mixed  $node
     */
    private static function assertUrls(mixed $node): void
    {
        if (! is_array($node)) {
            return;
        }
        foreach ($node as $key => $value) {
            if (is_string($value) && in_array((string) $key, ['url', 'href'], true) && preg_match('#^https?://#i', $value) === 1) {
                if (! BuyGuideLinkPolicy::allowsUrl($value) && ! BuyGuideLinkPolicy::allowsVideoUrl($value)) {
                    throw new ApiException('لینک خروجی خارج از فهرست دامنه‌های مجاز است.', 422);
                }
            }
            if (is_array($value)) {
                self::assertUrls($value);
            }
        }
    }
}

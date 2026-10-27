<?php

namespace App\Modules\BuyGuide;

use App\Exceptions\ApiException;

final class BuyGuideContentRules
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function assert(array $payload): array
    {
        $payload['title'] = self::cleanText((string) ($payload['title'] ?? ''));
        $payload['summary'] = self::cleanText((string) ($payload['summary'] ?? ''));
        $payload['intro'] = BuyGuideHtml::sanitize((string) ($payload['intro'] ?? ''));
        BuyGuidePublicValidator::assertPayload($payload);
        $payload['seo_title'] = self::cleanText((string) ($payload['seoTitle'] ?? $payload['seo_title'] ?? ''));
        $payload['seo_description'] = self::cleanText((string) ($payload['seoDescription'] ?? $payload['seo_description'] ?? ''));
        $chunks = [
            $payload['title'],
            $payload['summary'],
            strip_tags($payload['intro']),
            $payload['seo_title'],
            $payload['seo_description'],
        ];
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                continue;
            }
            $type = (string) ($section['type'] ?? '');
            if ($type === 'videos') {
                self::assertVideos($section['data'] ?? []);
            }
            $chunks[] = self::flatten($section);
            $sections[$index] = $section;
        }
        $payload['sections'] = $sections;
        self::assertText(implode("\n", $chunks));

        return $payload;
    }

    public static function assertText(string $text): void
    {
        $normalized = self::cleanText($text);
        if (self::asksForPassword($normalized)) {
            throw new ApiException('متن راهنما نباید از کاربر رمز عبور یا اطلاعات ورود بخواهد.', 422);
        }
        if (preg_match('/تحویل\s*(فوری|آنی|در\s*\d+\s*(دقیقه|ساعت))/u', $normalized) === 1) {
            throw new ApiException('در راهنما وعدهٔ زمان تحویل نگذارید.', 422);
        }
        if (preg_match('/\d[\d٬,.]{2,}\s*(تومان|ریال)|(?:USD|\$)\s*\d/u', $normalized) === 1) {
            throw new ApiException('عدد قیمت در متن راهنما مجاز نیست.', 422);
        }
    }

    public static function cleanText(string $value): string
    {
        $text = str_replace('چارژینو', 'شارژینو', $value);

        return trim($text);
    }

    public static function assertVideoUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new ApiException('آدرس ویدیو لازم است.', 422);
        }
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        if (! in_array($host, ['aparat.com', 'youtube.com', 'youtu.be'], true)) {
            throw new ApiException('ویدیو فقط از آپارات یا یوتیوب پذیرفته می‌شود.', 422);
        }

        return $url;
    }

    /**
     * @param  mixed  $data
     */
    private static function assertVideos(mixed $data): void
    {
        if (! is_array($data)) {
            return;
        }
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $provider = (string) ($item['provider'] ?? '');
            if ($provider === 'self') {
                continue;
            }
            if (! empty($item['url'])) {
                self::assertVideoUrl((string) $item['url']);
            }
        }
    }

    private static function asksForPassword(string $text): bool
    {
        if (preg_match('/رمز\s*عبور|کلمه\s*عبور|پسورد|\bpassword\b/iu', $text) !== 1) {
            return false;
        }
        if (preg_match('/هرگز.{0,80}(رمز|پسورد|password)|نمی[‌ ]?خواهد|درخواست نمی/u', $text) === 1) {
            return false;
        }

        return preg_match('/(وارد|بفرست|ارسال|بدهید|بده|لازم|نیاز|فیلد).{0,40}(رمز|پسورد|password)|(رمز|پسورد|password).{0,40}(وارد|بفرست|ارسال)/u', $text) === 1;
    }

    /**
     * @param  mixed  $value
     */
    public static function flatten(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (! is_array($value)) {
            return '';
        }
        $parts = [];
        array_walk_recursive($value, function ($item) use (&$parts): void {
            if (is_string($item) || is_numeric($item)) {
                $parts[] = (string) $item;
            }
        });

        return implode("\n", $parts);
    }
}

<?php

namespace App\Modules\Blog;

use Mews\Purifier\Facades\Purifier;

final class BlogHtml
{
    public static function sanitize(string $html): string
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return '';
        }
        if (! preg_match('/<[a-z][\s\S]*>/i', $trimmed)) {
            $trimmed = '<p>'.e($trimmed).'</p>';
        }
        $clean = (string) Purifier::clean($trimmed, 'blog');
        $clean = self::normalizeLinks($clean);
        $clean = self::normalizeImages($clean);
        $clean = preg_replace('/\s(on\w+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;

        return trim($clean);
    }

    public static function excerpt(string $html, int $max = 220): string
    {
        $text = self::plainText($html);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    public static function plainText(string $html): string
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    public static function wordCount(string $html): int
    {
        $text = self::plainText($html);
        if ($text === '') {
            return 0;
        }
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? count($parts) : 0;
    }

    public static function readingMinutes(string $html): int
    {
        return max(1, (int) ceil(self::wordCount($html) / 200));
    }

    public static function defaultMetaTitle(string $title): string
    {
        $base = trim($title);
        if ($base === '') {
            return 'شارژینو';
        }
        if (str_contains($base, 'شارژینو')) {
            return mb_substr($base, 0, 70);
        }

        return mb_substr($base.' | شارژینو', 0, 70);
    }

    private static function normalizeLinks(string $html): string
    {
        return preg_replace_callback('/<a\s+([^>]+)>/i', function (array $match): string {
            $href = self::attr($match[1], 'href');
            if ($href === '' || ! self::allowsHref($href)) {
                return '<span>';
            }
            $rel = strtolower(self::attr($match[1], 'rel'));
            $target = self::attr($match[1], 'target');
            $parts = [];
            if (self::isExternal($href)) {
                $parts[] = 'noopener';
                $parts[] = 'noreferrer';
            }
            if (str_contains($rel, 'nofollow')) {
                $parts[] = 'nofollow';
            }
            $relAttr = $parts !== [] ? ' rel="'.implode(' ', array_unique($parts)).'"' : '';
            $targetAttr = $target === '_blank' ? ' target="_blank"' : '';

            return '<a href="'.e($href).'"'.$relAttr.$targetAttr.'>';
        }, $html) ?? $html;
    }

    private static function normalizeImages(string $html): string
    {
        return preg_replace_callback('/<img\s+([^>]+)>/i', function (array $match): string {
            $src = self::attr($match[1], 'src');
            $alt = self::attr($match[1], 'alt');
            $width = self::attr($match[1], 'width');
            $height = self::attr($match[1], 'height');
            if ($src === '' || ! self::allowsSrc($src)) {
                return '';
            }
            $out = '<img src="'.e($src).'" alt="'.e($alt).'"';
            if (ctype_digit($width)) {
                $out .= ' width="'.$width.'"';
            }
            if (ctype_digit($height)) {
                $out .= ' height="'.$height.'"';
            }

            return $out.'>';
        }, $html) ?? $html;
    }

    private static function attr(string $attrs, string $name): string
    {
        if (preg_match('/'.$name.'\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $match) !== 1) {
            return '';
        }

        return trim($match[2] !== '' ? $match[2] : ($match[3] ?? ''));
    }

    public static function allowsHref(string $href): bool
    {
        $href = trim($href);
        $lower = strtolower($href);
        if ($href === '' || str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
            return false;
        }
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return true;
        }

        return preg_match('#^https://#i', $href) === 1;
    }

    public static function allowsSrc(string $src): bool
    {
        $src = trim($src);
        if (str_starts_with($src, '/storage/')) {
            return true;
        }
        if (preg_match('#^https://#i', $src) !== 1) {
            return false;
        }
        $host = strtolower((string) parse_url($src, PHP_URL_HOST));
        $path = (string) parse_url($src, PHP_URL_PATH);
        if (! str_starts_with($path, '/storage/')) {
            return false;
        }
        foreach (self::allowedHosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    public static function isExternal(string $href): bool
    {
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return false;
        }
        $host = strtolower((string) parse_url($href, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        foreach (self::allowedHosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function allowedHosts(): array
    {
        $hosts = ['charjino.ir', 'www.charjino.ir', 'api.charjino.ir'];
        foreach ([config('app.url'), config('services.storefront.url')] as $url) {
            $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }
}

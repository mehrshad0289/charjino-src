<?php

namespace App\Modules\Blog;

use App\Exceptions\ApiException;

final class BlogContentRules
{
    public static function assertStructure(string $html): void
    {
        if (preg_match('/<h1\b/i', $html) === 1) {
            throw new ApiException('تیتر H1 داخل متن مجاز نیست؛ عنوان مطلب همان H1 است.', 422, 'BLOG_H1');
        }
        if (preg_match('/<h[56]\b/i', $html) === 1) {
            throw new ApiException('فقط هدینگ H2 تا H4 داخل متن مجاز است.', 422, 'BLOG_HEADING');
        }
        if (preg_match('/^\s{0,3}#{1,6}\s+/m', $html) === 1) {
            throw new ApiException('Markdown خام در متن مجاز نیست؛ از ویرایشگر استفاده کن.', 422, 'BLOG_MARKDOWN');
        }
        $plain = BlogHtml::plainText($html);
        if (preg_match('/\*\*[^*\n]+\*\*/u', $plain) === 1) {
            throw new ApiException('Markdown خام در متن مجاز نیست؛ از ویرایشگر استفاده کن.', 422, 'BLOG_MARKDOWN');
        }
    }

    public static function assertPublishable(string $html): void
    {
        self::assertStructure($html);
        if (preg_match_all('/<img\b([^>]*)>/i', $html, $images) > 0) {
            foreach ($images[1] as $attrs) {
                if (preg_match('/\balt\s*=\s*("([^"]*)"|\'([^\']*)\')/i', (string) $attrs, $match) !== 1) {
                    throw new ApiException('همه تصاویر باید متن جایگزین (alt) داشته باشند.', 422, 'BLOG_IMG_ALT');
                }
                $alt = trim($match[2] !== '' ? $match[2] : ($match[3] ?? ''));
                if ($alt === '') {
                    throw new ApiException('همه تصاویر باید متن جایگزین (alt) داشته باشند.', 422, 'BLOG_IMG_ALT');
                }
            }
        }
        if (preg_match_all('/<a\b([^>]*)>/i', $html, $links) > 0) {
            foreach ($links[1] as $attrs) {
                if (preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')/i', (string) $attrs, $match) !== 1) {
                    throw new ApiException('لینک بدون آدرس در متن است.', 422, 'BLOG_LINK');
                }
                $href = trim($match[2] !== '' ? $match[2] : ($match[3] ?? ''));
                if ($href === '' || $href === '#' || ! BlogHtml::allowsHref($href)) {
                    throw new ApiException('لینک خالی یا نامعتبر در متن است.', 422, 'BLOG_LINK');
                }
            }
        }
    }

    public static function assertMetaDescription(?string $value): void
    {
        $text = trim((string) $value);
        $len = mb_strlen($text);
        if ($len < 120 || $len > 160) {
            throw new ApiException('توضیح متا باید بین ۱۲۰ تا ۱۶۰ کاراکتر باشد.', 422, 'BLOG_META_DESCRIPTION');
        }
    }
}

<?php

namespace App\Modules\BuyGuide;

final class BuyGuidePublicCopy
{
    /** @var list<string> */
    public const BLOCKED_HOSTS = [
        'iranmojo.com',
        'iranicard.ir',
        'gifteto.com',
        'igift24.net',
        'giftro.ir',
        'play360.shop',
        'cdkey.ir',
        'nikogem.com',
        'matrixgame.ir',
        'mojogift.com',
        'seagm.com',
    ];

    public static function stripMarks(string $text): string
    {
        return BuyGuideContentRules::cleanText(preg_replace('/[*`]/', '', $text) ?? $text);
    }

    public static function isInternal(string $text): bool
    {
        if (preg_match('/رقیب|فایل داخلی|fazercards|\bfazer\b|iranmojo|iranicard|gifteto|igift24/iu', $text) === 1) {
            return true;
        }
        if (preg_match('#\b(?:GET|POST)\s+/api#i', $text) === 1) {
            return true;
        }
        if (preg_match('/\bfast\b.{0,120}\breserve\b|\breserve\b.{0,120}\bfast\b|pubg-mobile-manual|\*-manual|\*-login/iu', $text) === 1) {
            return true;
        }
        if (preg_match('/محصولات لاگینی|محصولات کاتالوگ که این راهنما|طبق مستندات تأمین/u', $text) === 1) {
            return true;
        }

        return preg_match('#charjino\.ir/catalog/#i', $text) === 1;
    }

    public static function isCustomerSource(string $url, string $label = ''): bool
    {
        if ($url === '' || self::isInternal($label.' '.$url)) {
            return false;
        }

        return BuyGuideLinkPolicy::allowsUrl($url);
    }

    public static function presentIntroHtml(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }
        if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches, PREG_SET_ORDER)) {
            $out = '';
            foreach ($matches as $match) {
                $plain = self::plainFromHtml($match[1]);
                if ($plain === '' || self::isInternal($plain)) {
                    continue;
                }
                $out .= BuyGuideHtml::paragraphs($plain);
            }

            return $out !== '' ? $out : null;
        }
        $plain = self::plainFromHtml($html);
        if ($plain === '' || self::isInternal($plain)) {
            return null;
        }

        return BuyGuideHtml::paragraphs($plain);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function presentPayload(array $payload): array
    {
        $summary = self::stripMarks((string) ($payload['summary'] ?? ''));
        $payload['summary'] = $summary !== '' && ! self::isInternal($summary) ? $summary : null;
        $payload['introHtml'] = self::presentIntroHtml(isset($payload['introHtml']) ? (string) $payload['introHtml'] : null);
        $payload['sections'] = self::presentSections(is_array($payload['sections'] ?? null) ? $payload['sections'] : []);

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function presentSections(array $sections): array
    {
        $out = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $type = (string) ($section['type'] ?? '');
            if ($type === 'sources') {
                $section['items'] = self::publicSources($section['items'] ?? []);
                if ($section['items'] === []) {
                    continue;
                }
            } elseif ($type === 'faq') {
                $section['items'] = self::publicFaqs($section['items'] ?? []);
                if ($section['items'] === []) {
                    continue;
                }
            } elseif ($type === 'before_buy') {
                $section['notes'] = self::publicNotes($section['notes'] ?? []);
                if ($section['notes'] === []) {
                    continue;
                }
            }
            $out[] = self::stripMarksDeep($section);
        }

        return $out;
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private static function publicSources(mixed $items): array
    {
        $out = [];
        if (! is_array($items)) {
            return $out;
        }
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = (string) ($item['url'] ?? '');
            $label = self::stripMarks((string) ($item['label'] ?? ''));
            if (! self::isCustomerSource($url, $label)) {
                continue;
            }
            $item['label'] = $label;
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private static function publicFaqs(mixed $items): array
    {
        $out = [];
        if (! is_array($items)) {
            return $out;
        }
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $q = self::stripMarks((string) ($item['q'] ?? ''));
            $a = self::stripMarks((string) ($item['a'] ?? ''));
            if ($q === '' || $a === '' || self::isInternal($q.' '.$a)) {
                continue;
            }
            $item['q'] = $q;
            $item['a'] = $a;
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  mixed  $notes
     * @return list<array<string, mixed>>
     */
    private static function publicNotes(mixed $notes): array
    {
        $out = [];
        if (! is_array($notes)) {
            return $out;
        }
        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }
            $text = self::stripMarks((string) ($note['text'] ?? ''));
            if ($text === '' || self::isInternal($text)) {
                continue;
            }
            $note['text'] = $text;
            $out[] = $note;
        }

        return $out;
    }

    /**
     * @param  mixed  $node
     * @return mixed
     */
    private static function stripMarksDeep(mixed $node): mixed
    {
        if (is_string($node)) {
            return self::stripMarks($node);
        }
        if (! is_array($node)) {
            return $node;
        }
        $out = [];
        foreach ($node as $key => $value) {
            if (in_array($key, ['url', 'src', 'srcset', 'href', 'type', 'source', 'product_handle'], true) && is_string($value)) {
                $out[$key] = $value;
                continue;
            }
            $out[$key] = self::stripMarksDeep($value);
        }

        return $out;
    }

    private static function plainFromHtml(string $html): string
    {
        $text = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::stripMarks($text);
    }
}

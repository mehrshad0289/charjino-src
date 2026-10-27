<?php

$root = __DIR__;
$items = [];
foreach (glob($root.DIRECTORY_SEPARATOR.'*.php') as $file) {
    $base = basename($file);
    if (str_starts_with($base, '_')) {
        continue;
    }
    $item = require $file;
    if (! is_array($item) || empty($item['slug'])) {
        continue;
    }
    $items[] = $item;
}

function blog_plain(string $html): string
{
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

function blog_words(string $html): int
{
    $text = blog_plain($html);
    if ($text === '') {
        return 0;
    }
    $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

    return is_array($parts) ? count($parts) : 0;
}

$fail = 0;
foreach ($items as $item) {
    $words = blog_words((string) $item['content']);
    $meta = (string) $item['metaDescription'];
    $metaLen = mb_strlen($meta);
    $titleLen = mb_strlen((string) $item['title']);
    $metaTitleLen = mb_strlen((string) $item['metaTitle']);
    $links = preg_match_all('/<a\s+[^>]*href="(\/catalog\/[^"]+)"/i', (string) $item['content'], $m);
    $h2 = preg_match_all('/<h2\b/i', (string) $item['content']);
    $h3 = preg_match_all('/<h3\b/i', (string) $item['content']);
    $h1 = preg_match('/<h1\b/i', (string) $item['content']);
    $faq = is_array($item['faq'] ?? null) ? count($item['faq']) : 0;
    $ok = $words >= 1200 && $words <= 2000 && $metaLen >= 120 && $metaLen <= 160
        && $titleLen >= 10 && $titleLen <= 100 && $metaTitleLen <= 80
        && $links >= 2 && $links <= 4 && $h2 >= 4 && $h3 >= 2 && ! $h1 && $faq >= 4;
    if (! $ok) {
        $fail++;
    }
    echo sprintf(
        "%s words=%d meta=%d title=%d mt=%d links=%d h2=%d h3=%d faq=%d %s\n",
        $item['slug'],
        $words,
        $metaLen,
        $titleLen,
        $metaTitleLen,
        $links,
        $h2,
        $h3,
        $faq,
        $ok ? 'OK' : 'FAIL'
    );
}
exit($fail > 0 ? 1 : 0);

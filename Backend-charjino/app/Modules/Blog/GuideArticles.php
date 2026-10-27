<?php

namespace App\Modules\Blog;

final class GuideArticles
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $items = [];
        foreach (glob(database_path('data/blog-guides/*.php')) ?: [] as $file) {
            if (str_starts_with(basename($file), '_')) {
                continue;
            }
            $item = require $file;
            if (! is_array($item) || trim((string) ($item['slug'] ?? '')) === '') {
                continue;
            }
            $items[] = $item;
        }
        usort($items, function (array $a, array $b): int {
            return strcmp((string) ($a['publishedAt'] ?? ''), (string) ($b['publishedAt'] ?? ''));
        });

        return $items;
    }
}

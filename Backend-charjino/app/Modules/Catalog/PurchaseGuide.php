<?php

namespace App\Modules\Catalog;

final class PurchaseGuide
{
    public const TYPES = ['text', 'image', 'video', 'gif'];

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    public static function sanitize(array $items): array
    {
        $out = [];
        foreach (array_slice($items, 0, 20) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            if ($type === '') {
                $type = filled($row['src'] ?? null) ? MediaType::fromSrc((string) $row['src']) : 'text';
            }
            if (! in_array($type, self::TYPES, true)) {
                continue;
            }
            $title = self::clip((string) ($row['title'] ?? ''), 120);
            $caption = self::clip((string) ($row['caption'] ?? ''), 300);
            $body = CatalogAttr::multiline((string) ($row['body'] ?? '')) ?? '';
            $src = self::clip((string) ($row['src'] ?? ''), 500);

            if ($type === 'text') {
                if ($body === '' && $title === '') {
                    continue;
                }
                $out[] = array_filter([
                    'type' => 'text',
                    'title' => $title !== '' ? $title : null,
                    'body' => $body !== '' ? $body : null,
                ], fn ($value) => $value !== null);
                continue;
            }

            if ($src === '') {
                continue;
            }
            $out[] = array_filter([
                'type' => $type,
                'title' => $title !== '' ? $title : null,
                'src' => $src,
                'caption' => $caption !== '' ? $caption : null,
            ], fn ($value) => $value !== null);
        }

        return array_values($out);
    }

    /**
     * @param  list<mixed>  $items
     */
    public static function encode(array $items): ?string
    {
        $clean = self::sanitize($items);
        if ($clean === []) {
            return null;
        }

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function decode(?string $raw): array
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }
        if (str_starts_with($text, '[')) {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return self::sanitize($decoded);
            }
        }

        $plain = CatalogAttr::multiline($text);

        return $plain ? [['type' => 'text', 'body' => $plain]] : [];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function plaintext(array $items): ?string
    {
        $parts = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'text') {
                continue;
            }
            $chunk = trim((string) ($item['title'] ?? ''));
            $body = trim((string) ($item['body'] ?? ''));
            if ($chunk !== '' && $body !== '') {
                $parts[] = $chunk."\n".$body;
            } elseif ($body !== '') {
                $parts[] = $body;
            } elseif ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        return $parts !== [] ? implode("\n\n", $parts) : null;
    }

    private static function clip(string $value, int $max): string
    {
        $text = trim($value);

        return $text === '' ? '' : mb_substr($text, 0, $max);
    }
}

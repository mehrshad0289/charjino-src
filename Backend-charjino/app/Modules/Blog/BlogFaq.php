<?php

namespace App\Modules\Blog;

final class BlogFaq
{
    /**
     * @return list<array{question: string, answer: string}>|null
     */
    public static function parse(mixed $value): ?array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            $decoded = json_decode($trimmed, true);
            $value = is_array($decoded) ? $decoded : self::linesToItems($trimmed);
        }
        if (! is_array($value)) {
            return null;
        }
        $out = [];
        foreach ($value as $item) {
            $parsed = is_string($item) ? self::lineToItem($item) : self::itemToPair($item);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out !== [] ? array_values($out) : null;
    }

    /**
     * @param  list<array{question: string, answer: string}>|null  $items
     */
    public static function toLines(?array $items): string
    {
        if ($items === null || $items === []) {
            return '';
        }

        return implode("\n", array_map(
            fn (array $item): string => $item['question'].' | '.$item['answer'],
            $items,
        ));
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private static function linesToItems(string $text): array
    {
        $out = [];
        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            $item = self::lineToItem((string) $line);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return array{question: string, answer: string}|null
     */
    public static function lineToItem(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }
        if (str_contains($line, '||')) {
            [$question, $answer] = array_pad(explode('||', $line, 2), 2, '');
        } elseif (str_contains($line, '|')) {
            [$question, $answer] = array_pad(explode('|', $line, 2), 2, '');
        } else {
            return null;
        }

        return self::pair((string) $question, (string) $answer);
    }

    /**
     * @return array{question: string, answer: string}|null
     */
    private static function itemToPair(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }
        $question = (string) ($item['question'] ?? $item['q'] ?? '');
        $answer = (string) ($item['answer'] ?? $item['a'] ?? '');

        return self::pair($question, $answer);
    }

    /**
     * @return array{question: string, answer: string}|null
     */
    private static function pair(string $question, string $answer): ?array
    {
        $question = trim(strip_tags($question));
        $answer = trim(strip_tags($answer));
        if ($question === '' || $answer === '') {
            return null;
        }

        return [
            'question' => mb_substr($question, 0, 180),
            'answer' => mb_substr($answer, 0, 1000),
        ];
    }
}

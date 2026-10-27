<?php

namespace App\Modules\SmartSearch;

final class SearchDocumentId
{
    public static function make(string ...$parts): string
    {
        $raw = implode('__', array_filter($parts, fn ($p) => $p !== ''));
        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $raw) ?? '';
        $safe = trim($safe, '_');
        if ($safe === '') {
            $safe = 'doc_'.substr(sha1($raw), 0, 16);
        }

        return substr($safe, 0, 511);
    }

    public static function ofHandle(string $handle): string
    {
        if (preg_match('/^[a-zA-Z0-9_-]{1,511}$/', $handle) === 1) {
            return $handle;
        }

        return self::make($handle);
    }
}

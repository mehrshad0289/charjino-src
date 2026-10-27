<?php

namespace App\Modules\Catalog;

final class MediaType
{
    public static function fromSrc(string $src): string
    {
        $path = strtolower((string) (parse_url($src, PHP_URL_PATH) ?: $src));

        if (str_ends_with($path, '.mp4') || str_ends_with($path, '.webm') || str_ends_with($path, '.mov')) {
            return 'video';
        }

        if (str_ends_with($path, '.gif')) {
            return 'gif';
        }

        return 'image';
    }

    public static function fromUpload(?string $mime, string $filename): string
    {
        $mime = strtolower((string) $mime);
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return self::fromSrc($filename);
    }
}

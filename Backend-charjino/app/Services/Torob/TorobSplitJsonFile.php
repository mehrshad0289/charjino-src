<?php

namespace App\Services\Torob;

final class TorobSplitJsonFile
{
    /**
     * @return array<string, mixed>
     */
    public static function read(string $path): array
    {
        if ($path === '' || ! is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function write(string $path, array $data): void
    {
        if ($path === '') {
            return;
        }
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        file_put_contents($path, ($json !== false ? $json : '{}')."\n", LOCK_EX);
    }
}

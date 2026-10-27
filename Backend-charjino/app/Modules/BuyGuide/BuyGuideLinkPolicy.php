<?php

namespace App\Modules\BuyGuide;

final class BuyGuideLinkPolicy
{
    public static function hostOf(string $url): string
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    public static function allowsHost(string $host, ?array $allowed = null): bool
    {
        $host = preg_replace('/^www\./', '', mb_strtolower($host)) ?? $host;
        if ($host === '') {
            return false;
        }
        foreach ($allowed ?? config('buy_guide.outbound_hosts', []) as $ok) {
            $ok = preg_replace('/^www\./', '', mb_strtolower((string) $ok)) ?? $ok;
            if ($ok === '') {
                continue;
            }
            if ($host === $ok || str_ends_with($host, '.'.$ok)) {
                return true;
            }
        }

        return false;
    }

    public static function allowsUrl(string $url, ?array $allowed = null): bool
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }

        return self::allowsHost(self::hostOf($url), $allowed);
    }

    public static function allowsVideoUrl(string $url): bool
    {
        return self::allowsUrl($url, config('buy_guide.video_hosts', []));
    }
}

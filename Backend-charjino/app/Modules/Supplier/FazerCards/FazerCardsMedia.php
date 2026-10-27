<?php

namespace App\Modules\Supplier\FazerCards;

final class FazerCardsMedia
{
    public static function absolute(?string $raw): string
    {
        $value = trim((string) $raw);
        if ($value === '' || strcasecmp($value, 'null') === 0) {
            return '';
        }
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }
        if (str_starts_with($value, '//')) {
            return 'https:'.$value;
        }
        if (str_starts_with($value, '/image/') || str_starts_with($value, '/storage/')) {
            return $value;
        }

        $origin = rtrim((string) preg_replace('#/api/v2$#i', '', rtrim((string) config('charjino.supplier.fazercards_base'), '/')), '/');
        if ($origin === '') {
            $origin = 'https://api.fzr.cards';
        }
        if (str_starts_with($value, '/')) {
            return $origin.$value;
        }

        return $origin.'/'.$value;
    }

    public static function steamHeader(int|string $appId): string
    {
        $id = preg_replace('/\D+/', '', (string) $appId) ?? '';
        if ($id === '' || (int) $id <= 0) {
            return '';
        }

        return 'https://cdn.cloudflare.steamstatic.com/steam/apps/'.$id.'/header.jpg';
    }

    public static function isCloudflareSteam(string $url): bool
    {
        return str_contains(strtolower($url), 'cloudflare.steamstatic.com');
    }

    public static function isClassicSteamHeader(string $url): bool
    {
        $url = trim($url);

        return (bool) preg_match(
            '#^https://(?:[^/]*steamstatic\.com|steamcdn-a\.akamaihd\.net)/steam/apps/\d+/header\.jpg(?:\?.*)?$#i',
            $url,
        );
    }

    public static function iranSafeSteamUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        return str_ireplace(
            [
                'cdn.cloudflare.steamstatic.com',
                'clan.cloudflare.steamstatic.com',
                'shared.cloudflare.steamstatic.com',
                'shared.fastly.steamstatic.com',
            ],
            [
                'cdn.akamai.steamstatic.com',
                'clan.akamai.steamstatic.com',
                'shared.akamai.steamstatic.com',
                'shared.akamai.steamstatic.com',
            ],
            $url,
        );
    }

    public static function isHashedSteamHeader(string $url): bool
    {
        $path = explode('?', trim($url), 2)[0];

        return (bool) preg_match(
            '#^https://[a-z0-9.-]*akamai\.steamstatic\.com/store_item_assets/steam/apps/\d+/[0-9a-f]{16,64}/header\.jpg$#i',
            $path,
        );
    }

    public static function isLegacySteamHeader(string $url): bool
    {
        return self::isClassicSteamHeader($url) || self::isCloudflareSteam($url);
    }

    public static function appIdFromUrl(string $url): int
    {
        if (preg_match('#/apps/(\d+)/#', $url, $match) !== 1) {
            return 0;
        }

        return (int) $match[1];
    }
}

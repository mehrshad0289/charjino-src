<?php

namespace App\Modules\Supplier\FazerCards;

use App\Support\Money;

final class FazerCardsSteamGiftQuotes
{
    /**
     * GET /steam-gifts/games/{appid} آفر را با قیمت منطقه‌ای می‌دهد، نه price_usd تکی.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function regionOffers(array $payload): array
    {
        $rows = FazerCardsPayload::listFrom($payload, ['offers', 'packages', 'subs', 'items']);
        $out = [];
        foreach ($rows as $row) {
            $subId = trim((string) ($row['sub_id'] ?? $row['package_id'] ?? $row['id'] ?? ''));
            if ($subId === '' || $subId === '0') {
                continue;
            }
            $name = trim((string) ($row['name'] ?? $row['title'] ?? ''));
            $regions = $row['regions'] ?? [];
            if (! is_array($regions) || $regions === []) {
                $usd = Money::string($row['price_usd'] ?? $row['price'] ?? '0');
                if (! Money::isPositive($usd)) {
                    continue;
                }
                $region = strtoupper(trim((string) ($row['region'] ?? 'US')));
                $out[] = self::skuRow($subId, $name, $region, $usd);

                continue;
            }
            foreach ($regions as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                $region = strtoupper(trim((string) ($slot['region'] ?? $slot['code'] ?? '')));
                $usd = Money::string($slot['price'] ?? $slot['price_usd'] ?? '0');
                if ($region === '' || ! Money::isPositive($usd)) {
                    continue;
                }
                $out[] = self::skuRow($subId, $name, $region, $usd);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function usdForOffer(array $payload, string $offerId): string
    {
        [$subId, $region] = self::splitOfferId($offerId);
        if ($subId === '' || $region === '') {
            return '0';
        }
        foreach (self::regionOffers($payload) as $row) {
            if ((string) ($row['offer_id'] ?? '') === $subId.'|'.$region) {
                return Money::string($row['price_usd'] ?? '0');
            }
        }

        return '0';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function splitOfferId(string $offerId): array
    {
        $parts = explode('|', trim($offerId), 2);
        $subId = trim((string) ($parts[0] ?? ''));
        $region = strtoupper(trim((string) ($parts[1] ?? '')));

        return [$subId, $region];
    }

    public static function normalizeInviteUrl(string $raw): string
    {
        $url = trim($raw);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^s\.team/p/#i', $url) === 1) {
            return 'https://'.$url;
        }

        return $url;
    }

    public static function inviteAllowed(string $raw): bool
    {
        $url = self::normalizeInviteUrl($raw);

        return preg_match('#^https://s\.team/p/\S+#i', $url) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private static function skuRow(string $subId, string $name, string $region, string $usd): array
    {
        $label = $name !== '' ? $name.' ('.$region.')' : $region;

        return [
            'sub_id' => $subId,
            'offer_id' => $subId.'|'.$region,
            'name' => $label,
            'region' => $region,
            'price_usd' => $usd,
        ];
    }
}

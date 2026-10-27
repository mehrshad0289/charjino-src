<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use App\Models\Sku;
use App\Modules\Supplier\FazerCards\FazerCardsIds;

final class GiftCardBrand
{
    /**
     * Longest prefix first. Maps Fazer brand slug → shop subcategory id.
     *
     * @var array<string, string>
     */
    private const BRAND_PREFIXES = [
        'xbox-game-pass' => 'xbox',
        'app-store-itunes' => 'itunes',
        'google-play' => 'google-play',
        'steam-wallet' => 'steam',
        'nintendo-switch' => 'nintendo',
        'razer-gold' => 'razer-gold',
        'riot-access-code' => 'riot',
        'league-of-legends' => 'league-of-legends',
        'garena-free-fire' => 'free-fire',
        'garena-shells' => 'garena',
        'apex-legends-mobile' => 'apex-legends',
        'ea-gift-cards' => 'ea',
        'battle-net' => 'battlenet',
        'playstation' => 'playstation',
        'pubg-battlegrounds' => 'pubg',
        'pubg-mobile' => 'pubg-mobile',
        'wild-rift' => 'wild-rift',
        'free-fire' => 'free-fire',
        'netease-pay' => 'netease',
        'nintendo' => 'nintendo',
        'microsoft' => 'xbox',
        'amazon' => 'amazon',
        'netflix' => 'netflix',
        'roblox' => 'roblox',
        'fortnite' => 'fortnite',
        'valorant' => 'valorant',
        'tiktok' => 'tiktok',
        'tinder' => 'tinder',
        'twitch' => 'twitch',
        'airbnb' => 'airbnb',
        'discord' => 'discord',
        'telegram' => 'telegram',
        'eneba' => 'eneba',
        'kinguin' => 'kinguin',
        'astropay' => 'astropay',
        'itunes' => 'itunes',
        'xbox' => 'xbox',
        'steam' => 'steam',
        'ea' => 'ea',
    ];

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'itunes' => 'App Store',
        'google-play' => 'Google Play',
        'razer-gold' => 'Razer Gold',
        'league-of-legends' => 'League of Legends',
        'wild-rift' => 'Wild Rift',
        'pubg-mobile' => 'PUBG Mobile',
        'free-fire' => 'Free Fire',
        'amazon' => 'Amazon',
        'netflix' => 'Netflix',
        'roblox' => 'Roblox',
        'fortnite' => 'Fortnite',
        'valorant' => 'Valorant',
        'riot' => 'Riot',
        'tiktok' => 'TikTok',
        'tinder' => 'Tinder',
        'twitch' => 'Twitch',
        'airbnb' => 'Airbnb',
        'discord' => 'Discord',
        'telegram' => 'Telegram',
        'eneba' => 'Eneba',
        'kinguin' => 'Kinguin',
        'astropay' => 'AstroPay',
        'netease' => 'NetEase',
        'garena' => 'Garena',
        'apex-legends' => 'Apex Legends',
        'pubg' => 'PUBG',
    ];

    /**
     * @return array{platform: string, region: string}
     */
    public static function fromRemote(string $remoteId, string $name = ''): array
    {
        $raw = strtolower(trim($remoteId));
        $raw = preg_replace('/^giftcard:/', '', $raw) ?? $raw;
        $raw = str_replace([' ', '-'], '_', $raw);
        $parts = array_values(array_filter(explode('_', $raw), fn ($part) => $part !== ''));
        $region = '';
        if ($parts !== [] && self::isRegionToken((string) $parts[array_key_last($parts)])) {
            $token = (string) array_pop($parts);
            $region = (string) (CatalogAttr::publicRegion(self::displayRegion($token)) ?? '');
        }
        $brand = implode('-', $parts);
        $platform = self::canonicalBrand($brand);
        if ($platform === '' && $name !== '') {
            $fromName = self::fromRemote(self::slugTokens($name), '');
            $platform = $fromName['platform'];
            if ($region === '') {
                $region = $fromName['region'];
            }
        }

        return [
            'platform' => $platform,
            'region' => $region,
        ];
    }

    public static function label(string $id): string
    {
        if (isset(self::LABELS[$id])) {
            return self::LABELS[$id];
        }
        if (isset(GameKeyPlatform::LABELS[$id])) {
            return GameKeyPlatform::LABELS[$id];
        }

        return GameKeyPlatform::label($id);
    }

    public static function backfillEmpty(): int
    {
        return StorefrontHomeCache::defer(function (): int {
            $updated = 0;
            Product::query()
                ->where('fulfillment_type', FazerCardsIds::GIFTCARD)
                ->orderBy('id')
                ->chunkById(100, function ($rows) use (&$updated): void {
                    foreach ($rows as $product) {
                        if (! $product instanceof Product) {
                            continue;
                        }
                        $parsed = self::fromRemote(
                            FazerCardsIds::rawCategory((string) $product->fazercards_category_id),
                            (string) ($product->name_en ?: $product->title),
                        );
                        $dirty = false;
                        if (! filled($product->platform) && $parsed['platform'] !== '') {
                            $product->platform = $parsed['platform'];
                            $dirty = true;
                        }
                        if (! filled($product->region) && $parsed['region'] !== '') {
                            $product->region = $parsed['region'];
                            $dirty = true;
                        }
                        if ($dirty) {
                            $product->save();
                            $updated++;
                        }
                        if (filled($product->region)) {
                            Sku::query()
                                ->where('product_id', $product->id)
                                ->where(function ($query): void {
                                    $query->whereNull('region')->orWhere('region', '');
                                })
                                ->update(['region' => $product->region]);
                        }
                    }
                });

            Product::query()
                ->whereIn('fulfillment_type', [FazerCardsIds::STEAM_GIFT, FazerCardsIds::STEAM_TOPUP])
                ->where(function ($query): void {
                    $query->whereNull('platform')->orWhere('platform', '');
                })
                ->update(['platform' => 'steam']);

            return $updated;
        });
    }

    private static function canonicalBrand(string $slug): string
    {
        $slug = trim($slug, '-');
        if ($slug === '') {
            return '';
        }
        $prefixes = array_keys(self::BRAND_PREFIXES);
        usort($prefixes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($prefixes as $prefix) {
            if ($slug === $prefix || str_starts_with($slug, $prefix.'-')) {
                return self::BRAND_PREFIXES[$prefix];
            }
        }

        return $slug;
    }

    private static function isRegionToken(string $token): bool
    {
        $token = strtolower($token);

        return isset(self::regionTokens()[$token]);
    }

    private static function displayRegion(string $token): string
    {
        $token = strtolower($token);
        return match ($token) {
            'global', 'worldwide' => 'Global',
            'mena' => 'MENA',
            'cis' => 'CIS',
            'eu' => 'EU',
            'uk' => 'UK',
            default => strtoupper($token),
        };
    }

    private static function slugTokens(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';

        return trim($slug, '_');
    }

    /**
     * @return array<string, true>
     */
    private static function regionTokens(): array
    {
        static $tokens = null;
        if (is_array($tokens)) {
            return $tokens;
        }
        $iso = explode(',', 'ad,ae,af,ag,al,am,ao,ar,at,au,az,ba,bd,be,bg,bh,bo,br,by,bz,ca,ch,cl,cn,co,cr,cy,cz,de,dk,do,dz,ec,ee,eg,es,fi,fr,gb,ge,gh,gr,gt,hk,hn,hr,hu,id,ie,il,in,iq,is,it,jo,jp,ke,kr,kw,kz,lb,lk,lt,lu,lv,ma,md,mk,mm,mx,my,ng,nl,no,np,nz,om,pa,pe,ph,pk,pl,pt,qa,ro,rs,ru,sa,se,sg,si,sk,sv,th,tn,tr,tw,ua,ug,us,uy,uz,ve,vn,za');
        $extra = ['eu', 'uk', 'global', 'worldwide', 'mena', 'cis', 'usd', 'eur', 'gbp', 'pln', 'ars', 'aud', 'brl', 'cad', 'aed', 'bdt', 'clp', 'cop', 'idr', 'krw', 'mmk', 'mxn', 'myr', 'ngn', 'rub', 'sar', 'sgd', 'thb', 'try', 'vnd'];
        $tokens = [];
        foreach ([...$iso, ...$extra] as $token) {
            $tokens[$token] = true;
        }

        return $tokens;
    }
}

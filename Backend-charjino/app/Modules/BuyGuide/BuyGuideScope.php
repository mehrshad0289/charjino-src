<?php

namespace App\Modules\BuyGuide;

use App\Models\Product;
use App\Modules\Catalog\GameKeyPlatform;
use App\Modules\Catalog\GiftCardBrand;
use App\Modules\Supplier\FazerCards\FazerCardsIds;

final class BuyGuideScope
{
    public const CATEGORY = 'category';

    public const SUBCATEGORY = 'subcategory';

    public const PRODUCT = 'product';

    public const GAME_TOPUP = 'game-topup';

    public const GIFT_CARD = 'gift-card';

    public const GAME_KEYS = 'game-keys';

    public const TELEGRAM = 'telegram';

    public const STEAM_WALLET = 'steam-wallet';

    public const STEAM_GIFTS = 'steam-gifts';

    /** @var list<string> */
    public const CATEGORIES = [
        self::GAME_TOPUP,
        self::GIFT_CARD,
        self::GAME_KEYS,
        self::TELEGRAM,
        self::STEAM_WALLET,
        self::STEAM_GIFTS,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        self::GAME_TOPUP => 'شارژ مستقیم',
        self::GIFT_CARD => 'گیفت کارت',
        self::GAME_KEYS => 'کلید بازی',
        self::TELEGRAM => 'تلگرام',
        self::STEAM_WALLET => 'کیف پول استیم',
        self::STEAM_GIFTS => 'گیفت استیم',
    ];

    /** @var list<string> */
    public const SECTION_TYPES = [
        'before_buy',
        'fields',
        'purchase_steps',
        'redeem',
        'regions',
        'errors',
        'faq',
        'videos',
        'sources',
    ];

    /**
     * @return array{category: string, subcategory: string}|null
     */
    public static function forProduct(Product $product): ?array
    {
        if (self::isExcluded($product)) {
            return null;
        }
        $family = FazerCardsIds::familyOf($product->fulfillment_type, $product->fazercards_category_id);
        $category = match ($family) {
            FazerCardsIds::TOPUP => self::GAME_TOPUP,
            FazerCardsIds::GIFTCARD => self::GIFT_CARD,
            FazerCardsIds::GAMEKEY => self::GAME_KEYS,
            FazerCardsIds::TELEGRAM_STARS, FazerCardsIds::TELEGRAM_PREMIUM => self::TELEGRAM,
            FazerCardsIds::STEAM_TOPUP => self::STEAM_WALLET,
            FazerCardsIds::STEAM_GIFT => self::STEAM_GIFTS,
            default => null,
        };
        if ($category === null) {
            return null;
        }

        return [
            'category' => $category,
            'subcategory' => self::subcategoryOf($product, $category, $family),
        ];
    }

    public static function isExcluded(Product $product): bool
    {
        $family = FazerCardsIds::familyOf($product->fulfillment_type, $product->fazercards_category_id);
        if (in_array($family, [FazerCardsIds::MANUAL, FazerCardsIds::FUNPAY], true)) {
            return true;
        }
        $handle = mb_strtolower((string) $product->handle);
        $code = mb_strtolower((string) $product->code);

        return str_contains($handle, '-login')
            || str_contains($handle, '-manual')
            || str_contains($code, '-login')
            || str_contains($code, '-manual')
            || str_ends_with($handle, 'login')
            || str_ends_with($handle, 'manual');
    }

    public static function key(string $scopeType, string $category, string $subcategory = '', ?int $productId = null): string
    {
        if ($scopeType === self::PRODUCT) {
            return 'product:'.(int) $productId;
        }
        if ($scopeType === self::SUBCATEGORY) {
            return 'subcategory:'.$category.':'.self::normSub($subcategory);
        }

        return 'category:'.$category;
    }

    public static function label(string $category): string
    {
        return self::LABELS[$category] ?? $category;
    }

    private static function subcategoryOf(Product $product, string $category, string $family): string
    {
        if ($category === self::GAME_KEYS) {
            return (string) (GameKeyPlatform::canonical($product->platform) ?: self::normSub((string) $product->platform));
        }
        if ($category === self::GIFT_CARD) {
            $platform = trim((string) $product->platform);
            if ($platform !== '') {
                return self::normSub($platform);
            }
            $parsed = GiftCardBrand::fromRemote(
                FazerCardsIds::rawCategory((string) $product->fazercards_category_id),
                (string) ($product->name_en ?: $product->title),
            );

            return self::normSub($parsed['platform']);
        }
        if ($category === self::GAME_TOPUP) {
            return self::topupSubcategory($product);
        }
        if ($category === self::TELEGRAM) {
            return $family === FazerCardsIds::TELEGRAM_PREMIUM ? 'premium' : 'stars';
        }

        return '';
    }

    public static function normSub(?string $value): string
    {
        $slug = mb_strtolower(trim((string) $value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    /**
     * گروه‌های کاتالوگ شارژ مستقیم همگی slug والد `game-topup` دارند؛
     * زیرگروه راهنما از handle محصول با map.json هم‌خوان می‌شود.
     *
     * @var array<string, list<string>>
     */
    private const TOPUP_HANDLE_MARKERS = [
        'pubg-mobile' => ['pubg-mobile'],
        'free-fire' => ['free-fire'],
        'mobile-legends' => ['mobile-legends'],
        'call-of-duty-mobile' => ['call-of-duty-mobile', 'codm'],
        'hoyoverse' => ['genshin', 'honkai-impact', 'honkai-star-rail', 'zenless-zone-zero'],
        'riot-games' => ['valorant', 'wild-rift', 'tft-mobile', 'legends-of-runeterra', 'lol-id', 'lol-kh', 'lol-my', 'lol-ph', 'lol-sg', 'lol-th', 'lol-vn'],
        'honor-of-kings' => ['honor-of-kings'],
        'delta-force' => ['delta-force'],
        'ea-fc-mobile' => ['eafc-mobile', 'ea-fc-mobile'],
        'live-and-social-apps' => [
            'bigo-live', 'capcut', 'chamet', 'dazz-live', 'firefly-live', 'hago', 'heesay', 'idol-party',
            'imo', 'jaco', 'likee', 'lita', 'livu', 'mangatoon', 'mico', 'migo-live', 'nimo-tv',
            'poppo-live', 'qq-coin', 'sawa-play', 'soulchill', 'starmaker', 'sugo-online', 'tik-tok',
            'toptop', 'wesing', 'yoho-group',
        ],
    ];

    private static function topupSubcategory(Product $product): string
    {
        $handle = self::normSub((string) $product->handle);
        foreach (self::TOPUP_HANDLE_MARKERS as $slug => $needles) {
            foreach ($needles as $needle) {
                if (self::handleHasMarker($handle, $needle)) {
                    return $slug;
                }
            }
        }
        if (preg_match('/(?:^|-)yalla$/', $handle) === 1) {
            return 'live-and-social-apps';
        }
        $group = $product->relationLoaded('catalogGroup') ? $product->catalogGroup : $product->catalogGroup()->first();
        $groupSlug = self::normSub((string) ($group?->slug ?: ''));
        if ($groupSlug !== '' && $groupSlug !== self::GAME_TOPUP && isset(self::TOPUP_HANDLE_MARKERS[$groupSlug])) {
            return $groupSlug;
        }

        return 'other-games-generic';
    }

    private static function handleHasMarker(string $handle, string $needle): bool
    {
        $needle = self::normSub($needle);
        if ($needle === '') {
            return false;
        }
        if ($handle === $needle) {
            return true;
        }

        return (bool) preg_match('/(?:^|-)'.preg_quote($needle, '/').'(?:-|$)/', $handle);
    }
}

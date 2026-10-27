<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indexable brand landing pages that reuse the category browse template.
 * Products are selected by handle/platform rules, not a separate UI.
 */
final class BrandHub
{
    /**
     * @var array<string, array{fa: string, en: string, platform: string, patterns: list<string>}>
     */
    public const HUBS = [
        'google-play' => [
            'fa' => 'گوگل پلی',
            'en' => 'Google Play',
            'platform' => 'google-play',
            'patterns' => ['giftcard-google-play%', 'google-play%'],
        ],
        'call-of-duty-mobile' => [
            'fa' => 'کالاف دیوتی موبایل',
            'en' => 'Call of Duty Mobile',
            'platform' => 'cod-mobile',
            'patterns' => ['cod-mobile%', 'topup-cod-mobile%', '%call-of-duty-mobile%'],
        ],
        'free-fire' => [
            'fa' => 'فری فایر',
            'en' => 'Free Fire',
            'platform' => 'free-fire',
            'patterns' => ['%free-fire%', 'giftcard-garena-free-fire%'],
        ],
        'apple-itunes' => [
            'fa' => 'اپل آیتونز',
            'en' => 'Apple iTunes',
            'platform' => 'itunes',
            'patterns' => ['giftcard-app-store-itunes%', 'giftcard-itunes%', 'app-store-itunes%'],
        ],
        'playstation' => [
            'fa' => 'پلی استیشن',
            'en' => 'PlayStation',
            'platform' => 'playstation',
            'patterns' => ['giftcard-playstation%', 'playstation%'],
        ],
        'xbox' => [
            'fa' => 'ایکس باکس',
            'en' => 'Xbox',
            'platform' => 'xbox',
            'patterns' => ['giftcard-xbox%', 'giftcard-microsoft-windows%'],
        ],
        'valorant' => [
            'fa' => 'ولورانت',
            'en' => 'Valorant',
            'platform' => 'valorant',
            'patterns' => ['giftcard-valorant%', 'valorant%', 'topup-valorant%'],
        ],
    ];

    public static function is(string $slug): bool
    {
        return isset(self::HUBS[$slug]);
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::HUBS);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public static function constrain(Builder $query, string $slug): void
    {
        $hub = self::HUBS[$slug] ?? null;
        if ($hub === null) {
            $query->whereRaw('1 = 0');

            return;
        }
        $patterns = $hub['patterns'];
        $platform = $hub['platform'];
        $query->where(function (Builder $inner) use ($patterns, $platform): void {
            foreach ($patterns as $index => $pattern) {
                if ($index === 0) {
                    $inner->where('handle', 'like', $pattern);
                } else {
                    $inner->orWhere('handle', 'like', $pattern);
                }
            }
            if ($platform !== '') {
                $inner->orWhereRaw('LOWER(platform) = ?', [strtolower($platform)]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function toNavApi(string $slug, int $productsCount = 0): array
    {
        $hub = self::HUBS[$slug] ?? null;
        if ($hub === null) {
            return [];
        }

        return [
            'id' => -(crc32($slug) & 0x7fffffff),
            'slug' => $slug,
            'title' => $hub['fa'],
            'titleEn' => $hub['en'],
            'description' => null,
            'seoTitle' => 'خرید '.$hub['fa'].' ('.$hub['en'].') با تومان | تحویل فوری',
            'seoDescription' => 'خرید '.$hub['fa'].' با تومان و تحویل فوری از شارژینو. پشتیبانی پاسخگوست؛ پرداخت ریالی بدون رمز اکانت.',
            'imageUrl' => '',
            'productsCount' => $productsCount,
            'updatedAt' => now()->toAtomString(),
        ];
    }
}

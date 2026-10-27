<?php

namespace App\Services\Torob;

use App\Models\Product;

/**
 * Low-risk-first ordering for rollout stage files (mirrors enable dry-run brand priority).
 */
final class TorobRolloutPriority
{
    /**
     * @var list<string>
     */
    private const FAMILY_ORDER = [
        'rewarble',
        'razer',
        'google-play',
        'itunes',
        'playstation',
        'xbox',
        'steam',
        'amazon',
        'netflix',
        'spotify',
        'telegram',
        'topup-known',
        'other',
    ];

    public static function giftBrandFamily(Product $product): string
    {
        $handle = mb_strtolower(trim((string) $product->handle));
        if (str_contains($handle, 'rewarble')) {
            return 'rewarble';
        }
        if (str_contains($handle, 'razer')) {
            return 'razer';
        }
        if (str_contains($handle, 'google-play')) {
            return 'google-play';
        }
        if (str_contains($handle, 'itunes') || str_contains($handle, 'app-store')) {
            return 'itunes';
        }
        if (str_starts_with($handle, 'giftcard-playstation')) {
            return 'playstation';
        }
        if (str_starts_with($handle, 'giftcard-xbox')) {
            return 'xbox';
        }
        if (str_starts_with($handle, 'giftcard-steam')) {
            return 'steam';
        }
        if (str_starts_with($handle, 'giftcard-amazon')) {
            return 'amazon';
        }
        if (str_starts_with($handle, 'giftcard-netflix')) {
            return 'netflix';
        }
        if (str_contains($handle, 'spotify')) {
            return 'spotify';
        }

        return 'other';
    }

    public static function scopeFamily(Product $product, string $slug): string
    {
        if ($slug === 'telegram') {
            return 'telegram';
        }
        if ($slug === 'game-topup') {
            $needle = app(TorobFeedCatalogCopy::class)->brandNeedle($product);
            if ($needle !== null && isset(config('torob_split_copy.brand_units', [])[$needle])) {
                return 'topup-known';
            }

            return 'other';
        }

        return self::giftBrandFamily($product);
    }

    public static function familyRank(string $family): int
    {
        $idx = array_search($family, self::FAMILY_ORDER, true);

        return $idx === false ? count(self::FAMILY_ORDER) : $idx;
    }

    public static function giftBrandScore(Product $product): int
    {
        $handle = mb_strtolower(trim((string) $product->handle));
        $family = self::giftBrandFamily($product);
        $base = match ($family) {
            'rewarble' => str_contains($handle, 'eur') ? 100 : 70,
            'razer' => 95,
            'google-play' => 92,
            'itunes' => 90,
            'playstation' => 86,
            'xbox' => 84,
            'steam' => 82,
            'amazon' => 80,
            'netflix' => 78,
            'spotify' => 76,
            default => 0,
        };
        foreach (['-us', '-global', '-eu', '-uk', '-de'] as $i => $suffix) {
            if (str_ends_with($handle, $suffix)) {
                $base += 10 - $i;
            }
        }

        return $base;
    }

    /**
     * @param  array<int, int>  $extrasById
     * @return array<int, int>
     */
    public static function sortExtrasByRolloutRisk(array $extrasById, string $scopeSlug): array
    {
        if ($extrasById === []) {
            return [];
        }
        $ids = array_keys($extrasById);
        $products = Product::query()
            ->with('catalogGroup')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        usort($ids, static function (int $a, int $b) use ($products, $scopeSlug, $extrasById): int {
            $pa = $products->get($a);
            $pb = $products->get($b);
            if (! $pa instanceof Product || ! $pb instanceof Product) {
                return $a <=> $b;
            }
            $fa = self::scopeFamily($pa, $scopeSlug);
            $fb = self::scopeFamily($pb, $scopeSlug);
            $rank = self::familyRank($fa) <=> self::familyRank($fb);
            if ($rank !== 0) {
                return $rank;
            }
            $score = self::giftBrandScore($pb) <=> self::giftBrandScore($pa);
            if ($score !== 0) {
                return $score;
            }
            $extra = ($extrasById[$b] ?? 0) <=> ($extrasById[$a] ?? 0);
            if ($extra !== 0) {
                return $extra;
            }

            return $a <=> $b;
        });
        $sorted = [];
        foreach ($ids as $id) {
            $sorted[$id] = (int) ($extrasById[$id] ?? 0);
        }

        return $sorted;
    }
}

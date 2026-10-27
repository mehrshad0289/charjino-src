<?php

namespace App\Modules\SmartSearch;

use App\Models\Product;
use App\Modules\Catalog\CatalogAttr;
use App\Modules\Catalog\GameKeyPlatform;
use App\Modules\Supplier\FazerCards\FazerCardsIds;

final class ProductSearchDocumentFactory
{
    /**
     * @return array<string, mixed>|null
     */
    public static function make(Product $product): ?array
    {
        $product->loadMissing(['catalogGroup', 'searchTags', 'skus']);
        if (! StorefrontSearchEligibility::productIsEligible($product)) {
            return null;
        }
        $group = $product->catalogGroup;
        $platform = GameKeyPlatform::canonical((string) $product->platform)
            ?? CatalogAttr::publicPlatform((string) $product->platform);
        $labels = [];
        $regions = RegionParser::parse((string) $product->region, (string) $product->title, (string) $product->name_en);
        foreach ($product->skus as $sku) {
            if ($sku->status !== 'active' || ! $sku->visible) {
                continue;
            }
            foreach ([$sku->name, $sku->name_en] as $name) {
                $label = PersianTextNormalizer::normalize((string) $name);
                if ($label !== '') {
                    $labels[$label] = $label;
                }
            }
            $regions = array_values(array_unique([...$regions, ...RegionParser::parse((string) $sku->region, (string) $sku->name)]));
        }
        $aliases = [];
        foreach ($product->searchTags as $tag) {
            $aliases[] = PersianTextNormalizer::normalize((string) $tag->label);
            foreach ((array) $tag->aliases as $alias) {
                $aliases[] = PersianTextNormalizer::normalize((string) $alias);
            }
        }
        $groupSlug = $group?->slug ?: '';
        [$productType, $typeRank] = self::typeOf($product, $groupSlug);
        $isSteam = str_contains(mb_strtolower($groupSlug.' '.$platform.' '.$product->handle), 'steam') ? 1 : 0;

        return [
            'id' => SearchDocumentId::ofHandle((string) $product->handle),
            'handle' => (string) $product->handle,
            'title' => PersianTextNormalizer::normalize($product->storefrontTitle()),
            'name_en' => PersianTextNormalizer::normalize((string) $product->name_en),
            'aliases' => array_values(array_unique(array_filter($aliases))),
            'group_slug' => $groupSlug,
            'group_name' => PersianTextNormalizer::normalize((string) ($group?->storefrontTitle() ?: $product->category_name)),
            'platform' => (string) ($platform ?: ''),
            'regions' => $regions,
            'product_type' => $productType,
            'type_rank' => $typeRank,
            'is_steam' => $isSteam,
            'is_popular' => $product->is_popular ? 1 : 0,
            'packages_labels' => array_values($labels),
        ];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function typeOf(Product $product, string $groupSlug): array
    {
        $ft = (string) $product->fulfillment_type;
        $blob = mb_strtolower($groupSlug.' '.$product->title.' '.$product->name_en);
        if (str_contains($blob, 'dlc') || str_contains($blob, 'soundtrack') || str_contains($blob, 'pack')) {
            return ['dlc', 1];
        }
        if ($groupSlug === 'gift-card' || str_contains($blob, 'gift')) {
            return ['giftcard', 2];
        }
        if ($groupSlug === 'game-topup' || $ft === FazerCardsIds::TOPUP) {
            return ['topup', 2];
        }
        if ($groupSlug === 'game-keys' || $ft === FazerCardsIds::GAMEKEY) {
            return ['key', 2];
        }

        return ['game', 3];
    }
}

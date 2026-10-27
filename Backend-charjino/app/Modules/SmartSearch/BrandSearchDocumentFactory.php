<?php

namespace App\Modules\SmartSearch;

use App\Models\SearchTag;
use App\Modules\Catalog\GiftCardBrand;
use App\Modules\Catalog\GameKeyPlatform;
use Illuminate\Database\Eloquent\Builder;

final class BrandSearchDocumentFactory
{
    /**
     * @return array<string, mixed>|null
     */
    public static function fromTag(SearchTag $tag): ?array
    {
        if (! ($tag->is_active ?? true) || ($tag->kind ?? 'tag') !== 'brand') {
            return null;
        }
        $count = (int) $tag->products()
            ->where(fn (Builder $q) => StorefrontSearchEligibility::constrainProducts($q))
            ->count();
        if ($count < 1) {
            return null;
        }
        $aliases = array_map(fn ($a) => PersianTextNormalizer::normalize((string) $a), (array) $tag->aliases);
        $group = $tag->catalogGroup;

        return [
            'id' => SearchDocumentId::make((string) $tag->slug),
            'slug' => (string) $tag->slug,
            'name' => PersianTextNormalizer::normalize((string) $tag->label),
            'name_en' => '',
            'aliases' => array_values(array_unique(array_filter($aliases))),
            'group_slug' => $group?->slug,
            'active_products_count' => $count,
            'sort_order' => (int) ($tag->sort_order ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fromPlatforms(): array
    {
        $rows = [];
        $counts = \App\Models\Product::query()
            ->select('platform')
            ->selectRaw('COUNT(id) as active_count')
            ->tap(fn (Builder $q) => StorefrontSearchEligibility::constrainProducts($q))
            ->whereNotNull('platform')
            ->where('platform', '!=', '')
            ->groupBy('platform')
            ->pluck('active_count', 'platform');
        $merged = [];
        foreach ($counts as $platform => $count) {
            $id = GameKeyPlatform::canonical((string) $platform);
            if ($id === null || $id === '') {
                continue;
            }
            if (! isset(GameKeyPlatform::LABELS[$id]) && ! isset(GiftCardBrand::LABELS[$id])) {
                continue;
            }
            $merged[$id] = ($merged[$id] ?? 0) + (int) $count;
        }
        foreach ($merged as $id => $count) {
            if ($count < 1) {
                continue;
            }
            $rows[] = [
                'id' => SearchDocumentId::make('platform', $id),
                'slug' => $id,
                'name' => PersianTextNormalizer::normalize(GiftCardBrand::label($id) ?: GameKeyPlatform::label($id) ?: $id),
                'name_en' => $id,
                'aliases' => [$id],
                'group_slug' => null,
                'active_products_count' => $count,
                'sort_order' => 0,
            ];
        }

        return $rows;
    }
}

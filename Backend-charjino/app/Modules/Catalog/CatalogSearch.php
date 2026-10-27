<?php

namespace App\Modules\Catalog;

use Illuminate\Database\Eloquent\Builder;

final class CatalogSearch
{
    /**
     * @param  Builder<\App\Models\Product>  $query
     */
    public static function apply(Builder $query, string $needle, bool $giftCardNarrow = false): void
    {
        $needle = SearchQuery::normalize($needle);
        if ($needle === '') {
            return;
        }
        $like = SearchQuery::likeContains($needle);
        $query->where(function (Builder $inner) use ($like, $giftCardNarrow): void {
            self::matchColumns($inner, $like, $giftCardNarrow);
            $inner->orWhereHas('searchTags', function (Builder $tag) use ($like): void {
                $tag->where('label', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('aliases_text', 'like', $like);
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Product>  $inner
     */
    public static function matchColumns(Builder $inner, string $like, bool $giftCardNarrow = false): void
    {
        if ($giftCardNarrow) {
            $inner->where('title', 'like', $like)
                ->orWhere('name_en', 'like', $like)
                ->orWhere('subtitle', 'like', $like)
                ->orWhere('platform', 'like', $like)
                ->orWhere('handle', 'like', $like);

            return;
        }

        $inner->where('title', 'like', $like)
            ->orWhere('subtitle', 'like', $like)
            ->orWhere('publisher', 'like', $like)
            ->orWhere('handle', 'like', $like)
            ->orWhere('code', 'like', $like)
            ->orWhere('name_en', 'like', $like)
            ->orWhere('category_name', 'like', $like)
            ->orWhereHas('catalogGroup', function (Builder $group) use ($like): void {
                $group->where('title', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->orWhereHas('skus', function (Builder $sku) use ($like): void {
                $sku->where('name', 'like', $like)->orWhere('name_en', 'like', $like);
            });
    }
}

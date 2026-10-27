<?php

namespace App\Modules\SmartSearch;

final class SearchIndexSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function products(): array
    {
        return [
            'searchableAttributes' => [
                'title', 'name_en', 'aliases', 'packages_labels', 'group_name', 'regions', 'platform',
            ],
            'filterableAttributes' => [
                'group_slug', 'platform', 'product_type', 'regions', 'is_steam', 'is_popular',
            ],
            'sortableAttributes' => ['type_rank', 'is_steam', 'is_popular'],
            'rankingRules' => [
                'words', 'typo', 'proximity', 'attributeRank', 'wordPosition', 'exactness',
                'type_rank:desc', 'is_steam:asc', 'is_popular:desc',
            ],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 5, 'twoTypos' => 8],
                'disableOnNumbers' => true,
                'disableOnWords' => ['GTA', 'PSN', 'COD', 'CODM', 'UC', 'FF', 'CP', 'ZZZ', 'HSR', 'FC', 'EA'],
            ],
            'pagination' => ['maxTotalHits' => 1000],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function brands(): array
    {
        return [
            'searchableAttributes' => ['name', 'name_en', 'aliases', 'slug'],
            'filterableAttributes' => ['group_slug'],
            'sortableAttributes' => ['sort_order', 'active_products_count'],
            'rankingRules' => [
                'words', 'typo', 'proximity', 'attributeRank', 'wordPosition', 'exactness',
                'sort_order:asc', 'active_products_count:desc',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taxonomies(): array
    {
        $base = self::brands();
        $base['filterableAttributes'][] = 'kind';
        $base['searchableAttributes'] = ['name', 'name_en', 'aliases', 'slug'];

        return $base;
    }
}

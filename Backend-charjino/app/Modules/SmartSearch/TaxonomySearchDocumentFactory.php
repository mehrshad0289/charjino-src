<?php

namespace App\Modules\SmartSearch;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Modules\Catalog\GameKeyPlatform;
use Illuminate\Database\Eloquent\Builder;

final class TaxonomySearchDocumentFactory
{
    /**
     * @return array<string, mixed>|null
     */
    public static function fromGroup(CatalogGroup $group): ?array
    {
        if (! $group->visible) {
            return null;
        }
        $count = (int) $group->products()
            ->where(fn (Builder $q) => StorefrontSearchEligibility::constrainProducts($q))
            ->count();
        if ($count < 1) {
            return null;
        }

        return [
            'id' => SearchDocumentId::make('group', $group->slug),
            'kind' => 'group',
            'slug' => $group->slug,
            'name' => PersianTextNormalizer::normalize($group->storefrontTitle()),
            'name_en' => PersianTextNormalizer::normalize((string) $group->name_en),
            'aliases' => array_values(array_filter([
                PersianTextNormalizer::normalize((string) $group->name),
                PersianTextNormalizer::normalize((string) $group->slug),
            ])),
            'group_slug' => $group->slug,
            'active_products_count' => $count,
            'sort_order' => (int) $group->sort_order,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function platforms(): array
    {
        $out = [];
        foreach (BrandSearchDocumentFactory::fromPlatforms() as $row) {
            $slug = (string) $row['slug'];
            $out[] = [
                'id' => SearchDocumentId::make('platform', $slug),
                'kind' => 'platform',
                'slug' => $slug,
                'name' => $row['name'],
                'name_en' => $row['name_en'],
                'aliases' => $row['aliases'],
                'group_slug' => GameKeyPlatform::GROUP_SLUG,
                'active_products_count' => $row['active_products_count'],
                'sort_order' => $row['sort_order'],
            ];
        }

        return $out;
    }
}

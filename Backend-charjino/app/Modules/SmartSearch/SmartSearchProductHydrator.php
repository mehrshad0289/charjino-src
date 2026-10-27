<?php

namespace App\Modules\SmartSearch;

use App\Models\Product;
use App\Modules\Catalog\CatalogAttr;
use App\Modules\Catalog\GameKeyPlatform;
use App\Modules\SmartSearch\Data\SmartSearchCandidates;

final class SmartSearchProductHydrator
{
    /**
     * @param  list<array<string, mixed>>  $hits
     * @return list<array<string, mixed>>
     */
    public static function products(array $hits, string $query, bool $includePrice): array
    {
        $handles = [];
        $packageByHandle = [];
        foreach ($hits as $hit) {
            $handle = (string) ($hit['handle'] ?? $hit['id'] ?? '');
            if ($handle === '' || isset($handles[$handle])) {
                continue;
            }
            $handles[$handle] = $handle;
            $labels = is_array($hit['packages_labels'] ?? null) ? $hit['packages_labels'] : [];
            $packageByHandle[$handle] = PackageLabelMatcher::match($query, array_map('strval', $labels));
        }
        if ($handles === []) {
            return [];
        }
        $products = Product::query()
            ->with(['catalogGroup', 'searchTags', 'skus'])
            ->whereIn('handle', array_values($handles))
            ->get()
            ->keyBy('handle');
        $items = [];
        foreach ($handles as $handle) {
            $product = $products->get($handle);
            if (! $product instanceof Product || ! StorefrontSearchEligibility::productIsEligible($product)) {
                if (SmartSearchSyncService::writesAreEnabled() && $handle !== '') {
                    \App\Jobs\SmartSearch\SyncProductSearchDocument::dispatch(null, $handle);
                }
                continue;
            }
            $platform = GameKeyPlatform::canonical((string) $product->platform)
                ?? CatalogAttr::publicPlatform((string) $product->platform);
            $item = [
                'result_type' => 'product',
                'id' => $product->id,
                'handle' => $product->handle,
                'title' => $product->storefrontTitle(),
                'title_en' => $product->name_en ?: null,
                'group' => $product->catalogGroup?->storefrontTitle() ?: (string) $product->category_name,
                'categoryName' => $product->catalogGroup?->storefrontTitle() ?: (string) $product->category_name,
                'platform' => $platform ?: null,
                'thumbnail' => $product->displayImage(),
                'product_type' => (string) ($product->catalogGroup?->slug ?: 'game'),
                'matched_package_label' => $packageByHandle[$handle] ?? null,
                'href' => '/catalog/'.$product->handle,
            ];
            if ($includePrice) {
                $item['minPrice'] = $product->storefrontMinPrice();
            }
            $items[] = $item;
        }
        $wantedRegions = RegionParser::parse($query);
        if ($wantedRegions !== [] && $items !== []) {
            usort($items, function (array $a, array $b) use ($wantedRegions, $products): int {
                $score = function (array $row) use ($wantedRegions, $products): int {
                    $product = $products->get((string) ($row['handle'] ?? ''));
                    if (! $product instanceof Product) {
                        return 0;
                    }

                    return count(array_intersect(
                        $wantedRegions,
                        RegionParser::parse((string) $product->region, (string) $product->title, (string) $product->name_en),
                    ));
                };

                return $score($b) <=> $score($a);
            });
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return list<array<string, mixed>>
     */
    public static function references(array $hits): array
    {
        $items = [];
        foreach ($hits as $hit) {
            $kind = (string) ($hit['kind'] ?? '');
            $slug = (string) ($hit['slug'] ?? $hit['id'] ?? '');
            $name = (string) ($hit['name'] ?? '');
            if ($slug === '' || $name === '') {
                continue;
            }
            if (str_starts_with((string) ($hit['id'] ?? ''), 'platform__')) {
                $kind = 'platform';
            } elseif (str_starts_with((string) ($hit['id'] ?? ''), 'group__')) {
                $kind = 'group';
            } elseif ($kind === '') {
                $kind = 'brand';
            }
            $group = is_string($hit['group_slug'] ?? null) ? (string) $hit['group_slug'] : '';
            $href = match ($kind) {
                'group' => '/catalog/category/'.$slug,
                'platform' => '/catalog/category/'.GameKeyPlatform::GROUP_SLUG.'?platform='.rawurlencode($slug),
                default => $group !== ''
                    ? '/catalog/category/'.$group.'?q='.rawurlencode($name)
                    : '/catalog/category?q='.rawurlencode($name),
            };
            $items[] = [
                'result_type' => $kind === 'group' ? 'group' : ($kind === 'platform' ? 'platform' : 'brand'),
                'slug' => $slug,
                'name' => $name,
                'name_en' => (string) ($hit['name_en'] ?? ''),
                'group' => $group !== '' ? $group : null,
                'active_products_count' => (int) ($hit['active_products_count'] ?? 0),
                'href' => $href,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function payload(string $mode, string $query, array $items, int $page, int $lastPage, bool $hasMore): SmartSearchCandidates
    {
        return new SmartSearchCandidates($mode, $query, $items, [
            'current_page' => $page,
            'last_page' => $lastPage,
            'has_more_pages' => $hasMore,
        ]);
    }
}

<?php

namespace App\Modules\Catalog;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\SearchTag;
use Illuminate\Database\Eloquent\Builder;

final class SmartSearchService
{
    /**
     * @var array<string, list<string>>
     */
    private const BRAND_FA = [
        'playstation' => ['پلی‌استیشن', 'پلی استیشن'],
        'xbox' => ['ایکس‌باکس', 'ایکس باکس'],
        'pc' => ['پی‌سی', 'پی سی'],
        'steam' => ['استیم'],
        'nintendo' => ['نینتندو'],
        'google-play' => ['گوگل پلی'],
        'itunes' => ['اپ استور'],
        'roblox' => ['روبلاکس'],
        'pubg' => ['پابجی'],
        'pubg-mobile' => ['پابجی موبایل'],
        'fortnite' => ['فورتنایت'],
        'valorant' => ['ولورانت'],
        'telegram' => ['تلگرام'],
        'ea' => ['ای‌ای'],
        'epic' => ['اپیک'],
        'ubisoft' => ['یوبیسافت'],
        'rockstar' => ['راکستار'],
    ];

    /**
     * @return array{
     *     mode: string,
     *     query: string,
     *     items: list<array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, has_more_pages: bool}
     * }
     */
    public function search(string $raw, int $page = 1, int $perPage = 10, string $mode = 'auto'): array
    {
        $query = SearchQuery::normalize($raw);
        $page = max(1, $page);
        $perPage = max(1, min(30, $perPage));
        if (mb_strlen($query) < 2) {
            return $this->payload('empty', $query, [], $page, 1, false);
        }

        $forceProducts = in_array($mode, ['products', 'ads'], true);
        if (! $forceProducts) {
            $entities = $this->entities($query, $perPage);
            if ($entities !== []) {
                return $this->payload('entities', $query, $entities, 1, 1, false);
            }
        }

        return $this->products($query, $page, $perPage);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entities(string $query, int $limit): array
    {
        $items = [
            ...$this->categoryEntities($query),
            ...$this->brandEntities($query),
            ...$this->tagEntities($query),
        ];
        usort($items, function (array $a, array $b): int {
            $score = ((int) ($b['score'] ?? 0)) <=> ((int) ($a['score'] ?? 0));
            if ($score !== 0) {
                return $score;
            }

            return ((int) ($b['activeCount'] ?? 0)) <=> ((int) ($a['activeCount'] ?? 0));
        });

        return array_map(function (array $item): array {
            unset($item['score'], $item['activeCount']);

            return $item;
        }, array_slice($items, 0, $limit));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryEntities(string $query): array
    {
        $items = [];
        $groups = CatalogGroup::query()
            ->where('visible', true)
            ->withCount(['products as active_count' => fn (Builder $q) => $this->activeProductConstraint($q)])
            ->get();
        foreach ($groups as $group) {
            $count = (int) $group->active_count;
            if ($count < 1) {
                continue;
            }
            $names = array_filter([
                SearchQuery::normalize((string) $group->storefrontTitle()),
                SearchQuery::normalize((string) $group->name_en),
                SearchQuery::normalize((string) $group->name),
                SearchQuery::normalize((string) $group->slug),
            ]);
            $score = $this->prefixScore($names, $query);
            if ($score < 1) {
                continue;
            }
            $title = $group->storefrontTitle();
            $items[] = [
                'result_type' => 'category',
                'name' => $title,
                'slug' => $group->slug,
                'href' => '/catalog/category/'.$group->slug,
                'score' => $score,
                'activeCount' => $count,
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function brandEntities(string $query): array
    {
        $rawCounts = Product::query()
            ->select('platform')
            ->selectRaw('COUNT(id) as active_count')
            ->where('status', 'active')
            ->where('visible', true)
            ->whereNotNull('platform')
            ->where('platform', '!=', '')
            ->whereHas('skus', fn (Builder $sku) => $sku->where('status', 'active'))
            ->where(function (Builder $inner) {
                $inner->whereNull('catalog_group_id')
                    ->orWhereHas('catalogGroup', fn (Builder $group) => $group->where('visible', true));
            })
            ->groupBy('platform')
            ->pluck('active_count', 'platform');

        $counts = [];
        foreach ($rawCounts as $platform => $count) {
            $id = GameKeyPlatform::canonical((string) $platform) ?? CatalogAttr::publicPlatform((string) $platform);
            if (! is_string($id) || $id === '') {
                continue;
            }
            $counts[$id] = ($counts[$id] ?? 0) + (int) $count;
        }

        $items = [];
        foreach ($counts as $id => $count) {
            if ($count < 1) {
                continue;
            }
            $names = $this->brandNames($id);
            $score = $this->prefixScore($names, $query);
            if ($score < 1) {
                continue;
            }
            $label = GiftCardBrand::label($id);
            $fa = self::BRAND_FA[$id][0] ?? null;
            $items[] = [
                'result_type' => 'brand',
                'name' => $fa ?: $label,
                'slug' => $id,
                'href' => $this->brandHref($id),
                'score' => $score,
                'activeCount' => $count,
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tagEntities(string $query): array
    {
        $allowSlug = mb_strlen($query) >= 4;
        $tags = SearchTag::query()
            ->withCount(['products as active_count' => fn (Builder $q) => $this->activeProductConstraint($q)])
            ->get();
        $items = [];
        foreach ($tags as $tag) {
            $count = (int) $tag->active_count;
            if ($count < 1) {
                continue;
            }
            $names = [SearchQuery::normalize((string) $tag->label)];
            if ($allowSlug) {
                $names[] = SearchQuery::normalize((string) $tag->slug);
            }
            $score = $this->prefixScore($names, $query);
            if ($score < 1) {
                continue;
            }
            $items[] = [
                'result_type' => 'tag',
                'name' => $tag->label,
                'slug' => $tag->slug,
                'href' => '/catalog/category?q='.rawurlencode($tag->label),
                'score' => $score,
                'activeCount' => $count,
            ];
        }

        return $items;
    }

    /**
     * @return array{
     *     mode: string,
     *     query: string,
     *     items: list<array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, has_more_pages: bool}
     * }
     */
    private function products(string $query, int $page, int $perPage): array
    {
        $tokens = SearchQuery::tokens($query);
        if ($tokens === []) {
            return $this->payload('empty', $query, [], $page, 1, false);
        }
        $required = SearchQuery::requiredHits(count($tokens));
        $builder = $this->storefrontQuery()->with(['catalogGroup', 'searchTags']);
        $builder->where(function (Builder $outer) use ($tokens): void {
            foreach ($tokens as $token) {
                $like = SearchQuery::likeContains($token);
                $outer->orWhere(function (Builder $inner) use ($like): void {
                    CatalogSearch::matchColumns($inner, $like, false);
                    $inner->orWhereHas('searchTags', function (Builder $tag) use ($like): void {
                        $tag->where('label', 'like', $like)
                            ->orWhere('slug', 'like', $like)
                            ->orWhere('aliases_text', 'like', $like);
                    });
                });
            }
        });
        $candidates = $builder->limit(200)->get();
        $ranked = [];
        foreach ($candidates as $product) {
            if (! $product instanceof Product) {
                continue;
            }
            $haystack = $this->productHaystack($product);
            if ($this->tokenHits($haystack, $tokens) < $required) {
                continue;
            }
            $ranked[] = [
                'score' => $this->score($haystack, $query, $tokens),
                'popular' => $product->is_popular ? 1 : 0,
                'id' => $product->id,
                'item' => [
                    'result_type' => 'product',
                    'id' => $product->id,
                    'handle' => $product->handle,
                    'title' => $product->storefrontTitle(),
                    'thumbnail' => $product->displayImage(),
                    'categoryName' => $product->catalogGroup?->storefrontTitle() ?: (string) $product->category_name,
                    'minPrice' => $product->storefrontMinPrice(),
                    'href' => '/catalog/'.$product->handle,
                ],
            ];
        }
        usort($ranked, function (array $a, array $b): int {
            $score = $b['score'] <=> $a['score'];
            if ($score !== 0) {
                return $score;
            }
            $popular = $b['popular'] <=> $a['popular'];
            if ($popular !== 0) {
                return $popular;
            }

            return $b['id'] <=> $a['id'];
        });
        $total = count($ranked);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $slice = array_slice($ranked, ($page - 1) * $perPage, $perPage);
        $items = array_map(fn (array $row) => $row['item'], $slice);
        $mode = $items === [] ? 'empty' : 'products';

        return $this->payload($mode, $query, $items, $page, $lastPage, $page < $lastPage);
    }

    /**
     * @param  list<string>  $names
     */
    private function prefixScore(array $names, string $query): int
    {
        $best = 0;
        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }
            if (SearchQuery::startsWith($name, $query)) {
                $best = max($best, mb_strlen($query) === mb_strlen($name) ? 12 : 10);
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private function brandNames(string $id): array
    {
        $names = [
            SearchQuery::normalize(GiftCardBrand::label($id)),
            SearchQuery::normalize(GameKeyPlatform::label($id)),
            SearchQuery::normalize($id),
        ];
        foreach (self::BRAND_FA[$id] ?? [] as $fa) {
            $names[] = SearchQuery::normalize($fa);
        }

        return array_values(array_unique(array_filter($names)));
    }

    private function brandHref(string $id): string
    {
        $group = CatalogGroup::query()
            ->where('visible', true)
            ->whereHas('products', function (Builder $q) use ($id): void {
                $this->activeProductConstraint($q);
                GameKeyPlatform::applyFilter($q, $id);
            })
            ->orderByRaw("CASE slug WHEN 'game-keys' THEN 0 WHEN 'gift-card' THEN 1 ELSE 2 END")
            ->first();
        if ($group) {
            return '/catalog/category/'.$group->slug.'?platform='.rawurlencode($id);
        }

        return '/catalog/category?q='.rawurlencode($id);
    }

    /**
     * @param  Builder<\App\Models\Product>  $query
     */
    private function activeProductConstraint(Builder $query): void
    {
        $query->where('status', 'active')
            ->where('visible', true)
            ->whereHas('skus', fn (Builder $sku) => $sku->where('status', 'active'));
    }

    /**
     * @return Builder<Product>
     */
    private function storefrontQuery(): Builder
    {
        return Product::query()
            ->with(['skus' => fn ($q) => $q->where('status', 'active')->where('visible', true)])
            ->where('status', 'active')
            ->where('visible', true)
            ->whereHas('skus', fn (Builder $q) => $q->where('status', 'active'))
            ->where(function (Builder $inner) {
                $inner->whereNull('catalog_group_id')
                    ->orWhereHas('catalogGroup', fn (Builder $group) => $group->where('visible', true));
            });
    }

    private function productHaystack(Product $product): string
    {
        $parts = [
            $product->title,
            $product->name_en,
            $product->subtitle,
            $product->publisher,
            $product->platform,
            $product->handle,
            $product->code,
            $product->category_name,
            $product->catalogGroup?->title,
            $product->catalogGroup?->name_en,
            $product->catalogGroup?->name,
            $product->catalogGroup?->slug,
        ];
        foreach ($product->searchTags as $tag) {
            $parts[] = $tag->label;
            $parts[] = $tag->aliases_text;
        }

        return mb_strtolower(SearchQuery::normalize(implode(' ', array_filter($parts, fn ($part) => filled($part)))));
    }

    /**
     * @param  list<string>  $tokens
     */
    private function tokenHits(string $haystack, array $tokens): int
    {
        $hits = 0;
        foreach ($tokens as $token) {
            if ($this->tokenPresent($haystack, $token)) {
                $hits++;
            }
        }

        return $hits;
    }

    private function tokenPresent(string $haystack, string $token): bool
    {
        $token = mb_strtolower($token);
        if ($token === '') {
            return false;
        }
        if (mb_strlen($token) <= 2) {
            return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($token, '/').'(?![\p{L}\p{N}])/u', $haystack) === 1;
        }

        return SearchQuery::contains($haystack, $token);
    }

    /**
     * @param  list<string>  $tokens
     */
    private function score(string $haystack, string $query, array $tokens): int
    {
        $q = mb_strtolower($query);
        $score = 0;
        if (SearchQuery::startsWith($haystack, $q)) {
            $score += 10;
        } elseif (SearchQuery::contains($haystack, $q)) {
            $score += 4;
        }
        foreach ($tokens as $token) {
            if ($this->tokenPresent($haystack, $token)) {
                $score += 2;
            }
        }

        return $score;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *     mode: string,
     *     query: string,
     *     items: list<array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, has_more_pages: bool}
     * }
     */
    private function payload(string $mode, string $query, array $items, int $page, int $lastPage, bool $hasMore): array
    {
        return [
            'mode' => $mode,
            'query' => $query,
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'has_more_pages' => $hasMore,
            ],
        ];
    }
}

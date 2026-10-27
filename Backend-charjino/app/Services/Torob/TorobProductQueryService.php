<?php

namespace App\Services\Torob;

use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\CatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TorobProductQueryService
{
    public const CURSOR_ROW_FACTOR = 1_000_000_000;

    public function __construct(
        private TorobProductMapper $mapper,
        private CatalogService $catalog,
        private TorobSplitPolicy $policy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function paginated(int $page, string $sort, ?int $total = null, ?Builder $base = null): array
    {
        $pageSize = max(1, (int) config('torob.page_size', 100));
        $page = max(1, $page);
        $ids = $this->orderedListableIds($sort, $base);
        $counts = $this->splitRowCounts($base);
        $total = $total ?? $this->rowTotal($ids, $counts);
        $maxPages = max(1, (int) ceil($total / $pageSize));
        $rows = $this->rowsWindow($ids, $counts, ($page - 1) * $pageSize, $pageSize);

        return $this->rowsResult($page, $total, $maxPages, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function cursor(?int $cursor, ?int $total = null): array
    {
        $pageSize = max(1, (int) config('torob.page_size', 100));
        $total = $total ?? $this->listableTotal();
        $maxPages = max(1, (int) ceil($total / $pageSize));
        $parsed = $this->parseCursor($cursor);
        $key = (new Product)->getQualifiedKeyName();
        $rows = [];
        $lastProductId = null;
        $consumedFromLast = 0;
        $lastRowCount = 0;

        if ($parsed['product_id'] !== null && $parsed['skip_rows'] > 0) {
            $first = $this->productsByIds([$parsed['product_id']])->get($parsed['product_id']);
            if ($first instanceof Product) {
                $expanded = $this->mapper->toRows($first);
                $lastProductId = (int) $first->id;
                $lastRowCount = count($expanded);
                $consumedFromLast = $parsed['skip_rows'];
                foreach (array_slice($expanded, $parsed['skip_rows']) as $row) {
                    $rows[] = $row;
                    $consumedFromLast++;
                    if (count($rows) >= $pageSize) {
                        break;
                    }
                }
            }
        }

        if (count($rows) < $pageSize) {
            $query = $this->withRelations($this->listableQuery());
            $bound = $parsed['product_id'];
            while (count($rows) < $pageSize) {
                $chunk = (clone $query)
                    ->when($bound !== null, fn (Builder $inner) => $inner->where($key, '<', $bound))
                    ->orderByDesc($key)
                    ->limit($pageSize)
                    ->get();
                if ($chunk->isEmpty()) {
                    break;
                }
                foreach ($chunk as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }
                    $bound = (int) $product->id;
                    $expanded = $this->mapper->toRows($product);
                    $lastProductId = (int) $product->id;
                    $lastRowCount = count($expanded);
                    $consumedFromLast = 0;
                    foreach ($expanded as $row) {
                        $rows[] = $row;
                        $consumedFromLast++;
                        if (count($rows) >= $pageSize) {
                            break 3;
                        }
                    }
                }
            }
        }

        $payload = $this->rowsResult(1, $total, $maxPages, array_slice($rows, 0, $pageSize));
        if (count($rows) >= $pageSize && $lastProductId !== null) {
            $payload['cursor'] = (string) $this->nextCursor($lastProductId, $consumedFromLast, $lastRowCount);
        }

        return $payload;
    }

    public function listableTotal(?Builder $base = null): int
    {
        $query = $base instanceof Builder ? clone $base : $this->listableQuery();

        return (clone $query)->count() + $this->splitSkuExtraCount($base);
    }

    /**
     * @param  list<mixed>  $uniques
     * @return array<string, mixed>
     */
    public function byUniques(array $uniques): array
    {
        $rows = $this->rowsForUniques($uniques);

        return $this->rowsResult(1, count($rows), 1, $rows);
    }

    /**
     * @param  list<mixed>  $urls
     * @return array<string, mixed>
     */
    public function byUrls(array $urls): array
    {
        $rows = $this->rowsForUrls($urls);

        return $this->rowsResult(1, count($rows), 1, $rows);
    }

    /**
     * Union of URL and unique lookups, de-duplicated by page_unique.
     *
     * @param  list<mixed>  $urls
     * @param  list<mixed>  $uniques
     * @return array<string, mixed>
     */
    public function byUrlsAndUniques(array $urls, array $uniques): array
    {
        $rows = $this->uniqueRows(array_merge($this->rowsForUrls($urls), $this->rowsForUniques($uniques)));

        return $this->rowsResult(1, count($rows), 1, $rows);
    }

    /**
     * @return Builder<Product>
     */
    public function listableQuery(): Builder
    {
        $query = $this->catalog->storefrontListableQuery();
        $query->reorder();
        $query->setEagerLoads([]);
        $excluded = array_values(array_filter(array_map('intval', config('torob.excluded_product_ids', []))));
        if ($excluded !== []) {
            $query->whereNotIn($query->getModel()->getQualifiedKeyName(), $excluded);
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    public function explainSqls(?int $cursor = null, array $uniques = [1], array $urls = ['https://charjino.ir/catalog/example']): array
    {
        $pageSize = (int) config('torob.page_size', 100);
        $count = $this->listableQuery()->clone()->toBase()
            ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->selectRaw('count(*) as aggregate');
        $added = $this->withRelations($this->listableQuery());
        $this->applySort($added, 'date_added_desc');
        $updated = $this->withRelations($this->listableQuery());
        $this->applySort($updated, 'date_updated_desc');
        $cursorQuery = $this->withRelations($this->listableQuery());
        if ($cursor !== null && $cursor > 0) {
            $cursorQuery->where($cursorQuery->getModel()->getQualifiedKeyName(), '<', $cursor);
        }
        $cursorQuery->orderByDesc($cursorQuery->getModel()->getQualifiedKeyName())->limit($pageSize);
        $uniquesQuery = $this->withRelations($this->listableQuery());
        $uniquesQuery->whereIn($uniquesQuery->getModel()->getQualifiedKeyName(), $uniques)->limit($pageSize);
        $handles = $this->handlesFromUrls($urls);
        if ($handles === []) {
            $handles = ['example'];
        }
        $urlsQuery = $this->withRelations($this->listableQuery())
            ->whereIn('products.handle', $handles)
            ->limit($pageSize);
        $statsCursor = $this->listableQuery()
            ->select((new Product)->getQualifiedKeyName())
            ->where((new Product)->getQualifiedKeyName(), '>', 0)
            ->orderBy((new Product)->getQualifiedKeyName())
            ->limit($pageSize);

        return [
            'listable_count' => $count->toRawSql(),
            'page_date_added_desc' => $added->take($pageSize)->toRawSql(),
            'page_date_updated_desc' => $updated->take($pageSize)->toRawSql(),
            'cursor_product_id_desc' => $cursorQuery->toRawSql(),
            'by_uniques' => $uniquesQuery->toRawSql(),
            'by_urls' => $urlsQuery->toRawSql(),
            'stats_cursor_id' => $statsCursor->toRawSql(),
        ];
    }

    /**
     * @return array{listable: int, availability_false: int}
     */
    public function availabilityCounts(): array
    {
        $listable = 0;
        $unavailable = 0;
        $this->eachListableCursor(100, function (Collection $products) use (&$listable, &$unavailable): void {
            foreach ($products as $product) {
                foreach ($this->mapper->toRows($product) as $row) {
                    $listable++;
                    if (($row['availability'] ?? null) === false) {
                        $unavailable++;
                    }
                }
            }
        }, true);

        return [
            'listable' => $listable,
            'availability_false' => $unavailable,
        ];
    }

    public function listableCount(): int
    {
        $count = 0;
        $this->eachListableCursor(100, function (Collection $page) use (&$count): void {
            $count += $page->count();
        }, false);

        return $count;
    }

    /**
     * @param  callable(Collection<int, Product>): void  $callback
     */
    public function eachListableCursor(int $size, callable $callback, bool $withRelations = false): void
    {
        $size = 100;
        $lastId = 0;
        $key = (new Product)->getQualifiedKeyName();
        while (true) {
            $query = $this->listableQuery()
                ->where($key, '>', $lastId)
                ->orderBy($key)
                ->limit($size);
            if ($withRelations) {
                $query = $this->withRelations($query);
            } else {
                $query->select($key);
            }
            $page = $query->get();
            if ($page->isEmpty()) {
                break;
            }
            $callback($page);
            $lastId = (int) $page->last()->getKey();
            unset($page);
        }
    }

    private function splitSkuExtraCount(?Builder $base = null): int
    {
        $query = $this->enabledSplitQuery($base);
        if ($query === null) {
            return 0;
        }
        $extra = 0;
        $lastId = 0;
        $key = (new Product)->getQualifiedKeyName();
        while (true) {
            $page = (clone $query)
                ->where($key, '>', $lastId)
                ->orderBy($key)
                ->limit(100)
                ->get();
            if ($page->isEmpty()) {
                break;
            }
            foreach ($page as $product) {
                if (! $product instanceof Product) {
                    continue;
                }
                $extra += max(0, count($this->mapper->toRows($product)) - 1);
            }
            $lastId = (int) $page->last()->getKey();
        }

        return $extra;
    }

    /**
     * @return Builder<Product>|null
     */
    private function enabledSplitQuery(?Builder $base = null): ?Builder
    {
        $ids = $this->policy->enabledProductIds();
        $slugs = $this->policy->enabledGroupSlugs();
        if ($ids === [] && $slugs === []) {
            return null;
        }
        $query = $this->withRelations($base instanceof Builder ? clone $base : $this->listableQuery());
        $query->where(function (Builder $inner) use ($ids, $slugs): void {
            if ($ids !== []) {
                $inner->whereIn((new Product)->getQualifiedKeyName(), $ids);
            }
            if ($slugs !== []) {
                $method = $ids !== [] ? 'orWhereHas' : 'whereHas';
                $inner->{$method}('catalogGroup', fn ($group) => $group->whereIn('slug', $slugs));
            }
        });
        $exclude = $this->policy->excludeProductIds();
        if ($exclude !== []) {
            $query->whereNotIn((new Product)->getQualifiedKeyName(), $exclude);
        }

        return $query;
    }

    /**
     * @param  list<mixed>  $uniques
     * @return list<array<string, mixed>>
     */
    private function rowsForUniques(array $uniques): array
    {
        $parsed = [];
        $productIds = [];
        foreach ($uniques as $unique) {
            $item = $this->mapper->parsePageUnique(trim((string) $unique));
            if ($item === null) {
                continue;
            }
            $parsed[] = $item;
            $productIds[$item['product_id']] = $item['product_id'];
        }
        if ($productIds === []) {
            return [];
        }

        $products = $this->productsByIds(array_values($productIds));
        $rows = [];
        foreach ($parsed as $item) {
            $product = $products->get($item['product_id']);
            if (! $product instanceof Product) {
                continue;
            }
            $want = $this->wantedUniquesForLookup($product, $item['sku_id']);
            foreach ($this->mapper->toRows($product) as $row) {
                if (isset($want[(string) $row['page_unique']])) {
                    $rows[] = $row;
                }
            }
        }

        return $this->uniqueRows($rows);
    }

    /**
     * @param  list<mixed>  $urls
     * @return list<array<string, mixed>>
     */
    private function rowsForUrls(array $urls): array
    {
        /** @var array<string, array{all: bool, sku_ids: array<int, int>}> $lookups */
        $lookups = [];
        $handleOrder = [];
        foreach ($urls as $url) {
            $parsed = $this->mapper->parseProductUrl((string) $url);
            if ($parsed === null) {
                continue;
            }
            $handle = mb_strtolower(trim($parsed['handle']));
            if ($handle === '') {
                continue;
            }
            if (! isset($lookups[$handle])) {
                $lookups[$handle] = ['all' => false, 'sku_ids' => []];
                $handleOrder[] = $handle;
            }
            if ($parsed['sku_id'] === null) {
                $lookups[$handle]['all'] = true;
            } else {
                $lookups[$handle]['sku_ids'][(int) $parsed['sku_id']] = (int) $parsed['sku_id'];
            }
        }
        if ($lookups === []) {
            return [];
        }

        $products = $this->productsByHandles($handleOrder);
        $rows = [];
        foreach ($handleOrder as $handle) {
            $product = $products->get($handle);
            if (! $product instanceof Product) {
                continue;
            }
            $want = $lookups[$handle];
            $expanded = $this->mapper->toRows($product);
            if ($want['all'] || ! $this->mapper->splitsBySku($product)) {
                foreach ($expanded as $row) {
                    $rows[] = $row;
                }

                continue;
            }
            $allowed = [];
            foreach ($this->listedSkusOf($product) as $sku) {
                if (isset($want['sku_ids'][(int) $sku->id])) {
                    $allowed[$this->mapper->pageUniqueForSku($product, $sku)] = true;
                }
            }
            foreach ($expanded as $row) {
                if (isset($allowed[(string) $row['page_unique']])) {
                    $rows[] = $row;
                }
            }
        }

        return $this->uniqueRows($rows);
    }

    /**
     * @return array<string, true>
     */
    private function wantedUniquesForLookup(Product $product, ?int $skuId): array
    {
        if ($skuId === null) {
            return [(string) $product->id => true];
        }
        foreach ($this->listedSkusOf($product) as $sku) {
            if ((int) $sku->id === $skuId) {
                return [$this->mapper->pageUniqueForSku($product, $sku) => true];
            }
        }

        return [];
    }

    /**
     * @return list<Sku>
     */
    private function listedSkusOf(Product $product): array
    {
        return $this->policy->listedSkus($product);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    private function productsByIds(array $ids): Collection
    {
        $query = $this->withRelations($this->listableQuery());

        return $query
            ->whereIn($query->getModel()->getQualifiedKeyName(), $ids)
            ->get()
            ->keyBy(fn (Product $product): int => (int) $product->id);
    }

    /**
     * @param  list<string>  $handles
     * @return Collection<string, Product>
     */
    private function productsByHandles(array $handles): Collection
    {
        $query = $this->withRelations($this->listableQuery());

        return $query
            ->whereIn('products.handle', $handles)
            ->get()
            ->keyBy(fn (Product $product): string => mb_strtolower((string) $product->handle));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function uniqueRows(array $rows): array
    {
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = (string) ($row['page_unique'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @param  list<mixed>  $urls
     * @return list<string>
     */
    private function handlesFromUrls(array $urls): array
    {
        $handles = [];
        foreach ($urls as $url) {
            $parsed = $this->mapper->parseProductUrl((string) $url);
            if ($parsed === null) {
                continue;
            }
            $handle = mb_strtolower(trim($parsed['handle']));
            if ($handle === '' || isset($handles[$handle])) {
                continue;
            }
            $handles[$handle] = $handle;
        }

        return array_values($handles);
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        if ($sort === 'date_updated_desc') {
            $query->orderByRaw(
                'CASE WHEN COALESCE(skus_max_updated_at, products.updated_at, products.created_at) >= COALESCE(products.updated_at, products.created_at) THEN COALESCE(skus_max_updated_at, products.updated_at, products.created_at) ELSE COALESCE(products.updated_at, products.created_at) END DESC'
            )->orderByDesc($query->getModel()->getQualifiedKeyName());

            return;
        }

        $query->orderByDesc('products.created_at')
            ->orderByDesc($query->getModel()->getQualifiedKeyName());
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function withRelations(Builder $query): Builder
    {
        return $query
            ->with([
                'catalogGroup',
                'skus' => fn ($skus) => $skus->forProductPage(),
            ])
            ->withMax('skus', 'updated_at');
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, int>  $counts
     * @return list<array<string, mixed>>
     */
    private function rowsWindow(array $ids, array $counts, int $skip, int $take): array
    {
        if ($take < 1 || $ids === []) {
            return [];
        }
        $collectIds = [];
        $startOffset = 0;
        $emitted = 0;
        $needFrom = max(0, $skip);
        $needTo = $needFrom + $take;
        foreach ($ids as $id) {
            $n = $counts[$id] ?? 1;
            $next = $emitted + $n;
            if ($next <= $needFrom) {
                $emitted = $next;

                continue;
            }
            if ($collectIds === [] && $emitted < $needFrom) {
                $startOffset = $needFrom - $emitted;
            }
            $collectIds[] = $id;
            $emitted = $next;
            if ($emitted >= $needTo) {
                break;
            }
        }
        if ($collectIds === []) {
            return [];
        }

        $products = $this->productsByIds($collectIds);
        $rows = [];
        $offset = $startOffset;
        foreach ($collectIds as $id) {
            $product = $products->get($id);
            if (! $product instanceof Product) {
                continue;
            }
            $expanded = $this->mapper->toRows($product);
            if ($offset > 0) {
                $expanded = array_slice($expanded, $offset);
                $offset = 0;
            }
            foreach ($expanded as $row) {
                $rows[] = $row;
                if (count($rows) >= $take) {
                    return $rows;
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function orderedListableIds(string $sort, ?Builder $base = null): array
    {
        $ttl = max(1, (int) config('torob.cache_ttl', 300));
        $loader = function () use ($sort, $base): array {
            $query = $base instanceof Builder ? (clone $base)->reorder()->setEagerLoads([]) : $this->listableQuery();
            if ($sort === 'date_updated_desc') {
                $query->withMax('skus', 'updated_at');
            }
            $this->applySort($query, $sort);

            return $query
                ->pluck($query->getModel()->getQualifiedKeyName())
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        };
        if ($base instanceof Builder) {
            return $loader();
        }

        return Cache::remember(
            'torob:v3:ids:'.$sort.':'.$this->policy->ruleFingerprint(),
            $ttl,
            $loader,
        );
    }

    /**
     * @return array<int, int>
     */
    private function splitRowCounts(?Builder $base = null): array
    {
        $ttl = max(1, (int) config('torob.cache_ttl', 300));
        $loader = function () use ($base): array {
            $query = $this->enabledSplitQuery($base);
            if ($query === null) {
                return [];
            }
            $counts = [];
            $lastId = 0;
            $key = (new Product)->getQualifiedKeyName();
            while (true) {
                $page = (clone $query)
                    ->where($key, '>', $lastId)
                    ->orderBy($key)
                    ->limit(100)
                    ->get();
                if ($page->isEmpty()) {
                    break;
                }
                foreach ($page as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }
                    $n = count($this->mapper->toRows($product));
                    if ($n > 1) {
                        $counts[(int) $product->id] = $n;
                    }
                }
                $lastId = (int) $page->last()->getKey();
            }

            return $counts;
        };
        if ($base instanceof Builder) {
            return $loader();
        }

        return Cache::remember(
            'torob:v3:rowcounts:'.$this->policy->ruleFingerprint(),
            $ttl,
            $loader,
        );
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, int>  $counts
     */
    private function rowTotal(array $ids, array $counts): int
    {
        $total = 0;
        foreach ($ids as $id) {
            $total += $counts[$id] ?? 1;
        }

        return $total;
    }

    /**
     * @return array{product_id: int|null, skip_rows: int}
     */
    private function parseCursor(?int $cursor): array
    {
        if ($cursor === null || $cursor < 1) {
            return ['product_id' => null, 'skip_rows' => 0];
        }
        if ($cursor >= self::CURSOR_ROW_FACTOR) {
            return [
                'product_id' => $cursor % self::CURSOR_ROW_FACTOR,
                'skip_rows' => intdiv($cursor, self::CURSOR_ROW_FACTOR),
            ];
        }

        return ['product_id' => $cursor, 'skip_rows' => 0];
    }

    private function nextCursor(int $productId, int $consumedFromLast, int $lastRowCount): int
    {
        if ($consumedFromLast > 0 && $consumedFromLast < $lastRowCount) {
            return $consumedFromLast * self::CURSOR_ROW_FACTOR + $productId;
        }

        return $productId;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function rowsResult(int $page, int $total, int $maxPages, array $rows): array
    {
        return [
            'api_version' => 'torob_api_v3',
            'current_page' => $page,
            'total' => $total,
            'max_pages' => $maxPages,
            'products' => $rows,
        ];
    }
}

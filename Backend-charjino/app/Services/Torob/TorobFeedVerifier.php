<?php

namespace App\Services\Torob;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use Carbon\Carbon;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class TorobFeedVerifier
{
    public function __construct(
        private TorobProductMapper $mapper,
        private TorobProductQueryService $query,
        private TorobSplitPolicy $policy,
        private TorobSplitCanonicalStore $canonical,
        private TorobV3Cache $cache,
        private TorobSplitReporter $reporter,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function verify(array $ids = [], ?string $groupSlug = 'telegram', int $priceSample = 8): array
    {
        $started = hrtime(true);
        $products = $this->loadTargets($ids);
        $rowsByProduct = [];
        $allRows = [];
        $summary = [];
        foreach ($products as $product) {
            $rows = $this->mapper->toRows($product);
            $rowsByProduct[(int) $product->id] = $rows;
            foreach ($rows as $row) {
                $allRows[] = $row;
                $summary[] = [
                    'product_id' => (int) $product->id,
                    'page_unique' => $row['page_unique'] ?? null,
                    'page_url' => $row['page_url'] ?? null,
                    'title' => $row['title'] ?? null,
                    'current_price' => $row['current_price'] ?? null,
                    'availability' => $row['availability'] ?? null,
                ];
            }
        }

        $skipReport = $this->skipReasons($products);
        $checks = [
            $this->checkUniques($allRows),
            $this->checkCanonical($products, $rowsByProduct),
            $this->checkAvailability($products, $rowsByProduct),
            $this->checkLookups($products, $rowsByProduct),
            $this->checkPrices($products, $rowsByProduct, $priceSample),
            $this->checkPagination($groupSlug),
            $this->checkTimings($products),
            $this->checkRecentErrors(),
            $skipReport,
        ];
        $failed = array_values(array_filter($checks, fn (array $item): bool => ! $item['pass']));

        return [
            'pass' => $failed === [],
            'failed' => array_column($failed, 'name'),
            'checks' => $checks,
            'skip_reasons' => $skipReport['detail']['items'] ?? [],
            'no_orderable_sku' => $skipReport['detail']['no_orderable_sku'] ?? [],
            'product_count' => count($products),
            'row_count' => count($allRows),
            'rows' => $summary,
            'cache' => $this->cache->inspect(),
            'elapsed_ms' => (int) round((hrtime(true) - $started) / 1e6),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<Product>
     */
    private function loadTargets(array $ids): array
    {
        $query = $this->query->withRelations($this->query->listableQuery());
        if ($ids !== []) {
            $query->whereIn((new Product)->getQualifiedKeyName(), $ids);
        } else {
            $enabled = $this->policy->enabledProductIds();
            if ($enabled !== []) {
                $query->whereIn((new Product)->getQualifiedKeyName(), $enabled);
            } else {
                $query->orderByDesc((new Product)->getQualifiedKeyName())->limit(3);
            }
        }

        return $query->get()->filter(fn ($product) => $product instanceof Product)->values()->all();
    }

    /**
     * Informational: exemption/skip reason per product id. Never fails the verify run.
     *
     * @param  list<Product>  $products
     * @return array<string, mixed>
     */
    private function skipReasons(array $products): array
    {
        $items = [];
        $noOrderable = [];
        $counts = [];
        foreach ($products as $product) {
            $decision = $this->policy->decide($product);
            if ($decision->split) {
                continue;
            }
            $reason = $decision->reason ?? 'unknown';
            $item = [
                'product_id' => (int) $product->id,
                'handle' => (string) $product->handle,
                'reason' => $reason,
            ];
            $items[] = $item;
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
            if ($reason === TorobSplitDecision::REASON_NO_ORDERABLE_SKU) {
                $noOrderable[] = $item;
            }
        }

        return [
            'name' => 'skip_reasons',
            'pass' => true,
            'detail' => [
                'items' => $items,
                'counts' => $counts,
                'no_orderable_sku' => $noOrderable,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function checkUniques(array $rows): array
    {
        $uniques = [];
        $urls = [];
        $titles = [];
        $dupUnique = [];
        $dupUrl = [];
        $dupTitle = [];
        foreach ($rows as $row) {
            $unique = (string) ($row['page_unique'] ?? '');
            $url = (string) ($row['page_url'] ?? '');
            $title = (string) ($row['title'] ?? '');
            if ($unique === '' || isset($uniques[$unique])) {
                $dupUnique[] = $unique;
            }
            $uniques[$unique] = true;
            if ($url === '' || isset($urls[$url])) {
                $dupUrl[] = $url;
            }
            $urls[$url] = true;
            if ($title !== '' && isset($titles[$title])) {
                $dupTitle[] = $title;
            }
            $titles[$title] = true;
        }

        return [
            'name' => 'uniques',
            'pass' => $dupUnique === [] && $dupUrl === [] && $dupTitle === [],
            'detail' => [
                'rows' => count($rows),
                'duplicate_page_unique' => array_values(array_unique($dupUnique)),
                'duplicate_page_url' => array_values(array_unique($dupUrl)),
                'duplicate_title' => array_values(array_unique($dupTitle)),
            ],
        ];
    }

    /**
     * @param  list<Product>  $products
     * @param  array<int, list<array<string, mixed>>>  $rowsByProduct
     * @return array<string, mixed>
     */
    private function checkCanonical(array $products, array $rowsByProduct): array
    {
        $ok = true;
        $detail = [];
        foreach ($products as $product) {
            if (! $this->mapper->splitsBySku($product)) {
                continue;
            }
            $canonical = $this->policy->canonicalSku($product);
            $frozen = $this->canonical->get((int) $product->id);
            $rows = $rowsByProduct[(int) $product->id] ?? [];
            $holder = collect($rows)->firstWhere('page_unique', (string) $product->id);
            $pass = $canonical instanceof Sku && is_array($holder);
            if ($frozen !== null) {
                $pass = $pass && (int) $canonical->id === $frozen;
            }
            $ok = $ok && $pass;
            $detail[] = [
                'product_id' => (int) $product->id,
                'frozen_sku_id' => $frozen,
                'canonical_sku_id' => $canonical instanceof Sku ? (int) $canonical->id : null,
                'holder_url' => $holder['page_url'] ?? null,
                'pass' => $pass,
            ];
        }

        return ['name' => 'canonical', 'pass' => $ok, 'detail' => $detail];
    }

    /**
     * @param  list<Product>  $products
     * @param  array<int, list<array<string, mixed>>>  $rowsByProduct
     * @return array<string, mixed>
     */
    private function checkAvailability(array $products, array $rowsByProduct): array
    {
        $mismatches = [];
        foreach ($products as $product) {
            foreach ($rowsByProduct[(int) $product->id] ?? [] as $row) {
                $skuId = $this->skuIdFromRow($product, $row);
                $sku = $skuId !== null ? $product->skus->firstWhere('id', $skuId) : $this->policy->cheapestPricedSku($product);
                $expected = $sku instanceof Sku ? $this->policy->isOrderableSku($product, $sku) : false;
                if ((bool) ($row['availability'] ?? false) !== $expected) {
                    $mismatches[] = [
                        'product_id' => (int) $product->id,
                        'page_unique' => $row['page_unique'] ?? null,
                        'feed' => $row['availability'] ?? null,
                        'expected' => $expected,
                    ];
                }
            }
        }

        return ['name' => 'availability', 'pass' => $mismatches === [], 'detail' => $mismatches];
    }

    /**
     * @param  list<Product>  $products
     * @param  array<int, list<array<string, mixed>>>  $rowsByProduct
     * @return array<string, mixed>
     */
    private function checkLookups(array $products, array $rowsByProduct): array
    {
        $page = $this->query->paginated(1, 'date_added_desc');
        $detail = [
            'page_mode' => [
                'rows' => count($page['products']),
                'total' => $page['total'] ?? null,
            ],
            'products' => [],
        ];
        $ok = is_array($page['products']);
        foreach ($products as $product) {
            $rows = $rowsByProduct[(int) $product->id] ?? [];
            if ($rows === []) {
                continue;
            }
            $handle = (string) $product->handle;
            $baseUrl = $this->mapper->pageUrl($handle);
            $byUrl = $this->query->byUrls([$baseUrl]);
            $byUnique = $this->query->byUniques([(string) $product->id]);
            $combined = $this->query->byUrlsAndUniques([$baseUrl], [(string) $product->id]);
            $expectAll = count($rows);
            $urlCount = count($byUrl['products']);
            $uniqueCount = count($byUnique['products']);
            $combinedCount = count($combined['products']);
            $pass = $urlCount === $expectAll
                && $uniqueCount === 1
                && $combinedCount === $expectAll;
            $skuRow = collect($rows)->first(fn (array $row): bool => str_contains((string) $row['page_url'], '?sku='));
            if (is_array($skuRow)) {
                $one = $this->query->byUrls([(string) $skuRow['page_url']]);
                $pass = $pass && count($one['products']) === 1
                    && ($one['products'][0]['page_unique'] ?? null) === ($skuRow['page_unique'] ?? null);
            }
            $invalid = $this->query->byUrls([$baseUrl.'?sku=99999999']);
            if ($this->mapper->splitsBySku($product)) {
                $pass = $pass && ($invalid['products'] ?? []) === [];
            }
            $ok = $ok && $pass;
            $detail['products'][] = [
                'handle' => $handle,
                'by_urls' => $urlCount,
                'by_uniques' => $uniqueCount,
                'combined' => $combinedCount,
                'pass' => $pass,
            ];
        }

        return ['name' => 'lookups', 'pass' => $ok, 'detail' => $detail];
    }

    /**
     * @param  list<Product>  $products
     * @param  array<int, list<array<string, mixed>>>  $rowsByProduct
     * @return array<string, mixed>
     */
    private function checkPrices(array $products, array $rowsByProduct, int $sample): array
    {
        $candidates = [];
        foreach ($products as $product) {
            foreach ($rowsByProduct[(int) $product->id] ?? [] as $row) {
                $candidates[] = ['product' => $product, 'row' => $row];
            }
        }
        shuffle($candidates);
        $picked = array_slice($candidates, 0, max(1, $sample));
        $comparisons = [];
        foreach ($picked as $item) {
            /** @var Product $product */
            $product = $item['product'];
            /** @var array<string, mixed> $row */
            $row = $item['row'];
            $skuId = $this->skuIdFromRow($product, $row);
            $catalog = $this->catalogHttp((string) $product->handle, $skuId);
            $page = $this->storefrontHttp((string) $product->handle, $skuId);
            $feed = (int) ($row['current_price'] ?? 0);
            $match = $catalog['price'] === $feed && ($page['status'] === null || $page['ok']);
            $comparisons[] = [
                'handle' => (string) $product->handle,
                'sku_id' => $skuId,
                'page_url' => $row['page_url'] ?? null,
                'feed_price' => $feed,
                'catalog_http_price' => $catalog['price'],
                'catalog_http_status' => $catalog['status'],
                'storefront_http_status' => $page['status'],
                'match' => $match,
            ];
        }

        return [
            'name' => 'prices',
            'pass' => $comparisons !== [] && collect($comparisons)->every(fn (array $row): bool => $row['match']),
            'detail' => $comparisons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkPagination(?string $groupSlug): array
    {
        $slug = trim((string) $groupSlug);
        if ($slug === '') {
            return ['name' => 'pagination', 'pass' => true, 'detail' => 'skipped'];
        }
        $groupId = CatalogGroup::query()->where('slug', $slug)->value('id');
        if (! $groupId) {
            return ['name' => 'pagination', 'pass' => false, 'detail' => 'group_not_found:'.$slug];
        }
        $base = $this->query->listableQuery()->where('catalog_group_id', (int) $groupId);
        $pageSize = max(1, (int) config('torob.page_size', 100));
        $first = $this->query->paginated(1, 'date_added_desc', null, $base);
        $total = (int) $first['total'];
        $maxPages = (int) $first['max_pages'];
        if ($maxPages > 200) {
            return [
                'name' => 'pagination',
                'pass' => false,
                'detail' => ['group' => $slug, 'max_pages' => $maxPages, 'error' => 'too_many_pages'],
            ];
        }
        $seen = [];
        $sum = 0;
        for ($page = 1; $page <= $maxPages; $page++) {
            $payload = $page === 1 ? $first : $this->query->paginated($page, 'date_added_desc', $total, $base);
            $count = count($payload['products']);
            if ($page < $maxPages && $count !== $pageSize) {
                return [
                    'name' => 'pagination',
                    'pass' => false,
                    'detail' => ['page' => $page, 'rows' => $count, 'expected' => $pageSize],
                ];
            }
            if ($count > $pageSize) {
                return ['name' => 'pagination', 'pass' => false, 'detail' => ['overflow_page' => $page, 'rows' => $count]];
            }
            foreach ($payload['products'] as $row) {
                $unique = (string) ($row['page_unique'] ?? '');
                if ($unique === '' || isset($seen[$unique])) {
                    return ['name' => 'pagination', 'pass' => false, 'detail' => ['duplicate' => $unique, 'page' => $page]];
                }
                $seen[$unique] = true;
            }
            $sum += $count;
        }

        return [
            'name' => 'pagination',
            'pass' => $sum === $total && count($seen) === $total,
            'detail' => [
                'group' => $slug,
                'total' => $total,
                'max_pages' => $maxPages,
                'sum' => $sum,
                'unique' => count($seen),
            ],
        ];
    }

    /**
     * @param  list<Product>  $products
     * @return array<string, mixed>
     */
    private function checkTimings(array $products): array
    {
        $measured = $this->reporter->measure();
        $first = collect($measured['samples'] ?? [])->firstWhere('label', 'first_page');
        $coldMs = (int) ($first['cold']['ms'] ?? 0);

        return [
            'name' => 'timings',
            'pass' => $coldMs < 5000,
            'detail' => $measured,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkRecentErrors(): array
    {
        $counts = ['torob.v3.products.400' => 0, 'torob.v3.products.500' => 0];
        $since = now()->subMinutes(30);
        foreach (glob(storage_path('logs/laravel*.log')) ?: [] as $file) {
            $this->countNeedlesInFile($file, $counts, $since);
        }

        return [
            'name' => 'recent_errors',
            'pass' => $counts['torob.v3.products.500'] === 0,
            'detail' => $counts + ['window_minutes' => 30],
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function countNeedlesInFile(string $file, array &$counts, Carbon $since): void
    {
        $size = @filesize($file);
        if (! is_int($size) || $size < 1) {
            return;
        }
        $handle = fopen($file, 'r');
        if (! is_resource($handle)) {
            return;
        }
        if ($size > 4_000_000) {
            fseek($handle, -4_000_000, SEEK_END);
            fgets($handle);
        }
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} [0-9:]{8})\]/', $line, $match) !== 1) {
                continue;
            }
            try {
                $at = Carbon::parse($match[1], config('app.timezone'));
            } catch (Throwable) {
                continue;
            }
            if ($at->lt($since)) {
                continue;
            }
            foreach (array_keys($counts) as $needle) {
                if (str_contains($line, $needle)) {
                    $counts[$needle]++;
                }
            }
        }
        fclose($handle);
    }

    /**
     * @return array{status: int|null, price: int|null}
     */
    private function catalogHttp(string $handle, ?int $skuId): array
    {
        if (! app()->environment('testing')) {
            $url = rtrim((string) config('app.url'), '/').'/api/catalog/products/'.$handle.'/prices';
            try {
                $response = Http::timeout(8)->acceptJson()->get($url);

                return $this->priceFromPayload($response->status(), json_decode($response->body() ?: '[]', true), $skuId);
            } catch (Throwable) {
                return ['status' => null, 'price' => null];
            }
        }

        try {
            $request = Request::create('/api/catalog/products/'.$handle.'/prices', 'GET', server: [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $kernel = app(Kernel::class);
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            $payload = json_decode($response->getContent() ?: '[]', true);
            $kernel->terminate($request, $response);

            return $this->priceFromPayload($status, is_array($payload) ? $payload : [], $skuId);
        } catch (Throwable) {
            return ['status' => null, 'price' => null];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, price: int|null}
     */
    private function priceFromPayload(int $status, array $payload, ?int $skuId): array
    {
        $skus = is_array($payload['data']['skus'] ?? null) ? $payload['data']['skus'] : [];
        $price = null;
        if ($skuId !== null) {
            foreach ($skus as $sku) {
                if ((int) ($sku['id'] ?? 0) === $skuId) {
                    $price = isset($sku['price']) ? (int) $sku['price'] : null;
                    break;
                }
            }
        } elseif ($skus !== []) {
            $price = isset($skus[0]['price']) ? (int) $skus[0]['price'] : null;
        }

        return ['status' => $status, 'price' => $price];
    }

    /**
     * @return array{status: int|null, ok: bool}
     */
    private function storefrontHttp(string $handle, ?int $skuId): array
    {
        if (app()->environment('testing')) {
            return ['status' => null, 'ok' => true];
        }
        $url = rtrim((string) config('torob.shop_base_url'), '/').'/catalog/'.$handle;
        if ($skuId !== null && $skuId > 0) {
            $url .= '?sku='.$skuId;
        }
        try {
            $response = Http::timeout(8)->get($url);

            return ['status' => $response->status(), 'ok' => $response->successful()];
        } catch (Throwable) {
            return ['status' => null, 'ok' => false];
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function skuIdFromRow(Product $product, array $row): ?int
    {
        $url = $this->mapper->parseProductUrl((string) ($row['page_url'] ?? ''));
        if ($url !== null && $url['sku_id'] !== null) {
            return $url['sku_id'];
        }
        $parsed = $this->mapper->parsePageUnique((string) ($row['page_unique'] ?? ''));
        if ($parsed !== null && $parsed['sku_id'] !== null) {
            return $parsed['sku_id'];
        }
        $canonical = $this->policy->canonicalSku($product);

        return $canonical instanceof Sku ? (int) $canonical->id : null;
    }
}

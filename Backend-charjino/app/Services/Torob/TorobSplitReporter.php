<?php

namespace App\Services\Torob;

use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\CatalogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TorobSplitReporter
{
    public function __construct(
        private TorobProductMapper $mapper,
        private TorobProductQueryService $query,
        private TorobSplitPolicy $policy,
        private TorobSplitCanonicalStore $canonicalStore,
        private CatalogService $catalog,
        private TorobV3Cache $cache,
    ) {}

    /**
     * Read-only catalog simulation. Does not enable products or write canonical SKUs.
     *
     * @return array<string, mixed>
     */
    public function report(bool $simulateAll = false): array
    {
        $started = hrtime(true);
        $policy = $simulateAll ? $this->policy->withSimulateAll() : $this->policy;
        $listableProducts = 0;
        $multiSkuProducts = 0;
        $listedSkus = 0;
        $orderableSkus = 0;
        $productChunks = 0;
        $currentRows = 0;
        $simulatedRows = 0;
        $exempt = ['counts' => [], 'ids' => [], 'samples' => [], 'no_orderable_products' => []];
        $groups = [];
        $priorityCandidates = [];

        $this->query->eachListableCursor(100, function (Collection $products) use (
            $policy,
            &$listableProducts,
            &$multiSkuProducts,
            &$listedSkus,
            &$orderableSkus,
            &$productChunks,
            &$currentRows,
            &$simulatedRows,
            &$exempt,
            &$groups,
            &$priorityCandidates,
        ): void {
            $productChunks++;
            foreach ($products as $product) {
                if (! $product instanceof Product) {
                    continue;
                }
                $listableProducts++;
                $slug = trim((string) ($product->catalogGroup?->slug ?? '')) ?: 'ungrouped';
                $title = (string) $product->title;
                $groups[$slug] ??= [
                    'slug' => $slug,
                    'group_title' => (string) ($product->catalogGroup?->title ?? $product->catalogGroup?->name ?? $slug),
                    'products' => 0,
                    'multi_sku' => 0,
                    'current_rows' => 0,
                    'simulated_rows' => 0,
                    'sample_titles' => [],
                ];
                $groups[$slug]['products']++;

                $liveRows = $this->mapper->toRows($product);
                $currentRows += count($liveRows);
                $groups[$slug]['current_rows'] += count($liveRows);

                $orderable = 0;
                $maxPrice = 0;
                foreach ($policy->listedSkus($product) as $sku) {
                    $listedSkus++;
                    if ($policy->isOrderableSku($product, $sku)) {
                        $orderable++;
                        $orderableSkus++;
                        $maxPrice = max($maxPrice, $sku->sellPrice());
                    }
                }
                if ($orderable > 1) {
                    $multiSkuProducts++;
                    $groups[$slug]['multi_sku']++;
                    $priorityCandidates[] = [
                        'id' => (int) $product->id,
                        'handle' => (string) $product->handle,
                        'group' => $slug,
                        'orderable_skus' => $orderable,
                        'max_price' => $maxPrice,
                    ];
                }

                $simulated = $policy->decide($product);
                $simCount = $simulated->split ? count($simulated->skus) : 1;
                $simulatedRows += $simCount;
                $groups[$slug]['simulated_rows'] += $simCount;
                if (! $simulated->split && $simulated->reason !== null && $simulated->reason !== TorobSplitDecision::REASON_NOT_ENABLED && $simulated->reason !== TorobSplitDecision::REASON_SINGLE_SKU) {
                    $reason = $simulated->reason;
                    $exempt['counts'][$reason] = ($exempt['counts'][$reason] ?? 0) + 1;
                    $exempt['samples'][$reason] ??= [];
                    $exempt['ids'][$reason] ??= [];
                    $exempt['ids'][$reason][] = (int) $product->id;
                    if ($reason === TorobSplitDecision::REASON_NO_ORDERABLE_SKU) {
                        $exempt['no_orderable_products'][] = [
                            'id' => (int) $product->id,
                            'handle' => (string) $product->handle,
                            'group' => $slug,
                            'visible' => (bool) $product->visible,
                            'unavailable_at' => $product->unavailable_at?->toIso8601String(),
                        ];
                    }
                    if (count($exempt['samples'][$reason]) < 20) {
                        $exempt['samples'][$reason][] = [
                            'id' => (int) $product->id,
                            'handle' => (string) $product->handle,
                            'title' => $title,
                            'reason' => $reason,
                            'orderable_skus' => $orderable,
                            'group' => $slug,
                            'detail' => $this->exemptionDetail($product, $reason, $orderable),
                        ];
                    }
                }
                if ($simulated->split && count($groups[$slug]['sample_titles']) < 10) {
                    foreach ($simulated->skus as $sku) {
                        if (count($groups[$slug]['sample_titles']) >= 10) {
                            break;
                        }
                        $groups[$slug]['sample_titles'][] = $this->mapper->splitTitle($product, $sku);
                    }
                }
            }
        }, true);

        usort($priorityCandidates, static function (array $a, array $b): int {
            return [$b['max_price'], $b['orderable_skus'], $a['id']] <=> [$a['max_price'], $a['orderable_skus'], $b['id']];
        });

        $groupList = array_values($groups);
        usort($groupList, static fn (array $a, array $b): int => $b['products'] <=> $a['products']);
        $topGroups = array_slice($groupList, 0, 10);

        $ratio = $currentRows > 0 ? $simulatedRows / $currentRows : 0;

        return [
            'simulate_all' => $simulateAll,
            'listable_products' => $listableProducts,
            'multi_sku_products' => $multiSkuProducts,
            'listed_skus' => $listedSkus,
            'orderable_skus' => $orderableSkus,
            'product_chunks_scanned' => $productChunks,
            'product_chunk_size' => 100,
            'live_feed_pages_read' => 0,
            'current_feed_rows' => $currentRows,
            'simulated_feed_rows' => $simulatedRows,
            'row_multiplier' => round($ratio, 3),
            'recommend_priority_only' => $ratio > 2,
            'completeness' => 'catalog_products_only',
            'completeness_note' => 'همهٔ محصولات listable با رابطه خوانده می‌شوند (قطعه‌های ۱۰۰تایی شناسه). صفحهٔ HTTP فید ترب خوانده نمی‌شود. نمونهٔ معافیت حداکثر ۲۰ مورد در هر دلیل است. این گزارش ممیزی کامل صفحه‌بندی زنده نیست.',
            'exemptions' => [
                'counts' => is_array($exempt['counts'] ?? null) ? $exempt['counts'] : [],
                'ids' => is_array($exempt['ids'] ?? null) ? $exempt['ids'] : [],
                'no_orderable_sku' => is_array($exempt['no_orderable_products'] ?? null) ? $exempt['no_orderable_products'] : [],
                'samples' => is_array($exempt['samples'] ?? null) ? $exempt['samples'] : [],
            ],
            'top_groups' => $topGroups,
            'priority_candidates_preview' => array_slice($priorityCandidates, 0, 30),
            'enabled' => [
                'product_ids' => $this->policy->enabledProductIds(),
                'group_slugs' => $this->policy->enabledGroupSlugs(),
                'exclude_product_ids' => $this->policy->excludeProductIds(),
                'max_items_per_product' => $this->policy->maxItemsPerProduct(),
                'frozen_canonical_count' => count($this->canonicalStore->all()),
            ],
            'page_size_note' => 'صفحه‌بندی فید ردیف‌محور است؛ هر صفحه غیرآخر حداکثر page_size ردیف دارد و محصول تقسیم‌شده می‌تواند بین دو صفحه قطع شود.',
            'elapsed_ms' => (int) round((hrtime(true) - $started) / 1e6),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function measure(?int $total = null): array
    {
        $total = $total ?? $this->query->listableTotal();
        $pageSize = max(1, (int) config('torob.page_size', 100));
        $maxPages = max(1, (int) ceil($total / $pageSize));
        $pages = [
            ['label' => 'first', 'page' => 1, 'sort' => 'date_added_desc'],
            ['label' => 'middle', 'page' => max(1, (int) ceil($maxPages / 2)), 'sort' => 'date_added_desc'],
            ['label' => 'last', 'page' => $maxPages, 'sort' => 'date_added_desc'],
        ];
        $samples = [];
        foreach ($pages as $item) {
            $samples[] = $this->timeCall($item['label'].'_page', function () use ($item, $total): array {
                return $this->query->paginated($item['page'], $item['sort'], $total);
            });
        }

        $product = $this->sampleListableProduct();
        if ($product instanceof Product) {
            $url = $this->mapper->pageUrl((string) $product->handle);
            $unique = (string) $product->id;
            $samples[] = $this->timeCall('lookup_page_urls', fn (): array => $this->query->byUrls([$url]));
            $samples[] = $this->timeCall('lookup_page_uniques', fn (): array => $this->query->byUniques([$unique]));
        }

        return [
            'total' => $total,
            'max_pages' => $maxPages,
            'page_size' => $pageSize,
            'cache' => $this->cache->inspect(),
            'samples' => $samples,
        ];
    }

    /**
     * @param  list<string>  $handles
     * @return list<array<string, mixed>>
     */
    public function comparePrices(array $handles): array
    {
        $rows = [];
        foreach ($handles as $handle) {
            $handle = trim($handle);
            if ($handle === '') {
                continue;
            }
            $product = Product::query()
                ->with(['skus', 'catalogGroup'])
                ->whereRaw('LOWER(handle) = ?', [mb_strtolower($handle)])
                ->first();
            if (! $product instanceof Product) {
                $rows[] = ['handle' => $handle, 'error' => 'product_not_found'];

                continue;
            }
            $payload = $this->catalog->productPrices($handle);
            $apiSkus = collect($payload['skus'] ?? [])->keyBy('id');
            foreach ($this->mapper->toRows($product) as $row) {
                $skuId = $this->skuIdFromRow($product, $row);
                $sku = $skuId !== null ? $product->skus->firstWhere('id', $skuId) : $this->policy->cheapestPricedSku($product);
                $apiPrice = $skuId !== null ? (int) ($apiSkus[$skuId]['price'] ?? 0) : (int) ($payload['minPrice'] ?? 0);
                $sell = $sku instanceof Sku ? $sku->sellPrice() : 0;
                $feed = (int) ($row['current_price'] ?? 0);
                $rows[] = [
                    'handle' => $handle,
                    'page_unique' => $row['page_unique'] ?? null,
                    'sku_id' => $skuId,
                    'feed_price' => $feed,
                    'sku_sell_price' => $sell,
                    'catalog_api_price' => $apiPrice,
                    'match' => $feed === $sell && ($apiPrice === 0 || $feed === $apiPrice),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function timeCall(string $label, callable $callback): array
    {
        $run = function () use ($callback): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $started = hrtime(true);
            $memBefore = memory_get_usage(true);
            $payload = $callback();
            $elapsed = (int) round((hrtime(true) - $started) / 1e6);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return [
                'ms' => $elapsed,
                'queries' => $queries,
                'memory_delta_bytes' => memory_get_usage(true) - $memBefore,
                'peak_memory_bytes' => memory_get_peak_usage(true),
                'output_bytes' => is_string($json) ? strlen($json) : 0,
                'rows' => is_array($payload['products'] ?? null) ? count($payload['products']) : 0,
                'total' => $payload['total'] ?? null,
            ];
        };

        $cold = $run();
        $warm = $run();

        return [
            'label' => $label,
            'cold' => $cold,
            'warm' => $warm,
        ];
    }

    private function sampleListableProduct(): ?Product
    {
        $ids = $this->policy->enabledProductIds();
        $query = $this->query->withRelations($this->query->listableQuery());
        if ($ids !== []) {
            $found = (clone $query)->whereIn((new Product)->getQualifiedKeyName(), $ids)->first();
            if ($found instanceof Product) {
                return $found;
            }
        }

        return $query->orderByDesc((new Product)->getQualifiedKeyName())->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function skuIdFromRow(Product $product, array $row): ?int
    {
        $parsed = $this->mapper->parsePageUnique((string) ($row['page_unique'] ?? ''));
        if ($parsed === null) {
            return null;
        }
        if ($parsed['sku_id'] !== null) {
            return $parsed['sku_id'];
        }
        $url = $this->mapper->parseProductUrl((string) ($row['page_url'] ?? ''));
        if ($url !== null && $url['sku_id'] !== null) {
            return $url['sku_id'];
        }
        $canonical = $this->policy->canonicalSku($product);

        return $canonical instanceof Sku ? (int) $canonical->id : null;
    }

    private function exemptionDetail(Product $product, string $reason, int $orderable): string
    {
        if ($reason === TorobSplitDecision::REASON_NO_ORDERABLE_SKU) {
            return 'بدون SKU قابل سفارش; orderable='.$orderable
                .' visible='.((bool) $product->visible ? '1' : '0')
                .' unavailable_at='.($product->unavailable_at?->toIso8601String() ?? '');
        }
        if ($reason === TorobSplitDecision::REASON_OVER_MAX) {
            return 'sku_count='.count($this->policy->listedSkus($product))
                .' orderable='.$orderable
                .' exceeds max_items_per_product='.$this->policy->maxItemsPerProduct();
        }
        if ($reason === TorobSplitDecision::REASON_AMBIGUOUS_TITLES || $reason === TorobSplitDecision::REASON_EMPTY_LABELS) {
            $seen = [];
            foreach ($this->policy->listedSkus($product) as $sku) {
                $title = $this->mapper->splitTitle($product, $sku);
                $key = $this->normalizeSample($title);
                if ($key === '' || isset($seen[$key])) {
                    return 'title='.$title;
                }
                $seen[$key] = true;
            }
        }
        if ($reason === TorobSplitDecision::REASON_UNKNOWN_MAPPING) {
            $copy = app(TorobFeedCatalogCopy::class);
            foreach ($this->policy->listedSkus($product) as $sku) {
                $gap = $copy->mappingGap($product, $sku);
                if ($gap !== '') {
                    return $gap;
                }
            }

            return 'unknown_mapping';
        }

        return $reason;
    }

    private function normalizeSample(string $title): string
    {
        $title = strtr($title, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $title) ?? $title));
    }
}

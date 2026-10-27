<?php

namespace App\Services\Torob;

use App\Models\Product;
use App\Models\Sku;
use Illuminate\Support\Collection;

class TorobRolloutPlanner
{
    private const CHUNK_SIZE = 100;

    private const MAX_BATCH = 500;

    private const MULTIPLIER = 1.5;

    private const MAX_STAGE_EXTRA_ROWS = 1500;

    /**
     * @return array<string, mixed>
     */
    public function plan(TorobProductQueryService $query, TorobSplitPolicy $policy, TorobProductMapper $mapper, TorobFeedCatalogCopy $copy, int $globalFeedRows, int $globalBaselineStored): array
    {
        $simulate = $policy->withSimulateAll();
        $enabled = array_flip($policy->enabledProductIds());
        $blocked = array_flip(array_map('strval', config('torob_split_copy.blocked_group_slugs', [])));

        $scopes = $this->scopeDefinitions();
        $state = [];
        foreach (array_keys($scopes) as $key) {
            $state[$key] = $this->emptyScopeState($scopes[$key]['label'], $scopes[$key]['slug'] ?? $key);
        }

        $chunks = 0;
        $listedSkus = 0;
        $orderableSkus = 0;
        $productsScanned = 0;
        /** @var array<string, int> $groupFeedRows */
        $groupFeedRows = [];
        /** @var list<array<string, mixed>> $playItunes */
        $playItunes = [];

        $query->eachListableCursor(self::CHUNK_SIZE, function (Collection $products) use (
            $simulate,
            $mapper,
            $copy,
            $enabled,
            $blocked,
            $scopes,
            &$state,
            &$chunks,
            &$listedSkus,
            &$orderableSkus,
            &$productsScanned,
            &$groupFeedRows,
            &$playItunes,
        ): void {
            $chunks++;
            foreach ($products as $product) {
                if (! $product instanceof Product) {
                    continue;
                }
                $productsScanned++;
                $slug = trim((string) ($product->catalogGroup?->slug ?? ''));
                if ($slug !== '' && isset($blocked[$slug])) {
                    continue;
                }
                $handle = (string) $product->handle;
                if (str_contains(mb_strtolower($handle), 'game-pass')) {
                    continue;
                }

                $isEnabled = isset($enabled[(int) $product->id]);
                $liveRows = count($mapper->toRows($product));
                if ($slug !== '') {
                    $groupFeedRows[$slug] = ($groupFeedRows[$slug] ?? 0) + $liveRows;
                }
                $listed = $simulate->listedSkus($product);
                $orderable = 0;
                foreach ($listed as $sku) {
                    $listedSkus++;
                    if ($simulate->isOrderableSku($product, $sku)) {
                        $orderable++;
                        $orderableSkus++;
                    }
                }

                $decision = $simulate->decide($product);
                $simRows = $decision->split ? count($decision->skus) : 1;
                $handleLower = mb_strtolower($handle);
                if (str_contains($handleLower, 'google-play')
                    || str_contains($handleLower, 'itunes')
                    || str_contains($handleLower, 'app-store')) {
                    $playItunes[] = [
                        'id' => (int) $product->id,
                        'handle' => $handle,
                        'slug' => $slug,
                        'in_stage1_enabled' => $isEnabled,
                        'listed_skus' => count($listed),
                        'live_feed_rows' => $liveRows,
                        'simulate_split' => $decision->split,
                        'simulate_reason' => $decision->reason,
                    ];
                }

                foreach ($scopes as $scopeKey => $def) {
                    if (! ($def['match'])($product, $slug, $handle, $isEnabled)) {
                        continue;
                    }
                    $s = &$state[$scopeKey];
                    $s['products_examined']++;
                    $s['listed_skus'] += count($listed);
                    $s['orderable_skus'] += $orderable;
                    $s['current_feed_rows'] += $liveRows;
                    $s['simulated_feed_rows'] += $simRows;

                    if ($decision->split) {
                        $s['eligible_products']++;
                        $s['eligible_skus'] += count($decision->skus);
                        $pid = (int) $product->id;
                        $s['remaining_product_ids'][] = $pid;
                        $extra = max(0, count($decision->skus) - 1);
                        $s['remaining_extras_by_id'][$pid] = $extra;
                        $s['remaining_skus'] += count($decision->skus);
                        $s['remaining_extra_rows'] += $extra;
                        foreach ($decision->skus as $sku) {
                            if (count($s['sample_titles']) < 12) {
                                $s['sample_titles'][] = $mapper->splitTitle($product, $sku);
                            }
                            $this->trackBrand($s, $product, $copy, $mapper, $sku);
                        }
                    } elseif ($decision->reason !== null
                        && $decision->reason !== TorobSplitDecision::REASON_NOT_ENABLED) {
                        $reason = $decision->reason;
                        $pid = (int) $product->id;
                        $s['exemption_ids'][$reason] ??= [];
                        $s['exemption_ids'][$reason][] = $pid;
                        if ($reason === TorobSplitDecision::REASON_NO_ORDERABLE_SKU) {
                            $s['no_orderable_sku_ids'][] = $pid;
                            if (count($s['no_orderable_sku_handles']) < 80) {
                                $s['no_orderable_sku_handles'][] = [
                                    'id' => $pid,
                                    'handle' => $handle,
                                    'reason' => $reason,
                                ];
                            }
                        }
                        if ($reason !== TorobSplitDecision::REASON_SINGLE_SKU) {
                            $s['exemptions'][$reason] = ($s['exemptions'][$reason] ?? 0) + 1;
                            $this->trackMappingGaps($s, $product, $copy, $listed, $reason);
                        }
                    }

                    unset($s);
                }
            }
        }, true);

        $extrasSnapshot = [];
        foreach ($state as $key => &$s) {
            $noOrderable = array_values(array_unique($s['no_orderable_sku_ids']));
            sort($noOrderable);
            $s['no_orderable_sku_ids'] = $noOrderable;
            foreach ($s['exemption_ids'] as $reason => $reasonIds) {
                $unique = array_values(array_unique($reasonIds));
                sort($unique);
                $s['exemption_ids'][$reason] = $unique;
            }
            $ids = array_values(array_unique(array_diff($s['remaining_product_ids'], $noOrderable)));
            sort($ids);
            $s['remaining_product_ids'] = $ids;
            $s['remaining_products'] = count($ids);
            $extrasById = [];
            foreach ($ids as $id) {
                $extrasById[$id] = (int) ($s['remaining_extras_by_id'][$id] ?? 0);
            }
            $s['remaining_extra_rows'] = array_sum($extrasById);
            $extrasSnapshot[$key] = $extrasById;
            $s['new_feed_rows_if_remaining'] = $s['current_feed_rows'] + $s['remaining_extra_rows'];
            $s['ratio_vs_global_baseline'] = $globalFeedRows > 0
                ? round($s['new_feed_rows_if_remaining'] / $globalFeedRows, 4)
                : null;
            $slug = (string) ($s['slug'] ?? '');
            $categoryBaseline = $groupFeedRows[$slug] ?? $s['current_feed_rows'];
            $s['category_feed_rows_baseline'] = $categoryBaseline;
            $s['new_feed_rows_if_remaining_in_group'] = $categoryBaseline + $s['remaining_extra_rows'];
            $s['ratio_vs_category_baseline'] = $categoryBaseline > 0
                ? round($s['new_feed_rows_if_remaining_in_group'] / $categoryBaseline, 4)
                : null;
            $s['major_brands'] = $this->majorBrands($s['brand_stats']);
            $s['competitor_comparison'] = $this->competitorComparison($s['major_brands']);
            unset($s['brand_stats'], $s['remaining_extras_by_id']);
            $scopeSlug = (string) ($s['slug'] ?? $key);
            $sortedExtras = TorobRolloutPriority::sortExtrasByRolloutRisk($extrasById, $scopeSlug);
            $s['stages'] = $this->buildStages(
                $sortedExtras,
                $globalFeedRows,
                $globalBaselineStored,
                $categoryBaseline,
                $key,
            );
            $s['mapping_gaps'] = $this->summarizeGaps($s['gap_samples']);
            unset($s['gap_samples']);
        }
        unset($s);

        $globalIfAll = $globalFeedRows;
        foreach ($state as $s) {
            $globalIfAll += $s['remaining_extra_rows'];
        }
        $tgIds = $state['telegram-stars']['remaining_product_ids'] ?? [];
        $topIds = $state['service-topup']['remaining_product_ids'] ?? [];
        $combinedSmall = count($tgIds) + count($topIds);
        $combinedStages = [];
        if ($combinedSmall > 0 && $combinedSmall <= $this->maxBatch()) {
            $merged = [];
            foreach ($tgIds as $id) {
                $merged[$id] = (int) (($extrasSnapshot['telegram-stars'] ?? [])[$id] ?? 0);
            }
            foreach ($topIds as $id) {
                $merged[$id] = (int) (($extrasSnapshot['service-topup'] ?? [])[$id] ?? 0);
            }
            $merged = TorobRolloutPriority::sortExtrasByRolloutRisk($merged, 'telegram');
            $combinedStages = $this->buildStages(
                $merged,
                $globalFeedRows,
                $globalBaselineStored,
                ($groupFeedRows['telegram'] ?? 0) + ($groupFeedRows['game-topup'] ?? 0),
                'telegram-plus-service-topup',
            );
        }

        return [
            'generated_at' => now()->timezone('Asia/Tehran')->toIso8601String(),
            'completeness' => 'catalog_listable_full_scan',
            'completeness_note' => 'همهٔ محصولات listable با simulate-all پیمایش شد (قطعه ۱۰۰تایی). artisan report --simulate-all همین پوشش کاتالوگ را دارد؛ صفحهٔ HTTP فید خوانده نمی‌شود.',
            'products_scanned' => $productsScanned,
            'product_chunks' => $chunks,
            'product_chunk_size' => self::CHUNK_SIZE,
            'live_feed_pages_read' => 0,
            'listed_skus' => $listedSkus,
            'orderable_skus' => $orderableSkus,
            'global_feed_rows_current' => $globalFeedRows,
            'global_baseline_stored' => $globalBaselineStored,
            'global_stop_cap' => $globalBaselineStored > 0 ? (int) ceil($globalBaselineStored * self::MULTIPLIER) : null,
            'global_feed_rows_if_all_scopes_remaining' => $globalIfAll,
            'global_ratio_if_all_vs_stored_baseline' => $globalBaselineStored > 0
                ? round($globalIfAll / $globalBaselineStored, 4)
                : null,
            'global_ratio_if_all_vs_current' => $globalFeedRows > 0
                ? round($globalIfAll / $globalFeedRows, 4)
                : null,
            'cap_rules' => [
                'global_multiplier' => $this->globalMultiplier(),
                'global_only' => true,
                'category_ratio_is_not_a_stop' => true,
                'max_products_per_stage' => $this->maxBatch(),
                'max_extra_rows_per_stage' => $this->maxStageExtraRows(),
                'reason' => 'سقف ۱.۵ فقط برای کل فید نسبت به baseline ذخیره‌شده است. ضریب دسته قبلاً مرحله‌های تک‌محصولی می‌ساخت و جلوی فعال‌سازی باقی گیفت‌کارت را می‌گرفت؛ به‌جای آن هر مرحله حداکثر ۵۰۰ محصول و ۱۵۰۰ ردیف اضافه دارد.',
            ],
            'scopes' => $state,
            'combined_telegram_service' => [
                'remaining_products' => $combinedSmall,
                'stages' => $combinedStages,
            ],
            'google_play_and_itunes' => $this->annotatePlayItunes($playItunes, $state),
        ];
    }

    private function maxBatch(): int
    {
        return max(1, (int) config('torob.split_by_sku.max_batch', self::MAX_BATCH));
    }

    private function globalMultiplier(): float
    {
        $value = (float) config('torob.split_by_sku.max_feed_multiplier', self::MULTIPLIER);

        return $value > 0 ? $value : self::MULTIPLIER;
    }

    private function maxStageExtraRows(): int
    {
        return max(1, (int) config('torob.split_by_sku.max_stage_extra_rows', self::MAX_STAGE_EXTRA_ROWS));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $state
     * @return list<array<string, mixed>>
     */
    private function annotatePlayItunes(array $rows, array $state): array
    {
        $idToStage = [];
        foreach ($state as $scopeKey => $scope) {
            foreach ($scope['stages'] ?? [] as $stage) {
                foreach ($stage['product_ids'] ?? [] as $id) {
                    $idToStage[(int) $id] = [
                        'scope' => $scopeKey,
                        'file' => $stage['file'] ?? null,
                        'stage' => $stage['stage'] ?? null,
                    ];
                }
            }
        }
        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $hit = $idToStage[$id] ?? null;
            $row['rollout_file'] = $hit['file'] ?? null;
            $row['rollout_scope'] = $hit['scope'] ?? null;
            $row['rollout_stage'] = $hit['stage'] ?? null;
        }
        unset($row);
        usort($rows, static function (array $a, array $b): int {
            $ha = (string) ($a['handle'] ?? '');
            $hb = (string) ($b['handle'] ?? '');
            $byHandle = $ha <=> $hb;
            if ($byHandle !== 0) {
                return $byHandle;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $rows;
    }

    /**
     * @return array<string, array{label: string, slug: string, match: callable}>
     */
    private function scopeDefinitions(): array
    {
        return [
            'gift-card-remaining' => [
                'label' => 'Gift cards (remaining)',
                'slug' => 'gift-card',
                'match' => static function (Product $product, string $slug, string $handle, bool $enabled): bool {
                    return ! $enabled && $slug === 'gift-card';
                },
            ],
            'telegram-stars' => [
                'label' => 'Telegram Stars',
                'slug' => 'telegram',
                'match' => static function (Product $product, string $slug, string $handle, bool $enabled): bool {
                    return ! $enabled && $slug === 'telegram' && str_contains($handle, 'stars');
                },
            ],
            'steam-topup-cis' => [
                'label' => 'Steam Top-Up (CIS)',
                'slug' => 'steam-wallet',
                'match' => static function (Product $product, string $slug, string $handle, bool $enabled): bool {
                    if ($enabled) {
                        return false;
                    }
                    $h = mb_strtolower($handle);
                    if ($slug === 'steam-wallet') {
                        return true;
                    }
                    if ($slug !== 'gift-card') {
                        return false;
                    }

                    return str_contains($h, 'steam') && str_contains($h, 'cis');
                },
            ],
            'service-topup' => [
                'label' => 'Service top-up',
                'slug' => 'game-topup',
                'match' => static function (Product $product, string $slug, string $handle, bool $enabled): bool {
                    return ! $enabled && $slug === 'game-topup';
                },
            ],
            'game-keys' => [
                'label' => 'Game Keys',
                'slug' => 'game-keys',
                'match' => static function (Product $product, string $slug, string $handle, bool $enabled): bool {
                    return ! $enabled && $slug === 'game-keys';
                },
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyScopeState(string $label, string $slug): array
    {
        return [
            'label' => $label,
            'slug' => $slug,
            'products_examined' => 0,
            'listed_skus' => 0,
            'orderable_skus' => 0,
            'current_feed_rows' => 0,
            'simulated_feed_rows' => 0,
            'eligible_products' => 0,
            'eligible_skus' => 0,
            'remaining_products' => 0,
            'remaining_product_ids' => [],
            'remaining_extras_by_id' => [],
            'remaining_skus' => 0,
            'remaining_extra_rows' => 0,
            'exemptions' => [],
            'exemption_ids' => [],
            'no_orderable_sku_ids' => [],
            'no_orderable_sku_handles' => [],
            'sample_titles' => [],
            'brand_stats' => [],
            'gap_samples' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $s
     */
    private function trackBrand(array &$s, Product $product, TorobFeedCatalogCopy $copy, TorobProductMapper $mapper, Sku $sku): void
    {
        $needle = $copy->brandNeedle($product) ?? 'unmapped';
        $s['brand_stats'][$needle] ??= ['sku_count' => 0, 'products' => [], 'sample_titles' => []];
        $s['brand_stats'][$needle]['sku_count']++;
        $s['brand_stats'][$needle]['products'][(int) $product->id] = true;
        if (count($s['brand_stats'][$needle]['sample_titles']) < 3) {
            $s['brand_stats'][$needle]['sample_titles'][] = $mapper->splitTitle($product, $sku);
        }
    }

    /**
     * @param  list<Sku>  $listed
     * @param  array<string, mixed>  $s
     */
    private function trackMappingGaps(array &$s, Product $product, TorobFeedCatalogCopy $copy, array $listed, string $reason): void
    {
        if ($reason !== TorobSplitDecision::REASON_UNKNOWN_MAPPING) {
            return;
        }
        foreach ($listed as $sku) {
            $gap = $copy->mappingGap($product, $sku);
            if ($gap === '') {
                continue;
            }
            $s['gap_samples'][$gap] ??= ['count' => 0, 'handles' => []];
            $s['gap_samples'][$gap]['count']++;
            $handle = (string) $product->handle;
            if (count($s['gap_samples'][$gap]['handles']) < 30) {
                $s['gap_samples'][$gap]['handles'][$handle] = (int) $product->id;
            }
        }
    }

    /**
     * @param  array<string, array{sku_count: int, products: array<int, bool>, sample_titles: list<string>}>  $brandStats
     * @return list<array<string, mixed>>
     */
    private function majorBrands(array $brandStats): array
    {
        $rows = [];
        foreach ($brandStats as $brand => $stats) {
            $rows[] = [
                'brand' => $brand,
                'sku_count' => $stats['sku_count'],
                'products' => count($stats['products']),
                'sample_titles' => $stats['sample_titles'],
            ];
        }
        usort($rows, static fn (array $a, array $b): int => $b['sku_count'] <=> $a['sku_count']);

        return array_slice($rows, 0, 10);
    }

    /**
     * @param  list<array<string, mixed>>  $brands
     * @return list<array<string, mixed>>
     */
    private function competitorComparison(array $brands): array
    {
        $out = [];
        foreach ($brands as $row) {
            $out[] = [
                'brand' => $row['brand'] ?? '',
                'sample_titles' => array_slice($row['sample_titles'] ?? [], 0, 3),
                'iranian_competitor_titles' => [],
                'status' => 'نیازمند تصمیم کاربر',
                'note' => 'سه عنوان نمونه شارژینو برای بازبینی با رقبا در ترب. مقایسهٔ رقبا قبل از فعال‌سازی مرحله است و اینجا پر نمی‌شود.',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{count: int, handles: array<string, int>}>  $samples
     * @return list<array<string, mixed>>
     */
    private function summarizeGaps(array $samples): array
    {
        $out = [];
        foreach ($samples as $gap => $data) {
            $out[] = [
                'gap' => $gap,
                'count' => $data['count'],
                'sample_handles' => array_slice(array_keys($data['handles']), 0, 15),
            ];
        }
        usort($out, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $out;
    }

    /**
     * @param  array<int, int>  $extrasById
     * @return list<array<string, mixed>>
     */
    private function buildStages(
        array $extrasById,
        int $globalCurrent,
        int $globalBaselineStored,
        int $categoryCurrent,
        string $filePrefix,
    ): array {
        if ($extrasById === []) {
            return [];
        }
        $globalCap = $globalBaselineStored > 0
            ? (int) floor($globalBaselineStored * $this->globalMultiplier())
            : PHP_INT_MAX;
        $maxBatch = $this->maxBatch();
        $maxExtra = $this->maxStageExtraRows();

        $ids = array_keys($extrasById);
        sort($ids);
        $stages = [];
        $batch = [];
        $batchExtra = 0;
        $projGlobal = $globalCurrent;
        $projCat = $categoryCurrent;
        $stage = 0;

        foreach ($ids as $id) {
            $extra = (int) ($extrasById[$id] ?? 0);
            $wouldGlobal = $projGlobal + $extra;
            $wouldCount = count($batch) + 1;
            $wouldExtra = $batchExtra + $extra;
            if ($batch !== []
                && ($wouldGlobal > $globalCap || $wouldCount > $maxBatch || $wouldExtra > $maxExtra)) {
                $stage++;
                $stages[] = $this->stageRow($filePrefix, $stage, $batch, $batchExtra, $projGlobal, $globalBaselineStored, $projCat, $categoryCurrent);
                $batch = [];
                $batchExtra = 0;
            }
            $batch[] = $id;
            $batchExtra += $extra;
            $projGlobal += $extra;
            $projCat += $extra;
        }
        if ($batch !== []) {
            $stage++;
            $stages[] = $this->stageRow($filePrefix, $stage, $batch, $batchExtra, $projGlobal, $globalBaselineStored, $projCat, $categoryCurrent);
        }

        return $stages;
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function stageRow(
        string $prefix,
        int $stage,
        array $ids,
        int $extraRows,
        int $projectedGlobal,
        int $globalBaselineStored,
        int $projectedCategory,
        int $categoryBaseline,
    ): array {
        $name = $prefix.'-stage-'.str_pad((string) $stage, 3, '0', STR_PAD_LEFT).'.json';

        return [
            'file' => $name,
            'stage' => $stage,
            'enable_allowed' => false,
            'note' => 'dry-run only until owner approval',
            'product_ids' => $ids,
            'count' => count($ids),
            'projected_extra_rows' => $extraRows,
            'projected_global_feed_rows' => $projectedGlobal,
            'projected_global_ratio_vs_stored_baseline' => $globalBaselineStored > 0
                ? round($projectedGlobal / $globalBaselineStored, 4)
                : null,
            'projected_category_feed_rows' => $projectedCategory,
            'projected_category_ratio_vs_baseline' => $categoryBaseline > 0
                ? round($projectedCategory / $categoryBaseline, 4)
                : null,
            'under_global_1_5_cap' => $globalBaselineStored <= 0 || $projectedGlobal <= (int) ceil($globalBaselineStored * $this->globalMultiplier()),
            'under_stage_product_cap' => count($ids) <= $this->maxBatch(),
            'under_stage_extra_cap' => $extraRows <= $this->maxStageExtraRows() || count($ids) === 1,
            'category_ratio_informational_only' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function mappingProposals(array $plan): array
    {
        $proposals = ['brands' => [], 'brand_units' => [], 'groups' => [], 'currencies' => [], 'regions' => []];
        foreach ($plan['scopes'] ?? [] as $scope) {
            foreach ($scope['mapping_gaps'] ?? [] as $row) {
                $gap = (string) ($row['gap'] ?? '');
                foreach ($row['sample_handles'] ?? [] as $handle) {
                    $this->proposeForHandle($proposals, $gap, (string) $handle);
                }
            }
        }

        return [
            'note' => 'پیشنهاد فقط؛ تا تأیید صاحب پروژه در config/torob_split_copy.php اعمال نشود.',
            'proposals' => $proposals,
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $proposals
     */
    private function proposeForHandle(array &$proposals, string $gap, string $handle): void
    {
        $h = mb_strtolower($handle);
        if (str_starts_with($gap, 'unknown_brand') || $gap === 'unknown_group') {
            if (preg_match('/^(?:giftcard|topup|gamekey)-([a-z0-9-]+)/', $h, $m) === 1) {
                $needle = $m[1];
                if (! isset($proposals['brands'][$needle])) {
                    $proposals['brands'][$needle] = 'TODO: برند فارسی برای '.$handle;
                }
            }
        }
        if (str_starts_with($gap, 'unknown_currency:')) {
            $code = strtoupper(substr($gap, strlen('unknown_currency:')));
            $proposals['currencies'][$code] ??= 'TODO: واحد پولی فارسی برای '.$code;
        }
        if (str_starts_with($gap, 'unknown_region:')) {
            $code = strtolower(substr($gap, strlen('unknown_region:')));
            $proposals['regions'][$code] ??= 'TODO: ریجن فارسی برای '.$code;
        }
        if ($gap === 'unknown_amount' && str_contains($h, 'premium')) {
            $proposals['brand_units']['telegram-premium'] = 'ماه پرمیوم';
        }
        if ($gap === 'incomplete_title') {
            $proposals['groups']['_note_incomplete'] = 'عنوان بعد از نگاشت هنوز assertCompletePersianTitle را رد می‌کند';
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    public function writeRolloutFiles(array $plan, string $rolloutDir): array
    {
        if (! is_dir($rolloutDir)) {
            mkdir($rolloutDir, 0775, true);
        }
        $written = [];
        foreach ($plan['scopes'] ?? [] as $scopeKey => $scope) {
            foreach ($scope['stages'] ?? [] as $stage) {
                $path = $rolloutDir.DIRECTORY_SEPARATOR.$stage['file'];
                $payload = [
                    'scope' => $scopeKey,
                    'label' => $scope['label'],
                    'stage' => $stage['stage'],
                    'enable_allowed' => false,
                    'product_ids' => $stage['product_ids'],
                    'count' => $stage['count'],
                    'projected_extra_rows' => $stage['projected_extra_rows'],
                    'projected_global_ratio_vs_stored_baseline' => $stage['projected_global_ratio_vs_stored_baseline'],
                    'under_global_1_5_cap' => $stage['under_global_1_5_cap'] ?? true,
                    'note' => 'dry-run rollout file; do not enable without owner approval',
                ];
                file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n");
                $written[] = $path;
            }
        }
        foreach ($plan['combined_telegram_service']['stages'] ?? [] as $stage) {
            $file = 'telegram-plus-service-topup-stage-'.str_pad((string) $stage['stage'], 3, '0', STR_PAD_LEFT).'.json';
            $path = $rolloutDir.DIRECTORY_SEPARATOR.$file;
            $payload = [
                'scope' => 'telegram-plus-service-topup',
                'enable_allowed' => false,
                'product_ids' => $stage['product_ids'],
                'count' => $stage['count'],
                'note' => 'Combined list because telegram+service-topup remaining <= 500 products',
            ];
            file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n");
            $written[] = $path;
        }
        $keep = array_flip($written);
        foreach (glob($rolloutDir.DIRECTORY_SEPARATOR.'*-stage-*.json') ?: [] as $stale) {
            if (is_file($stale) && ! isset($keep[$stale])) {
                unlink($stale);
            }
        }

        return $written;
    }
}

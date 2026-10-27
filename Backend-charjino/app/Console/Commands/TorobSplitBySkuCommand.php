<?php

namespace App\Console\Commands;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Services\Torob\TorobFeedCatalogCopy;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobProductQueryService;
use App\Services\Torob\TorobSplitBaselineStore;
use App\Services\Torob\TorobSplitCanonicalStore;
use App\Services\Torob\TorobSplitPolicy;
use App\Services\Torob\TorobSplitReporter;
use App\Services\Torob\TorobSplitRuntimeStore;
use App\Services\Torob\TorobV3Cache;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class TorobSplitBySkuCommand extends Command
{
    protected $signature = 'torob:split-by-sku
        {action : status|enable|disable|report|measure|compare-prices|flush-cache|freeze-canonical}
        {--group= : اسلاگ گروه کاتالوگ}
        {--ids= : شناسه محصولات جدا با ویرگول}
        {--file= : فایل JSON فهرست شناسه (storage/app/torob/rollout یا database/data/torob/rollout)}
        {--limit=500 : فقط برای dry-run گروهی؛ فعال‌سازی واقعی با --file/--ids است}
        {--handle= : handle محصول برای compare-prices (ویرگول‌جدا)}
        {--simulate-all : در report همهٔ محصولات واجد شرایط را شبیه‌سازی کن}
        {--dry-run : چیزی روی دیسک ننویس}
        {--force : روی production اجازهٔ نوشتن runtime/canonical/کش؛ معافیت‌ها را دور نمی‌زند}
        {--json : خروجی JSON}
        {--all : برای disable، runtime را خالی کن}';

    protected $description = 'فعال‌سازی مرحله‌ای و برگشت‌پذیر تقسیم فید ترب به ازای هر SKU';

    public function handle(
        TorobSplitPolicy $policy,
        TorobSplitRuntimeStore $runtime,
        TorobSplitCanonicalStore $canonical,
        TorobSplitReporter $reporter,
        TorobProductQueryService $query,
        TorobV3Cache $cache,
    ): int {
        return match ($this->argument('action')) {
            'status' => $this->status($policy, $canonical),
            'enable' => $this->enable($policy, $runtime, $canonical, $query, $cache),
            'disable' => $this->disable($policy, $runtime, $canonical, $query, $cache),
            'report' => $this->report($reporter),
            'measure' => $this->measure($reporter),
            'compare-prices' => $this->comparePrices($reporter),
            'flush-cache' => $this->flushCache($cache),
            'freeze-canonical' => $this->freezeCanonical($policy, $canonical, $query),
            default => $this->invalidAction(),
        };
    }

    private function status(TorobSplitPolicy $policy, TorobSplitCanonicalStore $canonical): int
    {
        $payload = [
            'enabled_product_ids' => $policy->enabledProductIds(),
            'enabled_group_slugs' => $policy->enabledGroupSlugs(),
            'exclude_product_ids' => $policy->excludeProductIds(),
            'max_items_per_product' => $policy->maxItemsPerProduct(),
            'rule_version' => $policy->ruleVersion(),
            'frozen_canonical_count' => count($canonical->all()),
            'note' => 'خالی کردن runtime یا env لیست‌ها فید را به یک ردیف در هر محصول برمی‌گرداند (به‌جز ۸۸۷ اگر در env مانده باشد).',
        ];
        $this->emit($payload);
        $this->line('enabled_product_ids='.$this->csvOrNone($payload['enabled_product_ids']));
        $this->line('enabled_group_slugs='.$this->csvOrNone($payload['enabled_group_slugs']));
        $this->line('exclude_product_ids='.$this->csvOrNone($payload['exclude_product_ids']));
        $this->line('max_items_per_product='.$payload['max_items_per_product']);
        $this->line('frozen_canonical_count='.$payload['frozen_canonical_count']);

        return self::SUCCESS;
    }

    private function enable(
        TorobSplitPolicy $policy,
        TorobSplitRuntimeStore $runtime,
        TorobSplitCanonicalStore $canonical,
        TorobProductQueryService $query,
        TorobV3Cache $cache,
    ): int {
        if (! $this->mayWrite()) {
            return self::FAILURE;
        }
        try {
            $requested = $this->optionIds();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && $requested === []) {
            $this->error('فعال‌سازی واقعی فقط با --file یا --ids مجاز است، نه --limit یا --group به‌تنهایی.');

            return self::FAILURE;
        }
        if ($requested === [] && trim((string) $this->option('group')) === '') {
            $this->error('enable به --file، --ids یا (فقط در dry-run) --group نیاز دارد.');

            return self::FAILURE;
        }
        $group = trim((string) $this->option('group'));
        $blocked = $this->blockedGroupSlugs();
        if ($group !== '' && in_array($group, $blocked, true)) {
            $this->error('گروه '.$group.' هرگز فعال نمی‌شود.');

            return self::FAILURE;
        }
        $candidates = $this->candidates($policy, $query, true);
        $maxBatch = max(1, (int) config('torob.split_by_sku.max_batch', 500));
        $already = array_flip($policy->enabledProductIds());
        $skippedEnabled = [];
        $skippedRequested = [];
        if ($requested !== []) {
            $selected = $this->selectRequested($requested, $candidates, $already, $policy, $query, $skippedEnabled, $skippedRequested);
        } else {
            $limit = max(1, (int) $this->option('limit'));
            if ($limit > $maxBatch) {
                $this->warn('limit clamped to '.$maxBatch);
                $limit = $maxBatch;
            }
            foreach ($candidates as $row) {
                if (isset($already[$row['id']])) {
                    $skippedEnabled[] = $row['id'];
                }
            }
            $selected = $this->pickEnableBatch($candidates, $already, $limit);
        }
        if (count($selected) > $maxBatch) {
            $this->error('این فهرست از سقف max_batch='.$maxBatch.' بزرگ‌تر است؛ فایل را به چند مرحله تقسیم کنید.');

            return self::FAILURE;
        }
        $toAdd = array_map(static fn (array $row): int => (int) $row['id'], $selected);
        $currentRows = $query->listableTotal();
        $extraRows = 0;
        foreach ($selected as $row) {
            $extraRows += max(0, (int) ($row['sku_count'] ?? 1) - 1);
        }
        $projectedRows = $currentRows + $extraRows;
        $multiplier = (float) config('torob.split_by_sku.max_feed_multiplier', 1.5);
        $baselineStore = $this->laravel->make(TorobSplitBaselineStore::class);
        $baseline = $dryRun ? ($baselineStore->get() ?: $currentRows) : $baselineStore->remember($currentRows);
        $payload = [
            'dry_run' => $dryRun,
            'file' => trim((string) $this->option('file')),
            'requested_ids' => $requested,
            'eligible' => count($candidates),
            'skipped_already_enabled' => $skippedEnabled,
            'skipped_requested' => $skippedRequested,
            'selected' => $selected,
            'would_enable_ids' => $toAdd,
            'feed_rows_current' => $currentRows,
            'feed_rows_baseline' => $baseline,
            'feed_rows_projected' => $projectedRows,
            'feed_multiplier_limit' => $multiplier,
            'force_bypasses_exemptions' => false,
        ];
        if ($baseline > 0 && $projectedRows > (int) ceil($baseline * $multiplier)) {
            $this->error('رشد فید از '.$multiplier.' برابر وضعیت شروع بیشتر می‌شود؛ متوقف شدم. baseline='.$baseline.' current='.$currentRows.' projected='.$projectedRows);
            $this->emit($payload);

            return self::FAILURE;
        }
        foreach ($skippedRequested as $row) {
            $this->line('skipped_id='.$row['id'].' reason='.$row['reason']);
        }
        if ($dryRun) {
            $this->warn('dry-run: هیچ محصولی فعال نشد.');
            $this->emit($payload);
            $this->printCandidateTable($selected);

            return self::SUCCESS;
        }
        $runtime->merge($toAdd);
        $this->freezeListed($policy, $canonical, $query, $toAdd);
        $flushed = $this->flushFeedCacheSafely($cache);
        $this->info('enabled='.count($toAdd).' frozen_canonical='.count($canonical->all()).' cache_keys_deleted='.$flushed);
        $this->emit($payload);

        return self::SUCCESS;
    }

    private function disable(
        TorobSplitPolicy $policy,
        TorobSplitRuntimeStore $runtime,
        TorobSplitCanonicalStore $canonical,
        TorobProductQueryService $query,
        TorobV3Cache $cache,
    ): int {
        if (! $this->mayWrite()) {
            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->warn('dry-run: disable اجرا نشد.');

            return self::SUCCESS;
        }
        try {
            $ids = $this->optionIds();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $group = trim((string) $this->option('group'));
        if ($this->option('all')) {
            $ids = array_values(array_unique(array_merge($ids, $runtime->productIds())));
            $runtime->clear();
        } else {
            if ($group !== '') {
                $groupIds = $this->listableInGroup($query, $group)
                    ->pluck((new Product)->getQualifiedKeyName())
                    ->map(fn ($id): int => (int) $id)
                    ->all();
                $ids = array_values(array_unique(array_merge($ids, $groupIds)));
            }
            if ($ids === []) {
                $this->error('برای disable باید --ids یا --file یا --group یا --all بدهید.');

                return self::FAILURE;
            }
            $runtime->removeProductIds($ids);
            if ($group !== '') {
                $runtime->removeGroupSlugs([$group]);
            }
        }
        $stillEnabled = array_flip($policy->enabledProductIds());
        $forget = [];
        foreach ($ids as $id) {
            if (! isset($stillEnabled[(int) $id])) {
                $forget[] = (int) $id;
            }
        }
        if ($this->option('all')) {
            foreach (array_keys($canonical->all()) as $pid) {
                if (! isset($stillEnabled[(int) $pid])) {
                    $forget[] = (int) $pid;
                }
            }
            $forget = array_values(array_unique($forget));
        }
        $unfrozen = $canonical->forgetMany($forget);
        $flushed = $this->flushFeedCacheSafely($cache);
        $this->info('removed_runtime_ids='.$this->csvOrNone($ids));
        $this->line('unfrozen_canonical='.$unfrozen);
        $this->line('cache_keys_deleted='.$flushed);
        $this->line('effective_enabled_ids='.$this->csvOrNone($policy->enabledProductIds()));

        return self::SUCCESS;
    }

    private function report(TorobSplitReporter $reporter): int
    {
        $payload = $reporter->report((bool) $this->option('simulate-all'));
        $this->emit($payload);
        $this->info('listable_products='.$payload['listable_products']);
        $this->line('multi_sku_products='.$payload['multi_sku_products']);
        $this->line('listed_skus='.($payload['listed_skus'] ?? 0));
        $this->line('orderable_skus='.($payload['orderable_skus'] ?? 0));
        $this->line('product_chunks_scanned='.($payload['product_chunks_scanned'] ?? 0));
        $this->line('live_feed_pages_read='.($payload['live_feed_pages_read'] ?? 0));
        $this->line('completeness='.($payload['completeness'] ?? ''));
        $this->line('current_feed_rows='.$payload['current_feed_rows']);
        $this->line('simulated_feed_rows='.$payload['simulated_feed_rows']);
        $this->line('row_multiplier='.$payload['row_multiplier']);
        if ($payload['recommend_priority_only']) {
            $this->warn('ردیف‌های شبیه‌سازی‌شده بیش از دو برابر فید فعلی است؛ فقط محصولات اولویت‌دار را فعال کنید.');
        }
        $this->line('elapsed_ms='.$payload['elapsed_ms']);
        $counts = is_array($payload['exemptions']['counts'] ?? null) ? $payload['exemptions']['counts'] : [];
        $samples = is_array($payload['exemptions']['samples'] ?? null) ? $payload['exemptions']['samples'] : [];
        foreach (['over_max', 'ambiguous_titles', 'empty_labels', 'unknown_mapping', 'excluded', 'no_orderable_sku'] as $reason) {
            $this->line('exemption_'.$reason.'='.(int) ($counts[$reason] ?? 0));
            $ids = is_array($payload['exemptions']['ids'][$reason] ?? null) ? $payload['exemptions']['ids'][$reason] : [];
            if ($ids !== []) {
                $this->line('exemption_'.$reason.'_ids='.implode(',', array_slice($ids, 0, 80)));
            }
            foreach (array_slice(is_array($samples[$reason] ?? null) ? $samples[$reason] : [], 0, 20) as $row) {
                $this->line('sample_'.$reason.'_handle='.(string) ($row['handle'] ?? ''));
                $this->line('sample_'.$reason.'_detail='.(string) ($row['detail'] ?? ''));
                $this->line('sample_'.$reason.'_group='.(string) ($row['group'] ?? '').' skus='.(string) ($row['orderable_skus'] ?? '').' id='.(string) ($row['id'] ?? ''));
            }
        }
        foreach ($payload['exemptions']['no_orderable_sku'] ?? [] as $row) {
            $this->line('no_orderable_sku id='.(string) ($row['id'] ?? '').' handle='.(string) ($row['handle'] ?? '').' group='.(string) ($row['group'] ?? ''));
        }

        return self::SUCCESS;
    }

    private function measure(TorobSplitReporter $reporter): int
    {
        $payload = $reporter->measure();
        $this->emit($payload);
        foreach ($payload['samples'] as $sample) {
            $this->line($sample['label']
                .' cold_ms='.$sample['cold']['ms']
                .' warm_ms='.$sample['warm']['ms']
                .' cold_queries='.$sample['cold']['queries']
                .' rows='.$sample['cold']['rows']
                .' bytes='.$sample['cold']['output_bytes']);
        }
        $this->line('cache_store='.$payload['cache']['store'].' keys='.$payload['cache']['keys']);

        return self::SUCCESS;
    }

    private function comparePrices(TorobSplitReporter $reporter): int
    {
        $handles = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('handle')))));
        if ($handles === []) {
            $this->error('compare-prices به --handle=slug نیاز دارد.');

            return self::FAILURE;
        }
        $rows = $reporter->comparePrices($handles);
        $this->emit(['rows' => $rows]);
        $mismatch = 0;
        foreach ($rows as $row) {
            if (! ($row['match'] ?? false)) {
                $mismatch++;
                $this->warn(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            }
        }
        $this->info('compared='.count($rows).' mismatch='.$mismatch);

        return $mismatch === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function flushCache(TorobV3Cache $cache): int
    {
        if (! $this->mayWrite()) {
            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->warn('dry-run: flush انجام نشد. '.$this->inspectLine($cache));

            return self::SUCCESS;
        }
        $deleted = $cache->forgetAll();
        $this->info('deleted_torob_v3_keys='.$deleted);
        $this->line($this->inspectLine($cache));

        return self::SUCCESS;
    }

    private function freezeCanonical(
        TorobSplitPolicy $policy,
        TorobSplitCanonicalStore $canonical,
        TorobProductQueryService $query,
    ): int {
        if (! $this->mayWrite()) {
            return self::FAILURE;
        }
        $ids = $this->optionIds();
        if ($ids === []) {
            $ids = $policy->enabledProductIds();
        }
        if ($this->option('dry-run')) {
            $this->warn('dry-run: freeze نوشته نشد. ids='.implode(',', $ids));

            return self::SUCCESS;
        }
        $written = $this->freezeListed($policy, $canonical, $query, $ids);
        $this->info('frozen='.$written);

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $ids
     */
    private function freezeListed(
        TorobSplitPolicy $policy,
        TorobSplitCanonicalStore $canonical,
        TorobProductQueryService $query,
        array $ids,
    ): int {
        if ($ids === []) {
            return 0;
        }
        $products = $query->withRelations($query->listableQuery())
            ->whereIn((new Product)->getQualifiedKeyName(), $ids)
            ->get();
        $pairs = [];
        foreach ($products as $product) {
            if (! $product instanceof Product) {
                continue;
            }
            $sku = $policy->canonicalSku($product);
            if ($sku !== null) {
                $pairs[(int) $product->id] = (int) $sku->id;
            }
        }
        $canonical->putMany($pairs);

        return count($pairs);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function candidates(TorobSplitPolicy $policy, TorobProductQueryService $query, bool $treatAsEnabled): array
    {
        $ids = $this->optionIds();
        $group = trim((string) $this->option('group'));
        $builder = $query->withRelations($query->listableQuery());
        if ($ids !== []) {
            $builder->whereIn((new Product)->getQualifiedKeyName(), $ids);
        } elseif ($group !== '') {
            $builder = $this->listableInGroup($query, $group);
        } else {
            $this->error('enable به --group یا --ids نیاز دارد.');

            return [];
        }
        $mapper = $this->laravel->make(TorobProductMapper::class);
        $copy = $this->laravel->make(TorobFeedCatalogCopy::class);
        $rows = [];
        $lastId = 0;
        $key = (new Product)->getQualifiedKeyName();
        while (true) {
            $page = (clone $builder)->where($key, '>', $lastId)->orderBy($key)->limit(100)->get();
            if ($page->isEmpty()) {
                break;
            }
            foreach ($page as $product) {
                if (! $product instanceof Product) {
                    continue;
                }
                if (str_contains(mb_strtolower((string) $product->handle), 'game-pass')) {
                    continue;
                }
                $slug = trim((string) ($product->catalogGroup?->slug ?? ''));
                if ($slug !== '' && in_array($slug, $this->blockedGroupSlugs(), true)) {
                    continue;
                }
                $decision = $policy->decide($product, $treatAsEnabled);
                if (! $decision->split) {
                    continue;
                }
                $maxPrice = 0;
                $amounts = [];
                $sample = [];
                foreach ($decision->skus as $sku) {
                    $maxPrice = max($maxPrice, $sku->sellPrice());
                    $amount = $copy->skuNumericAmount($sku);
                    if ($amount !== null) {
                        $amounts[$amount] = true;
                    }
                    if (count($sample) < 3) {
                        $sample[] = $mapper->splitTitle($product, $sku);
                    }
                }
                $rows[] = [
                    'id' => (int) $product->id,
                    'handle' => (string) $product->handle,
                    'title' => (string) $product->title,
                    'group' => (string) ($product->catalogGroup?->slug ?? ''),
                    'sku_count' => count($decision->skus),
                    'denom_count' => count($amounts),
                    'max_price' => $maxPrice,
                    'brand_score' => $this->giftBrandScore($product),
                    'family' => $this->giftBrandFamily($product),
                    'sample_titles' => $sample,
                ];
            }
            $lastId = (int) $page->last()->getKey();
        }
        usort($rows, static function (array $a, array $b): int {
            return [$b['brand_score'], $b['denom_count'], $b['max_price'], $b['sku_count'], $a['id']]
                <=> [$a['brand_score'], $a['denom_count'], $a['max_price'], $a['sku_count'], $b['id']];
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<int, int|true>  $already
     * @return list<array<string, mixed>>
     */
    private function pickEnableBatch(array $candidates, array $already, int $limit): array
    {
        $order = ['rewarble', 'razer', 'google-play', 'itunes', 'playstation', 'xbox', 'steam', 'amazon', 'netflix', 'spotify'];
        $buckets = [];
        foreach ($candidates as $row) {
            if (isset($already[$row['id']])) {
                continue;
            }
            $family = (string) ($row['family'] ?? 'other');
            $buckets[$family][] = $row;
        }
        $selected = [];
        $progress = true;
        while (count($selected) < $limit && $progress) {
            $progress = false;
            foreach ($order as $family) {
                if (count($selected) >= $limit) {
                    break;
                }
                if (($buckets[$family] ?? []) === []) {
                    continue;
                }
                $selected[] = array_shift($buckets[$family]);
                $progress = true;
            }
        }
        if (count($selected) >= $limit) {
            return $selected;
        }
        $picked = array_flip(array_map(static fn (array $row): int => (int) $row['id'], $selected));
        foreach ($candidates as $row) {
            if (count($selected) >= $limit) {
                break;
            }
            if (isset($already[$row['id']]) || isset($picked[$row['id']])) {
                continue;
            }
            $selected[] = $row;
        }

        return $selected;
    }

    private function giftBrandFamily(Product $product): string
    {
        $handle = mb_strtolower(trim((string) $product->handle));
        if (str_contains($handle, 'rewarble')) {
            return 'rewarble';
        }
        if (str_contains($handle, 'razer')) {
            return 'razer';
        }
        if (str_contains($handle, 'google-play')) {
            return 'google-play';
        }
        if (str_contains($handle, 'itunes') || str_contains($handle, 'app-store')) {
            return 'itunes';
        }
        if (str_starts_with($handle, 'giftcard-playstation')) {
            return 'playstation';
        }
        if (str_starts_with($handle, 'giftcard-xbox')) {
            return 'xbox';
        }
        if (str_starts_with($handle, 'giftcard-steam')) {
            return 'steam';
        }
        if (str_starts_with($handle, 'giftcard-amazon')) {
            return 'amazon';
        }
        if (str_starts_with($handle, 'giftcard-netflix')) {
            return 'netflix';
        }
        if (str_contains($handle, 'spotify')) {
            return 'spotify';
        }

        return 'other';
    }

    private function giftBrandScore(Product $product): int
    {
        $handle = mb_strtolower(trim((string) $product->handle));
        $family = $this->giftBrandFamily($product);
        $base = match ($family) {
            'rewarble' => str_contains($handle, 'eur') ? 100 : 70,
            'razer' => 95,
            'google-play' => 92,
            'itunes' => 90,
            'playstation' => 86,
            'xbox' => 84,
            'steam' => 82,
            'amazon' => 80,
            'netflix' => 78,
            'spotify' => 76,
            default => 0,
        };
        foreach (['-us', '-global', '-eu', '-uk', '-de'] as $i => $suffix) {
            if (str_ends_with($handle, $suffix)) {
                $base += 10 - $i;

                break;
            }
        }

        return $base;
    }

    /**
     * @return Builder<Product>
     */
    private function listableInGroup(TorobProductQueryService $query, string $slug): Builder
    {
        $groupId = CatalogGroup::query()->where('slug', $slug)->value('id');
        $builder = $query->withRelations($query->listableQuery());
        if ($groupId) {
            $builder->where('catalog_group_id', (int) $groupId);
        } else {
            $builder->whereRaw('0 = 1');
        }

        return $builder;
    }

    /**
     * @return list<int>
     */
    private function optionIds(): array
    {
        $ids = [];
        foreach ($this->idsFromFile() as $id) {
            $ids[$id] = $id;
        }
        foreach (explode(',', (string) $this->option('ids')) as $id) {
            $id = (int) trim($id);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @return list<int>
     */
    private function idsFromFile(): array
    {
        $file = trim((string) $this->option('file'));
        if ($file === '') {
            return [];
        }
        $path = $this->resolveRolloutFile($file);
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('خواندن فایل فهرست شناسه ممکن نشد: '.$path);
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('فایل فهرست شناسه JSON نامعتبر است: '.$path);
        }
        $values = $decoded['product_ids'] ?? (array_is_list($decoded) ? $decoded : null);
        if (! is_array($values)) {
            throw new \RuntimeException('فایل باید آرایه یا کلید product_ids داشته باشد: '.$path);
        }
        $ids = [];
        foreach ($values as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function resolveRolloutFile(string $file): string
    {
        $name = basename($file);
        $candidates = [];
        if ($file !== $name || str_contains($file, '/') || str_contains($file, '\\')) {
            $candidates[] = $file;
        }
        $candidates[] = rtrim((string) config('torob.split_by_sku.rollout_dir', storage_path('app/torob/rollout')), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
        $candidates[] = rtrim((string) config('torob.split_by_sku.rollout_source_dir', database_path('data/torob/rollout')), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
        $candidates[] = storage_path('app/torob/rollout/'.$name);
        $candidates[] = database_path('data/torob/rollout/'.$name);
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('فایل فهرست شناسه پیدا نشد: '.$file);
    }

    /**
     * @param  list<int>  $requested
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<int, int|true>  $already
     * @param  list<int>  $skippedEnabled
     * @param  list<array<string, mixed>>  $skippedRequested
     * @return list<array<string, mixed>>
     */
    private function selectRequested(
        array $requested,
        array $candidates,
        array $already,
        TorobSplitPolicy $policy,
        TorobProductQueryService $query,
        array &$skippedEnabled,
        array &$skippedRequested,
    ): array {
        $byId = [];
        foreach ($candidates as $row) {
            $byId[(int) $row['id']] = $row;
        }
        $selected = [];
        foreach ($requested as $id) {
            $id = (int) $id;
            if (isset($already[$id])) {
                $skippedEnabled[] = $id;

                continue;
            }
            if (isset($byId[$id])) {
                $selected[] = $byId[$id];

                continue;
            }
            $skippedRequested[] = [
                'id' => $id,
                'reason' => $this->skipReasonForId($id, $policy, $query),
            ];
        }

        return $selected;
    }

    private function skipReasonForId(int $id, TorobSplitPolicy $policy, TorobProductQueryService $query): string
    {
        $product = Product::query()->with(['skus', 'catalogGroup'])->find($id);
        if (! $product instanceof Product) {
            return 'not_found';
        }
        $slug = trim((string) ($product->catalogGroup?->slug ?? ''));
        if ($slug !== '' && in_array($slug, $this->blockedGroupSlugs(), true)) {
            return 'blocked_group:'.$slug;
        }
        if (str_contains(mb_strtolower((string) $product->handle), 'game-pass')) {
            return 'game_pass_skipped';
        }
        $listable = $query->withRelations($query->listableQuery())->whereKey($id)->first();
        if (! $listable instanceof Product) {
            return 'not_listable';
        }
        $decision = $policy->decide($listable, true);

        return $decision->split ? 'not_eligible' : (string) ($decision->reason ?? 'not_eligible');
    }

    /**
     * @return list<string>
     */
    private function blockedGroupSlugs(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $slug): string => trim((string) $slug),
            is_array(config('torob_split_copy.blocked_group_slugs'))
                ? config('torob_split_copy.blocked_group_slugs')
                : ['steam-gifts', 'manual-services'],
        ), static fn (string $slug): bool => $slug !== ''));
    }

    private function flushFeedCacheSafely(TorobV3Cache $cache): int
    {
        try {
            return $cache->forgetAll();
        } catch (\RuntimeException $e) {
            $this->warn($e->getMessage());

            return 0;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function printCandidateTable(array $rows): void
    {
        if ($rows === []) {
            $this->line('no candidates');

            return;
        }
        $this->table(
            ['id', 'handle', 'skus', 'denoms', 'family', 'sample_title'],
            array_map(
                static fn (array $row): array => [
                    $row['id'],
                    $row['handle'],
                    $row['sku_count'],
                    $row['denom_count'] ?? '',
                    $row['family'] ?? '',
                    (string) (($row['sample_titles'][0] ?? $row['title'] ?? '')),
                ],
                $rows,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emit(array $payload): void
    {
        if (! $this->option('json')) {
            return;
        }
        $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}');
    }

    private function inspectLine(TorobV3Cache $cache): string
    {
        $inspect = $cache->inspect();

        return 'cache_store='.$inspect['store'].' keys='.$inspect['keys'].' bytes='.(string) ($inspect['bytes'] ?? 'n/a');
    }

    private function mayWrite(): bool
    {
        if ($this->option('dry-run')) {
            return true;
        }
        if (! app()->isProduction()) {
            return true;
        }
        if ($this->option('force')) {
            return true;
        }
        $this->error('روی production برای نوشتن --force یا --dry-run لازم است. هیچ محصول جدیدی فعال نشد.');

        return false;
    }

    /**
     * @param  list<int|string>  $values
     */
    private function csvOrNone(array $values): string
    {
        return $values === [] ? '(none)' : implode(',', $values);
    }

    private function invalidAction(): int
    {
        $this->error('action نامعتبر است. status|enable|disable|report|measure|compare-prices|flush-cache|freeze-canonical');

        return self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Torob\TorobFeedCatalogCopy;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobProductQueryService;
use App\Services\Torob\TorobRolloutPlanner;
use App\Services\Torob\TorobSplitBaselineStore;
use App\Services\Torob\TorobSplitPolicy;
use App\Services\Torob\TorobV3Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TorobRolloutPlanCommand extends Command
{
    protected $signature = 'torob:rollout-plan
        {--write : فایل‌های rollout و گزارش را در storage/app/torob/rollout بنویس}
        {--json : خروجی JSON در stdout}';

    protected $description = 'dry-run کامل دسته‌های rollout (بدون فعال‌سازی)';

    public function handle(
        TorobRolloutPlanner $planner,
        TorobProductQueryService $query,
        TorobSplitPolicy $policy,
        TorobProductMapper $mapper,
        TorobFeedCatalogCopy $copy,
        TorobSplitBaselineStore $baselineStore,
        TorobV3Cache $cache,
    ): int {
        $globalCurrent = $query->listableTotal();
        $globalStored = $baselineStore->get() ?: $globalCurrent;
        $plan = $planner->plan($query, $policy, $mapper, $copy, $globalCurrent, $globalStored);
        $plan['cache_probe'] = $this->cacheProbe($cache, $query);
        $proposals = $planner->mappingProposals($plan);
        $plan['mapping_proposals'] = $proposals;

        $rolloutDir = (string) config('torob.split_by_sku.rollout_dir', storage_path('app/torob/rollout'));
        if ($this->option('write')) {
            $written = $planner->writeRolloutFiles($plan, $rolloutDir);
            $reportPath = $rolloutDir.DIRECTORY_SEPARATOR.'DRYRUN-PLAN.json';
            file_put_contents($reportPath, json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n");
            $proposalPath = $rolloutDir.DIRECTORY_SEPARATOR.'MAPPING-PROPOSALS.json';
            file_put_contents($proposalPath, json_encode($proposals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n");
            $this->info('wrote '.$reportPath);
            $this->info('wrote '.$proposalPath);
            $this->line('rollout_files='.count($written));
        }

        if ($this->option('json')) {
            $this->line(json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}');
        } else {
            $this->printSummary($plan);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheProbe(TorobV3Cache $cache, TorobProductQueryService $query): array
    {
        $inspectBefore = $cache->inspect();
        // Response cache is only written by TorobProductController (HTTP), not QueryService.
        $inspectAfter = $inspectBefore;
        $store = Cache::getStore();
        $prefix = method_exists($store, 'getPrefix') ? $store->getPrefix() : '';
        $logical = TorobV3Cache::PREFIX.'*';
        $redisMatch = $prefix.$logical;

        return [
            'cache_default' => config('cache.default'),
            'config_prefix' => config('cache.prefix'),
            'store_prefix' => $prefix,
            'logical_key_prefix' => TorobV3Cache::PREFIX,
            'redis_scan_match' => $redisMatch,
            'inspect_before' => $inspectBefore,
            'inspect_after_warm_page1' => $inspectAfter,
            'note' => 'کلیدهای torob:v3 فقط روی درخواست HTTP POST /api/torob/v3/products ساخته می‌شوند؛ artisan measure/report کلید نمی‌سازد. flush با redis_scan_match بالا.',
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function printSummary(array $plan): void
    {
        $this->info('products_scanned='.$plan['products_scanned'].' chunks='.$plan['product_chunks']);
        $this->line('global_current='.$plan['global_feed_rows_current'].' stored_baseline='.$plan['global_baseline_stored']);
        $this->line('if_all_remaining='.$plan['global_feed_rows_if_all_scopes_remaining']
            .' ratio_vs_stored='.$plan['global_ratio_if_all_vs_stored_baseline']);
        foreach ($plan['scopes'] ?? [] as $key => $scope) {
            $this->line($key.': examined='.$scope['products_examined']
                .' remaining='.$scope['remaining_products']
                .' extra_rows='.$scope['remaining_extra_rows']
                .' exemptions='.json_encode($scope['exemptions'], JSON_UNESCAPED_UNICODE));
            $noOrderable = $scope['no_orderable_sku_ids'] ?? [];
            if ($noOrderable !== []) {
                $this->line($key.'_no_orderable_sku_ids='.implode(',', $noOrderable));
            }
            foreach ($scope['exemption_ids'] ?? [] as $reason => $ids) {
                $this->line($key.'_exemption_'.$reason.'_ids='.implode(',', array_slice($ids, 0, 80)));
            }
            foreach ($scope['competitor_comparison'] ?? [] as $row) {
                $samples = implode(' | ', $row['sample_titles'] ?? []);
                $this->line($key.'_brand_'.$row['brand'].'_samples='.$samples);
            }
        }
        $probe = $plan['cache_probe'] ?? [];
        $this->line('redis_scan_match='.($probe['redis_scan_match'] ?? ''));
        $this->line('cache_keys_after_warm='.($probe['inspect_after_warm_page1']['keys'] ?? 0));
    }
}

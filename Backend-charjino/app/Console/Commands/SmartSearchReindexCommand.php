<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Sku;
use App\Modules\SmartSearch\MeiliClientFactory;
use App\Modules\SmartSearch\SearchIndexSettings;
use App\Modules\SmartSearch\SearchIndexWriteRegistry;
use App\Modules\SmartSearch\SmartSearchIndexManager;
use App\Modules\SmartSearch\SmartSearchSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SmartSearchReindexCommand extends Command
{
    protected $signature = 'smart-search:reindex {--direct : پر کردن ایندکس فعال بدون swap}';

    protected $description = 'بازسازی ایندکس Smart Search از MySQL';

    public function handle(): int
    {
        $lock = Cache::lock('smart-search-reindex', 7200);
        if (! $lock->get()) {
            $this->error('reindex already running');

            return self::FAILURE;
        }
        try {
            $manager = SmartSearchIndexManager::make();
            $sync = SmartSearchSyncService::make();
            $started = now();
            $direct = (bool) $this->option('direct') || $this->indexesEmpty();
            if ($direct) {
                $this->info('mode=direct');
                $manager->configureAll();
                $sync->reindexAll(fn (int $n) => $this->line('products_docs='.$n));
                $this->catchUp($sync, SearchIndexWriteRegistry::active()['products'], $started);
                $this->info('reindex done');

                return self::SUCCESS;
            }

            return $this->swapReindex($manager, $sync, $started);
        } finally {
            SearchIndexWriteRegistry::setBarrier(false);
            SearchIndexWriteRegistry::clearMirrors();
            $lock->release();
        }
    }

    private function indexesEmpty(): bool
    {
        try {
            $uid = SearchIndexWriteRegistry::active()['products'];
            $stats = MeiliClientFactory::makeForWrite()->index($uid)->stats();
            $count = (int) ($stats['numberOfDocuments'] ?? 0);

            return $count < 1;
        } catch (\Throwable) {
            return true;
        }
    }

    private function swapReindex(SmartSearchIndexManager $manager, SmartSearchSyncService $sync, \Carbon\CarbonInterface $started): int
    {
        $this->info('mode=swap');
        $logical = SearchIndexWriteRegistry::active();
        $stamp = now()->format('YmdHis');
        $temps = [
            'products' => $logical['products'].'__next__'.$stamp,
            'brands' => $logical['brands'].'__next__'.$stamp,
            'taxonomies' => $logical['taxonomies'].'__next__'.$stamp,
        ];
        $syn = SmartSearchIndexManager::synonymMap();
        $manager->ensureIndex($temps['products'], SearchIndexSettings::products(), $syn);
        $manager->ensureIndex($temps['brands'], SearchIndexSettings::brands(), $syn);
        $manager->ensureIndex($temps['taxonomies'], SearchIndexSettings::taxonomies(), $syn);
        SearchIndexWriteRegistry::setMirrors($temps);
        $sync->rebuildProducts($temps['products'], fn (int $n) => $this->line('products_docs='.$n));
        $sync->rebuildBrands($temps['brands']);
        $sync->rebuildTaxonomies($temps['taxonomies']);
        $this->catchUp($sync, $temps['products'], $started);
        SearchIndexWriteRegistry::setBarrier(true);
        $this->catchUp($sync, $temps['products'], $started);
        $client = MeiliClientFactory::makeForWrite();
        foreach (['products', 'brands', 'taxonomies'] as $name) {
            $task = $client->swapIndexes([[$logical[$name], $temps[$name]]]);
            MeiliClientFactory::wait($client, $task['taskUid'] ?? 0, 180000);
        }
        SearchIndexWriteRegistry::clearMirrors();
        SearchIndexWriteRegistry::setBarrier(false);
        $this->info('swap done; old indexes retained as '.$temps['products']);

        return self::SUCCESS;
    }

    private function catchUp(SmartSearchSyncService $sync, string $uid, \Carbon\CarbonInterface $started): void
    {
        $ids = Product::query()->where('updated_at', '>=', $started->copy()->subMinute())->pluck('id')
            ->merge(Sku::query()->where('updated_at', '>=', $started->copy()->subMinute())->pluck('product_id'))
            ->unique()
            ->filter()
            ->values()
            ->all();
        $sync->catchUpProducts(array_map('intval', $ids), $uid);
    }
}

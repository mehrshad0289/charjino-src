<?php

namespace App\Console\Commands;

use App\Modules\SmartSearch\SmartSearchIndexManager;
use Illuminate\Console\Command;

class SmartSearchSyncSynonymsCommand extends Command
{
    protected $signature = 'smart-search:sync-synonyms';

    protected $description = 'مترادف‌های فعال را به هر سه ایندکس Meilisearch می‌فرستد';

    public function handle(): int
    {
        SmartSearchIndexManager::make()->configureAll();
        $this->info('synonyms='.count(SmartSearchIndexManager::synonymMap()));

        return self::SUCCESS;
    }
}

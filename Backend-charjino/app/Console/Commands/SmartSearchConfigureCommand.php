<?php

namespace App\Console\Commands;

use App\Modules\SmartSearch\SearchSynonymCatalog;
use App\Modules\SmartSearch\SmartSearchIndexManager;
use Illuminate\Console\Command;

class SmartSearchConfigureCommand extends Command
{
    protected $signature = 'smart-search:configure {--seed-synonyms : upsert مجموعهٔ اولیه مترادف اگر خالی باشد}';

    protected $description = 'ساخت ایندکس‌های Meilisearch و اعمال settings/synonyms بدون وارد کردن سند';

    public function handle(): int
    {
        if ($this->option('seed-synonyms')) {
            $n = SearchSynonymCatalog::seed();
            $this->info('synonyms_seeded='.$n);
        }
        SmartSearchIndexManager::make()->configureAll();
        $this->info('configured products/brands/taxonomies');

        return self::SUCCESS;
    }
}

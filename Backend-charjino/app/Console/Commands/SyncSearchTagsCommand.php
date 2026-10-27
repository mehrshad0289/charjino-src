<?php

namespace App\Console\Commands;

use App\Modules\Catalog\SearchTagBank;
use Illuminate\Console\Command;

class SyncSearchTagsCommand extends Command
{
    protected $signature = 'charjino:sync-search-tags';

    protected $description = 'بانک برچسب جستجو را می‌سازد و به محصولات مطابق الگو وصل می‌کند';

    public function handle(): int
    {
        $result = SearchTagBank::sync();
        $this->info('tags='.$result['tags'].' attached='.$result['attached']);

        return self::SUCCESS;
    }
}

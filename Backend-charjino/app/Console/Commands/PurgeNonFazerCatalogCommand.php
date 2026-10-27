<?php

namespace App\Console\Commands;

use App\Modules\Catalog\CatalogSeed;
use Illuminate\Console\Command;

class PurgeNonFazerCatalogCommand extends Command
{
    protected $signature = 'charjino:purge-non-fazer';

    protected $description = 'حذف سید و Shop2 از دیتابیس؛ فقط کاتالوگ FazerCards می‌ماند';

    public function handle(): int
    {
        $result = CatalogSeed::purgeFromDatabase();
        $this->info(
            'products='.$result['products']
            .' skus='.$result['skus']
            .' banners='.$result['banners']
            .' groups='.$result['groups']
        );

        return self::SUCCESS;
    }
}

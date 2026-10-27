<?php

namespace App\Console\Commands;

use App\Modules\Catalog\CatalogOrigin;
use Illuminate\Console\Command;

class PurgeShop2CatalogCommand extends Command
{
    protected $signature = 'charjino:purge-shop2';

    protected $description = 'حذف فیزیکی کاتالوگ Shop2 از دیتابیس؛ محصولات FazerCards می‌مانند';

    public function handle(): int
    {
        $result = CatalogOrigin::purgeShop2Catalog();
        $this->info('groups='.$result['groups'].' products='.$result['products'].' skus='.$result['skus']);

        return self::SUCCESS;
    }
}

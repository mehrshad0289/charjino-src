<?php

namespace App\Console\Commands;

use App\Modules\Catalog\CatalogUrlLifecycle;
use Illuminate\Console\Command;

class ExpireUrlLifecycleCommand extends Command
{
    protected $signature = 'charjino:expire-url-lifecycle';

    protected $description = 'محصولات hideMissing که بعد از مهلت برنگشتند را ۳۰۱ می‌کند';

    public function handle(CatalogUrlLifecycle $lifecycle): int
    {
        $count = $lifecycle->expireTemporaryUnavailable();
        $this->info('expired='.$count);

        return self::SUCCESS;
    }
}

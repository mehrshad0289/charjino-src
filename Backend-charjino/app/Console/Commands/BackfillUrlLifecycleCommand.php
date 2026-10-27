<?php

namespace App\Console\Commands;

use App\Modules\Catalog\CatalogUrlLifecycle;
use Illuminate\Console\Command;

class BackfillUrlLifecycleCommand extends Command
{
    protected $signature = 'charjino:backfill-url-lifecycle';

    protected $description = 'رکورد چرخهٔ عمر برای URLهای خارج‌شده و اصلاح مثبت کاذب لاگین';

    public function handle(CatalogUrlLifecycle $lifecycle): int
    {
        $restored = $lifecycle->restoreFalseLoginExclusions();
        $backfilled = $lifecycle->backfillKnownHandles(CatalogUrlLifecycle::departedSampleHandles());
        $this->info('restoredLoginFalsePositives='.$restored.' backfilled='.$backfilled);

        return self::SUCCESS;
    }
}

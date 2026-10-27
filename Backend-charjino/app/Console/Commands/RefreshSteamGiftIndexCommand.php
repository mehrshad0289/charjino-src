<?php

namespace App\Console\Commands;

use App\Modules\Catalog\SteamGiftIndexability;
use Illuminate\Console\Command;

class RefreshSteamGiftIndexCommand extends Command
{
    protected $signature = 'charjino:refresh-steam-gift-index';

    protected $description = 'گیفت استیم ایندکس‌پذیر را به حدود ۵۰۰ بازی پرتقاضا محدود می‌کند؛ صفحه حذف نمی‌شود';

    public function handle(SteamGiftIndexability $indexability): int
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        $result = $indexability->refresh();
        $this->info(
            'indexable='.$result['indexable']
            .' sold='.$result['sold']
            .' popular='.$result['popular']
            .' blocked='.$result['blocked']
            .' free='.($result['free'] ?? 0)
            .' total='.$result['total']
        );

        return self::SUCCESS;
    }
}

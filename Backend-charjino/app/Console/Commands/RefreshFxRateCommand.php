<?php

namespace App\Console\Commands;

use App\Modules\Pricing\FxRateService;
use Illuminate\Console\Command;

class RefreshFxRateCommand extends Command
{
    protected $signature = 'charjino:refresh-fx';

    protected $description = 'بروزرسانی نرخ دلار و قیمت فروش تومانی';

    public function handle(FxRateService $fx): int
    {
        $result = $fx->refresh();
        $this->info('rate='.$result['usdTomanRate'].' margin='.$result['marginPercent']);

        return self::SUCCESS;
    }
}

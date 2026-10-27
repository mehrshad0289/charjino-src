<?php

namespace App\Console\Commands;

use App\Modules\Ops\AdminAlertService;
use App\Modules\Supplier\FazerCards\FazerCardsBalance;
use App\Support\Money;
use Illuminate\Console\Command;

class FazerBalanceWatchCommand extends Command
{
    protected $signature = 'charjino:fazer-balance-watch';

    protected $description = 'Open LOW_BALANCE admin alert when Fazer wallet is low';

    public function handle(FazerCardsBalance $balance, AdminAlertService $alerts): int
    {
        $snap = $balance->snapshot();
        $min = Money::string(config('charjino.orders.low_balance_usd', '20'));
        if (Money::compare($snap['balance'], $min) < 0) {
            $alerts->open('LOW_BALANCE', 'balance', ['balance' => $snap['balance']]);
            $this->warn('low '.$snap['balance']);
        } else {
            $alerts->resolve('balance');
        }
        cache()->put('scheduler:fazer-balance-watch', now()->toIso8601String(), 3600);

        return self::SUCCESS;
    }
}

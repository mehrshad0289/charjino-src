<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftShop;
use Illuminate\Console\Command;

class CurateSteamGiftsShopCommand extends Command
{
    protected $signature = 'charjino:curate-steam-gifts';

    protected $description = 'گیفت استیم فروشگاه را به جدیدترین‌ها و بازی‌های پین‌شده محدود می‌کند؛ سفارش زنده نمی‌زند';

    public function handle(FazerCardsSteamGiftShop $shop): int
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        try {
            $result = $shop->curate();
        } catch (ApiException $e) {
            $this->error(($e->errorCode ?: 'CURATE_FAILED').' — '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            'keep='.$result['keep']
            .' shown='.$result['shown']
            .' hidden='.$result['hidden']
            .' visible='.$result['visible']
        );

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Modules\Catalog\FazerCardsCatalogSync;
use Illuminate\Console\Command;

class LightSyncFazerCardsCommand extends Command
{
    protected $signature = 'charjino:sync-fazercards-light';

    protected $description = 'تازه‌سازی قیمت و موجودی topup/gift/key فعال و نرخ استیم';

    public function handle(FazerCardsCatalogSync $sync): int
    {
        try {
            $result = $sync->lightRefresh();
        } catch (ApiException $e) {
            $this->error(($e->errorCode ?: 'SYNC_FAILED').' — '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            'light status='.($result['status'] ?? '')
            .' products='.($result['products'] ?? 0)
            .' skus='.($result['skus'] ?? 0)
            .' hidden='.($result['hiddenProducts'] ?? 0)
            .' restored='.($result['restoredProducts'] ?? 0)
            .' failed='.($result['failed'] ?? 0)
        );

        if (($result['status'] ?? '') === 'failed') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

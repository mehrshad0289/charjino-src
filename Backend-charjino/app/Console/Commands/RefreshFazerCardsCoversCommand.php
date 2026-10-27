<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Modules\Catalog\FazerCardsCoverSync;
use Illuminate\Console\Command;

class RefreshFazerCardsCoversCommand extends Command
{
    protected $signature = 'charjino:refresh-covers';

    protected $description = 'کاور محصولات را از include_ui Fazer و CDN استیم به‌روز می‌کند (سفارش زنده نمی‌زند)';

    public function handle(FazerCardsCoverSync $sync): int
    {
        try {
            $result = $sync->refresh();
        } catch (ApiException $e) {
            $this->error(($e->errorCode ?: 'COVER_FAILED').' — '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('local='.$result['local'].' remote='.$result['remote']);

        return self::SUCCESS;
    }
}

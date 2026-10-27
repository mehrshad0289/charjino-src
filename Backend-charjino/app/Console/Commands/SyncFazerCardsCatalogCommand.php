<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Models\FazerCardsSyncCheckpoint;
use App\Modules\Catalog\FazerCardsCatalogSync;
use Illuminate\Console\Command;

class SyncFazerCardsCatalogCommand extends Command
{
    protected $signature = 'charjino:sync-fazercards {family? : topups|giftcards|gamekeys|telegram|steam-topup|steam-gifts|funpay|manual} {--mode=full : full|visible|weekly}';

    protected $description = 'همگام یک دسته FazerCards (یا همه اگر family خالی باشد)';

    public function handle(FazerCardsCatalogSync $sync): int
    {
        $family = $this->argument('family');
        $family = is_string($family) && $family !== '' ? $family : null;
        $mode = (string) $this->option('mode');
        if ($mode === 'weekly' && $family === 'steam-gifts' && ! FazerCardsSyncCheckpoint::shouldStartWeekly()) {
            $this->info('steam-gifts weekly skipped (not Thursday and no checkpoint)');

            return self::SUCCESS;
        }
        try {
            $result = $sync->sync($family, $mode);
        } catch (ApiException $e) {
            $this->error(($e->errorCode ?: 'SYNC_FAILED').' — '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            ($result['scope'] ?? $family ?? 'all')
            .' status='.($result['status'] ?? '')
            .' products='.($result['products'] ?? 0)
            .' skus='.($result['skus'] ?? 0)
            .' hiddenSkus='.($result['hiddenSkus'] ?? 0)
            .' hiddenProducts='.($result['hiddenProducts'] ?? 0)
            .' restoredProducts='.($result['restoredProducts'] ?? 0)
            .' message='.((string) ($result['message'] ?? ''))
        );

        $message = (string) ($result['message'] ?? '');
        if (str_contains($message, 'قفل همگام از دست رفت')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

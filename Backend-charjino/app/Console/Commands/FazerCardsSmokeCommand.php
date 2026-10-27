<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsClient;
use Illuminate\Console\Command;

class FazerCardsSmokeCommand extends Command
{
    protected $signature = 'charjino:fc-smoke {--player= : Player ID اختیاری برای validate پابجی}';

    protected $description = 'Smoke FazerCards: me + balance + topups (بدون سفارش پولی)';

    public function handle(FazerCardsClient $client, FazerCardsAdapter $adapter): int
    {
        if (! $client->configured()) {
            $this->error('FAZERCARDS_API_KEY خالی است. کلید را در env یا پنل ادمین بگذار.');

            return self::FAILURE;
        }

        if ($client->liveOrderEnabled()) {
            $this->warn('ALLOW_LIVE_ORDER=yes است؛ این smoke همچنان order نمی‌زند.');
        }

        try {
            $account = $adapter->account();
        } catch (ApiException $e) {
            $this->error(($e->errorCode ?: 'SUPPLIER_ERROR').' — '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('account ok wallet='.$account['wallet'].' USD plan='.$account['plan']);

        try {
            $count = count($client->allTopups());
            $this->info('topups='.$count);
        } catch (ApiException $e) {
            $this->error('topups failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $player = trim((string) $this->option('player'));
        if ($player !== '') {
            try {
                $check = $client->validatePlayerId('pubg_mobile_auto', ['player_id' => $player]);
                $valid = ($check['data']['valid'] ?? false) === true ? 'true' : 'false';
                $this->info('validate mapped='.(($check['data']['validateCategoryId'] ?? '').' valid='.$valid));
            } catch (ApiException $e) {
                $this->warn('validate: '.$e->errorCode.' '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

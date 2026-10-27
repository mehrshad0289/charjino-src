<?php

namespace App\Console\Commands;

use App\Modules\Ops\AdminAlertService;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use Illuminate\Console\Command;

class FazerWebhookHealthCommand extends Command
{
    protected $signature = 'charjino:fazer-webhook-health';

    protected $description = 'Check FazerCards webhook health';

    public function handle(FazerCardsAdapter $adapter, AdminAlertService $alerts): int
    {
        if (! $adapter->configured()) {
            return self::SUCCESS;
        }
        try {
            $data = app(\App\Modules\Supplier\FazerCards\FazerCardsClient::class)->fetch('account/webhook')['data'];
            $enabled = (bool) ($data['enabled'] ?? true);
            $fails = (int) ($data['consecutive_failures'] ?? 0);
            if (! $enabled || $fails > 5) {
                $alerts->open('WEBHOOK_UNHEALTHY', 'webhook:health', [
                    'enabled' => $enabled,
                    'fails' => $fails,
                ]);
            } else {
                $alerts->resolve('webhook:health');
            }
        } catch (\Throwable) {
            $alerts->open('WEBHOOK_UNHEALTHY', 'webhook:health', ['error' => true]);
        }
        cache()->put('scheduler:fazer-webhook-health', now()->toIso8601String(), 3600);

        return self::SUCCESS;
    }
}

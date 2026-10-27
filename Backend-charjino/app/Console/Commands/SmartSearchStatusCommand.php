<?php

namespace App\Console\Commands;

use App\Modules\SmartSearch\MeiliClientFactory;
use App\Modules\SmartSearch\SearchIndexWriteRegistry;
use Illuminate\Console\Command;

class SmartSearchStatusCommand extends Command
{
    protected $signature = 'smart-search:status';

    protected $description = 'وضعیت ایندکس‌های Smart Search را بدون mutate نشان می‌دهد';

    public function handle(): int
    {
        $this->info('driver='.config('smart-search.driver'));
        $this->info('prefix='.config('smart-search.index_prefix'));
        $this->info('writes='.( \App\Modules\SmartSearch\SmartSearchSyncService::writesAreEnabled() ? 'on' : 'off'));
        $active = SearchIndexWriteRegistry::active();
        try {
            $client = MeiliClientFactory::makeForWrite();
            foreach ($active as $name => $uid) {
                try {
                    $stats = $client->index($uid)->stats();
                    $this->line($name.' uid='.$uid.' docs='.((int) ($stats['numberOfDocuments'] ?? 0)));
                } catch (\Throwable $e) {
                    try {
                        $client->index($uid)->getSettings();
                        $this->line($name.' uid='.$uid.' ready (stats scoped-off)');
                    } catch (\Throwable) {
                        $this->line($name.' uid='.$uid.' missing');
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->warn('meili unreachable');
        }

        return self::SUCCESS;
    }
}

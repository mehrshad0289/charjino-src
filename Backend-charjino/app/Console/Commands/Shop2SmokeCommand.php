<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Modules\Supplier\Shop2\Shop2Adapter;
use App\Modules\Supplier\Shop2\Shop2Client;
use App\Modules\Supplier\Shop2\SkuDiscovery;
use App\Modules\Supplier\Shop2\SkuMap;
use Illuminate\Console\Command;

class Shop2SmokeCommand extends Command
{
    protected $signature = 'charjino:s2t-smoke {--player= : Player ID اختیاری برای validate} {--discover : کشف کاتالوگ PUBG/COD}';

    protected $description = 'Smoke تأمین‌کننده: account + discovery + requirements (بدون create پول‌دار)';

    public function handle(Shop2Client $client, Shop2Adapter $adapter, SkuDiscovery $discovery): int
    {
        if (! $client->configured()) {
            $this->error('S2T_KEY خالی است. کلید را فقط در env بگذار.');

            return self::FAILURE;
        }

        if ($client->liveOrderEnabled()) {
            $this->warn('ALLOW_LIVE_ORDER=yes است؛ این smoke همچنان create نمی‌زند.');
        }

        try {
            $account = $adapter->account();
        } catch (ApiException $e) {
            $this->error(($e->errorCode ?: 'SUPPLIER_ERROR').' — '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('account ok wallet='.$account['wallet'].' '.$account['currency']);

        $map = SkuMap::read();
        if ($this->option('discover') || ($map['skus'] ?? []) === []) {
            $this->info('discovering PUBG UC + COD CP…');
            $map = $discovery->discover();
        }

        $skus = is_array($map['skus'] ?? null) ? $map['skus'] : [];
        $picks = SkuMap::cheapestPicks($skus);
        if (count($picks) < 2) {
            $this->error('sku-map ناقص است؛ حداقل یک PUBG UC و یک COD CP لازم است.');

            return self::FAILURE;
        }

        $this->info('sku-map mvp_items='.implode(',', SkuMap::cheapestItemIds($skus)));

        foreach ($picks as $pick) {
            $sku = null;
            foreach ($skus as $row) {
                if (is_array($row) && (int) ($row['item_id'] ?? 0) === $pick['id']) {
                    $sku = $row;
                    break;
                }
            }
            $categoryId = (int) ($sku['category_id'] ?? $pick['category_id'] ?? 0);
            $code = (string) ($sku['internal_code'] ?? 'item-'.$pick['id']);
            $this->info('requirements category='.$categoryId.' '.$code);
            try {
                $fields = $client->requirements($categoryId);
                $this->line('  fields='.count($fields));
            } catch (ApiException $e) {
                $this->warn(($e->errorCode ?: 'SUPPLIER_ERROR').' — '.$e->getMessage());
            }
        }

        $player = trim((string) $this->option('player'));
        if ($player !== '') {
            $itemId = $picks[0]['id'] ?? 0;
            try {
                $check = $adapter->validatePlayer($itemId, $player);
                $this->info('validate ok playerName='.($check['playerName'] ?: '(empty)'));
            } catch (ApiException $e) {
                $this->error(($e->errorCode ?: 'SUPPLIER_ERROR').' — '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('smoke ok (no paid create)');

        return self::SUCCESS;
    }
}

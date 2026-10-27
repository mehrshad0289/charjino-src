<?php

namespace App\Console\Commands;

use App\Modules\Supplier\Shop2\SkuDiscovery;
use App\Modules\Supplier\Shop2\SkuMap;
use Illuminate\Console\Command;

class Shop2DiscoverCommand extends Command
{
    protected $signature = 'charjino:s2t-discover';

    protected $description = 'کشف item_id واقعی PUBG UC و COD CP و ذخیره sku-map (بدون secret)';

    public function handle(SkuDiscovery $discovery): int
    {
        $map = $discovery->discover();
        $count = count($map['skus'] ?? []);
        $this->info('sku-map skus='.$count.' path='.SkuMap::storagePath());

        return $count > 0 ? self::SUCCESS : self::FAILURE;
    }
}

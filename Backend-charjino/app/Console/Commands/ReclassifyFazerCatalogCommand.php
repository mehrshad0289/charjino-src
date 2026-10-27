<?php

namespace App\Console\Commands;

use App\Modules\Catalog\CatalogReclassifier;
use Illuminate\Console\Command;

class ReclassifyFazerCatalogCommand extends Command
{
    protected $signature = 'charjino:fazer-reclassify';

    protected $description = 'مستثناسازی ساختاریافته و ساخت purchase_schema برای کاتالوگ فعلی Fazer';

    public function handle(CatalogReclassifier $reclassifier): int
    {
        $result = $reclassifier->run();
        $this->info(
            'products='.$result['products']
            .' hidden='.$result['hidden']
            .' schemas='.$result['schemas']
            .' hiddenSteamPacks='.$result['hiddenSteamPacks']
        );

        return self::SUCCESS;
    }
}

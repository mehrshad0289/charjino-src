<?php

namespace App\Console\Commands;

use App\Modules\Catalog\FazerCardsCatalogSync;
use App\Modules\Supplier\FazerCards\FazerCardsListedShop;
use App\Modules\Supplier\FazerCards\FazerCardsRequestLane;
use Illuminate\Console\Command;

class CompareFazerListPagesCommand extends Command
{
    protected $signature = 'charjino:compare-fazer-list-pages';

    protected $description = 'مقایسه فقط‌خواندنی omit-limit با cursor+limit=500 برای topup/giftcard/gamekey';

    public function handle(FazerCardsCatalogSync $sync, FazerCardsListedShop $listed): int
    {
        $families = ['topups', 'giftcards', 'gamekeys'];
        $allOk = true;
        $report = FazerCardsRequestLane::using(FazerCardsRequestLane::LIGHT, function () use ($families, $sync, $listed, &$allOk): array {
            $out = [];
            foreach ($families as $family) {
                $spec = $sync->familySpecFor($family);
                if (! is_array($spec)) {
                    $out[$family] = ['error' => 'missing spec'];
                    $allOk = false;

                    continue;
                }
                $out[$family] = $listed->comparePagination($spec, 500);
                if (! ($out[$family]['identical'] ?? false)) {
                    $allOk = false;
                }
            }

            return $out;
        });
        $this->line(json_encode(['identical' => $allOk, 'families' => $report], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}

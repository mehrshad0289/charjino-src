<?php

namespace App\Console\Commands;

use App\Modules\Catalog\ProductDuplicateMerger;
use Illuminate\Console\Command;

class MergeDuplicateProductsCommand extends Command
{
    protected $signature = 'products:merge-duplicates
        {--apply : ادغام را واقعاً انجام بده}
        {--handles= : slugهایی که گروهشان باید ادغام شود، جدا با ویرگول}';

    protected $description = 'تکراری‌های هم‌کلید را به محصول اصلی منتقل و با ۳۰۱ غیرفعال می‌کند';

    public function handle(ProductDuplicateMerger $merger): int
    {
        $handles = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('handles'))
        )));
        if ($handles === []) {
            $this->error('handles لازم است');

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $results = $merger->mergeGroupsContaining($handles, $apply);
        if ($results === []) {
            $this->warn('گروهی برای این slugها پیدا نشد');

            return self::SUCCESS;
        }
        foreach ($results as $row) {
            $this->line(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }
}

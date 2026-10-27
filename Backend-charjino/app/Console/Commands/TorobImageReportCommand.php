<?php

namespace App\Console\Commands;

use App\Services\Torob\TorobProductQueryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class TorobImageReportCommand extends Command
{
    protected $signature = 'torob:image-report';

    protected $description = 'گزارش فقط‌خواندنی ابعاد تصویر محصولات listable ترب؛ دانلود نمی‌کند';

    public function handle(TorobProductQueryService $query): int
    {
        $listable = (clone $query->listableQuery())->count();
        $columns = Schema::getColumnListing('products');
        $dimensionColumns = array_values(array_intersect($columns, [
            'image_width',
            'image_height',
            'cover_width',
            'cover_height',
            'width',
            'height',
        ]));

        $this->info('listable='.$listable);
        if ($dimensionColumns === []) {
            $this->warn('ابعاد ذخیره نشده');
            $this->line('below_600x600=نامعلوم');

            return self::SUCCESS;
        }

        $width = in_array('image_width', $dimensionColumns, true) ? 'image_width' : $dimensionColumns[0];
        $height = in_array('image_height', $dimensionColumns, true)
            ? 'image_height'
            : ($dimensionColumns[1] ?? $dimensionColumns[0]);

        $below = (clone $query->listableQuery())
            ->whereNotNull($width)
            ->whereNotNull($height)
            ->where(function ($inner) use ($width, $height) {
                $inner->where($width, '<', 600)->orWhere($height, '<', 600);
            })
            ->count();
        $this->line('dimension_columns='.implode(',', $dimensionColumns));
        $this->line('below_600x600='.$below);

        return self::SUCCESS;
    }
}

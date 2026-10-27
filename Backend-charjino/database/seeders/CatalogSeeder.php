<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\CatalogGroup;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Catalog\CatalogSlug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->runningUnitTests()) {
            return;
        }

        $catalog = require database_path('data/catalog.php');

        $groups = [];
        $sort = 10;
        foreach (collect($catalog['products'])->pluck('category_name')->unique()->filter() as $title) {
            $slug = CatalogSlug::base((string) $title);
            $groups[$title] = CatalogGroup::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => (string) $title,
                    'title' => (string) $title,
                    'name_en' => match ((string) $title) {
                        'شارژ بازی' => 'Game top-up',
                        'گیفت‌کارت' => 'Gift cards',
                        default => null,
                    },
                    'visible' => true,
                    'sort_order' => $sort,
                ]
            );
            $sort += 10;
        }

        foreach ($catalog['products'] as $row) {
            $title = (string) ($row['category_name'] ?? '');
            if ($title !== '' && isset($groups[$title])) {
                $row['catalog_group_id'] = $groups[$title]->id;
            }

            $row['name_en'] = $row['name_en']
                ?? Str::headline(str_replace('-', ' ', (string) $row['handle']));

            Product::query()->updateOrCreate(
                ['handle' => $row['handle']],
                $row
            );
        }

        Product::query()
            ->whereIn('handle', collect($catalog['products'])->pluck('handle')->all())
            ->update(['is_popular' => false]);

        foreach ($catalog['banners'] as $row) {
            Banner::query()->updateOrCreate(
                ['title' => $row['title'], 'href' => $row['href']],
                $row
            );
        }

        FlashSaleItem::query()->delete();
        foreach ($catalog['flash_sale'] as $row) {
            FlashSaleItem::query()->create($row);
        }

        $products = Product::query()->get()->keyBy('handle');
        foreach ($catalog['skus'] as $row) {
            $product = $products->get($row['product']);
            if (! $product) {
                continue;
            }

            Sku::query()->updateOrCreate(
                ['product_id' => $product->id, 'code' => $row['code']],
                [
                    'name' => $row['name'],
                    'name_en' => $row['name_en'] ?? $row['name'],
                    'price' => $row['price'],
                    'status' => 'active',
                    'visible' => in_array($row['code'], ['uc-60', 'cp-160'], true),
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}

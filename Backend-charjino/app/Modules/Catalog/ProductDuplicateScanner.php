<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use App\Models\ProductSupplierIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProductDuplicateScanner
{
    /**
     * @return array{
     *     groups: list<array{key: string, kind: string, region: string, products: list<array<string, mixed>>}>,
     *     titles: list<array{title: string, products: list<array<string, mixed>>}>,
     *     metas: list<array{meta: string, products: list<array<string, mixed>>}>
     * }
     */
    public function report(): array
    {
        $products = $this->activeProducts();
        $orderCounts = $this->orderCounts($products->pluck('id')->all());
        $shop2Extra = $this->extraShop2Ids($products->pluck('id')->all());

        $byKey = [];
        foreach ($products as $product) {
            $parts = ProductNormalizedKey::partsFor($product);
            $identity = $parts['kind'].'|'.$parts['region'].'|'.$parts['key'];
            $byKey[$identity] ??= [
                'key' => $parts['key'],
                'kind' => $parts['kind'],
                'region' => $parts['region'],
                'products' => [],
            ];
            $byKey[$identity]['products'][] = $this->row($product, $orderCounts, $shop2Extra);
        }

        $groups = array_values(array_filter(
            $byKey,
            static fn (array $group): bool => count($group['products']) > 1
        ));
        usort($groups, static fn (array $a, array $b): int => strcmp($a['kind'].$a['key'], $b['kind'].$b['key']));

        return [
            'groups' => $groups,
            'titles' => $this->textGroups($products, $orderCounts, $shop2Extra, 'title'),
            'metas' => $this->textGroups($products, $orderCounts, $shop2Extra, 'seo_description'),
        ];
    }

    public function hasDuplicates(): bool
    {
        $report = $this->report();

        return $report['groups'] !== [] || $report['titles'] !== [] || $report['metas'] !== [];
    }

    /**
     * @return Collection<int, Product>
     */
    public function activeProducts(): Collection
    {
        return Product::query()
            ->where('status', 'active')
            ->where('visible', true)
            ->where(fn ($query) => $query->whereNull('excluded_reason')->orWhere('excluded_reason', ''))
            ->whereNull('substitute_product_id')
            ->orderBy('id')
            ->get([
                'id',
                'handle',
                'title',
                'seo_title',
                'seo_description',
                'created_at',
                'shop2_category_id',
                'fazercards_category_id',
                'fulfillment_type',
                'region',
                'status',
                'visible',
                'excluded_reason',
                'substitute_product_id',
            ]);
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, int>
     */
    public function orderCounts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $fromOrders = DB::table('orders')
            ->selectRaw('product_id, COUNT(*) as c')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->pluck('c', 'product_id')
            ->all();
        $fromItems = DB::table('order_items')
            ->selectRaw('product_id, COUNT(*) as c')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->pluck('c', 'product_id')
            ->all();
        $out = [];
        foreach ($productIds as $id) {
            $out[(int) $id] = (int) ($fromOrders[$id] ?? $fromOrders[(string) $id] ?? 0)
                + (int) ($fromItems[$id] ?? $fromItems[(string) $id] ?? 0);
        }

        return $out;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<int, int>  $orderCounts
     * @param  array<int, list<string>>  $shop2Extra
     * @return list<array{title?: string, meta?: string, products: list<array<string, mixed>>}>
     */
    private function textGroups(Collection $products, array $orderCounts, array $shop2Extra, string $field): array
    {
        $buckets = [];
        foreach ($products as $product) {
            $text = mb_strtolower(trim((string) $product->getAttribute($field)));
            if ($text === '') {
                continue;
            }
            $buckets[$text][] = $this->row($product, $orderCounts, $shop2Extra);
        }
        $groups = [];
        foreach ($buckets as $text => $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $groups[] = $field === 'title'
                ? ['title' => $text, 'products' => $rows]
                : ['meta' => $text, 'products' => $rows];
        }

        return $groups;
    }

    /**
     * @param  array<int, int>  $orderCounts
     * @param  array<int, list<string>>  $shop2Extra
     * @return array<string, mixed>
     */
    private function row(Product $product, array $orderCounts, array $shop2Extra): array
    {
        $shop2 = [];
        if ($product->shop2_category_id) {
            $shop2[] = (string) $product->shop2_category_id;
        }
        foreach ($shop2Extra[(int) $product->id] ?? [] as $id) {
            $shop2[] = $id;
        }

        return [
            'id' => (int) $product->id,
            'slug' => (string) $product->handle,
            'created_at' => optional($product->created_at)?->toDateTimeString(),
            'shop2_category_id' => $shop2 === [] ? null : implode(',', array_values(array_unique($shop2))),
            'fazercards_category_id' => $product->fazercards_category_id,
            'orders' => (int) ($orderCounts[(int) $product->id] ?? 0),
            'title' => (string) $product->title,
        ];
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, list<string>>
     */
    private function extraShop2Ids(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $out = [];
        ProductSupplierIdentity::query()
            ->where('supplier', ProductSupplierIdentity::SHOP2)
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->get(['product_id', 'remote_id'])
            ->each(function (ProductSupplierIdentity $row) use (&$out): void {
                $out[(int) $row->product_id][] = (string) $row->remote_id;
            });

        return $out;
    }
}

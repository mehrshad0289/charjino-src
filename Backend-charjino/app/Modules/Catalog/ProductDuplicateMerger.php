<?php

namespace App\Modules\Catalog;

use App\Models\BuyGuide;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\ProductSupplierIdentity;
use App\Models\UserFavorite;
use App\Modules\BuyGuide\StorefrontRevalidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProductDuplicateMerger
{
    public function __construct(
        private readonly ProductDuplicateScanner $scanner,
        private readonly ProductSupplierCatalog $suppliers,
        private readonly StorefrontRevalidator $revalidator,
        private readonly CatalogUrlLifecycle $lifecycle,
    ) {}

    /**
     * @param  list<string>  $handles
     * @return list<array<string, mixed>>
     */
    public function mergeGroupsContaining(array $handles, bool $apply): array
    {
        $wanted = array_fill_keys(array_map(
            static fn (string $handle): string => strtolower(trim($handle)),
            array_filter($handles)
        ), true);
        $report = $this->scanner->report();
        $done = [];
        foreach ($report['groups'] as $group) {
            $match = false;
            foreach ($group['products'] as $row) {
                if (isset($wanted[strtolower((string) $row['slug'])])) {
                    $match = true;
                    break;
                }
            }
            if (! $match) {
                continue;
            }
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $group['products']);
            $done[] = $this->mergeIds($ids, $apply);
        }

        return $done;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<string, mixed>
     */
    public function mergeIds(array $productIds, bool $apply): array
    {
        $products = Product::query()->whereIn('id', $productIds)->orderBy('id')->get();
        if ($products->count() < 2) {
            return ['ok' => false, 'message' => 'need two products'];
        }
        $counts = $this->scanner->orderCounts($products->pluck('id')->all());
        $sorted = $products->sort(function (Product $a, Product $b) use ($counts): int {
            $ordersA = ($counts[(int) $a->id] ?? 0) > 0 ? 1 : 0;
            $ordersB = ($counts[(int) $b->id] ?? 0) > 0 ? 1 : 0;
            if ($ordersA !== $ordersB) {
                return $ordersB <=> $ordersA;
            }
            $created = ($a->created_at?->timestamp ?? 0) <=> ($b->created_at?->timestamp ?? 0);
            if ($created !== 0) {
                return $created;
            }

            return $a->id <=> $b->id;
        })->values();
        $winner = $sorted->first();
        $losers = $sorted->slice(1)->values();
        $plan = [
            'ok' => true,
            'applied' => false,
            'winner' => [
                'id' => $winner->id,
                'slug' => $winner->handle,
                'created_at' => optional($winner->created_at)?->toDateTimeString(),
                'orders' => $counts[(int) $winner->id] ?? 0,
            ],
            'losers' => $losers->map(fn (Product $product) => [
                'id' => $product->id,
                'slug' => $product->handle,
                'created_at' => optional($product->created_at)?->toDateTimeString(),
                'orders' => $counts[(int) $product->id] ?? 0,
            ])->all(),
        ];
        if (! $apply) {
            return $plan;
        }

        $handles = [$winner->handle];
        DB::transaction(function () use ($winner, $losers, &$handles): void {
            foreach ($losers as $loser) {
                $this->repoint($winner, $loser);
                $handles[] = $loser->handle;
            }
        });
        StorefrontHomeCache::forget();
        $this->revalidator->pingCatalogHandles($handles);
        $plan['applied'] = true;

        return $plan;
    }

    private function repoint(Product $winner, Product $loser): void
    {
        if ($winner->id === $loser->id) {
            return;
        }
        DB::table('orders')->where('product_id', $loser->id)->update(['product_id' => $winner->id]);
        if (Schema::hasTable('order_items')) {
            DB::table('order_items')->where('product_id', $loser->id)->update(['product_id' => $winner->id]);
        }
        ProductComment::query()->where('product_id', $loser->id)->update(['product_id' => $winner->id]);
        $winnerFavs = UserFavorite::query()->where('product_id', $winner->id)->pluck('user_id');
        UserFavorite::query()
            ->where('product_id', $loser->id)
            ->whereIn('user_id', $winnerFavs)
            ->delete();
        UserFavorite::query()->where('product_id', $loser->id)->update(['product_id' => $winner->id]);

        if (Schema::hasTable('blog_post_product')) {
            $keepPosts = DB::table('blog_post_product')->where('product_id', $winner->id)->pluck('post_id');
            DB::table('blog_post_product')->where('product_id', $loser->id)->whereIn('post_id', $keepPosts)->delete();
            DB::table('blog_post_product')->where('product_id', $loser->id)->update(['product_id' => $winner->id]);
        }
        if (Schema::hasTable('product_search_tag')) {
            $keepTags = DB::table('product_search_tag')->where('product_id', $winner->id)->pluck('search_tag_id');
            DB::table('product_search_tag')->where('product_id', $loser->id)->whereIn('search_tag_id', $keepTags)->delete();
            DB::table('product_search_tag')->where('product_id', $loser->id)->update(['product_id' => $winner->id]);
        }
        if (class_exists(BuyGuide::class)) {
            BuyGuide::query()->where('product_id', $loser->id)->update(['product_id' => $winner->id]);
        }

        foreach ($this->suppliers->remoteIds($loser, ProductSupplierIdentity::FAZERCARDS) as $remote) {
            $this->suppliers->remember($winner, ProductSupplierIdentity::FAZERCARDS, $remote);
        }
        foreach ($this->suppliers->remoteIds($loser, ProductSupplierIdentity::SHOP2) as $remote) {
            $this->suppliers->remember($winner, ProductSupplierIdentity::SHOP2, $remote);
        }
        ProductSupplierIdentity::query()->where('product_id', $loser->id)->delete();

        if (! filled($winner->fazercards_category_id) && filled($loser->fazercards_category_id)) {
            $winner->fazercards_category_id = $loser->fazercards_category_id;
        }
        if (! $winner->shop2_category_id && $loser->shop2_category_id) {
            $winner->shop2_category_id = $loser->shop2_category_id;
        }
        $winner->save();

        $loser->fazercards_category_id = null;
        $loser->shop2_category_id = null;
        $loser->substitute_product_id = $winner->id;
        $loser->visible = false;
        $loser->status = 'hidden';
        $loser->indexable = false;
        $loser->save();

        $this->lifecycle->redirectDuplicate($loser, $winner);
        $winner->comments_count = ProductComment::query()
            ->where('product_id', $winner->id)
            ->whereNull('parent_id')
            ->count();
        $winner->save();
    }
}

<?php

namespace App\Modules\Catalog;

use App\Exceptions\ApiException;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Pricing\PricingService;
use App\Modules\Supplier\Shop2\Payload;
use App\Modules\Supplier\Shop2\Shop2Adapter;
use App\Modules\Supplier\Shop2\Shop2Client;
use App\Modules\Supplier\Shop2\SkuMap;
use Illuminate\Support\Str;

class CatalogSyncService
{
    public function __construct(
        private readonly Shop2Client $shop2,
        private readonly Shop2Adapter $adapter,
        private readonly PricingService $pricing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sync(): array
    {
        if (! $this->shop2->configured()) {
            throw new ApiException('کلید تأمین‌کننده تنظیم نشده است.', 503, 'SUPPLIER_NOT_CONFIGURED');
        }

        $started = now();
        CatalogSyncStatus::put([
            'status' => 'running',
            'startedAt' => $started->toIso8601String(),
        ]);

        return StorefrontHomeCache::defer(function () use ($started) {
            try {
                $account = CatalogSyncStatus::publicAccount($this->adapter->account());
            } catch (ApiException $e) {
                CatalogSyncStatus::fail($e->errorCode ?? 'SUPPLIER_UNAVAILABLE', $e->getMessage(), $e->status);
            }

            $groups = 0;
            $products = 0;
            $skus = 0;

            foreach ($this->shop2->bigCategories() as $big) {
                $bigId = Payload::id($big, 'id', 'big_category_id');
                if (! $bigId) {
                    continue;
                }
                $group = $this->upsertGroup($big, $bigId);
                $groups++;

                foreach ($this->shop2->categories($bigId) as $category) {
                    $categoryId = Payload::id($category, 'id', 'category_id');
                    if (! $categoryId) {
                        continue;
                    }
                    $product = $this->upsertProduct($group, $category, $categoryId, $bigId);
                    $products++;

                    foreach ($this->shop2->subcategories($categoryId) as $index => $item) {
                        $itemId = Payload::id($item, 'item_id', 'id', 'sub_category_id');
                        if (! $itemId) {
                            continue;
                        }
                        $this->upsertSku($product, $item, $itemId, $index);
                        $skus++;
                    }
                    usleep(200000);
                }
                usleep(150000);
            }

            $this->pricing->repriceAllSkus();
            $finished = now();
            $result = [
                'status' => 'completed',
                'startedAt' => $started->toIso8601String(),
                'finishedAt' => $finished->toIso8601String(),
                'durationMs' => (int) abs($started->diffInMilliseconds($finished)),
                'account' => $account,
                'groups' => $groups,
                'products' => $products,
                'skus' => $skus,
                'pricing' => $this->pricing->snapshot(),
            ];
            CatalogSyncStatus::put($result);

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $big
     */
    private function upsertGroup(array $big, int $bigId): CatalogGroup
    {
        $name = Payload::name($big);
        $existing = CatalogGroup::query()->where('shop2_big_category_id', $bigId)->first();

        return CatalogGroup::query()->updateOrCreate(
            ['shop2_big_category_id' => $bigId],
            [
                'name' => $name,
                'slug' => $existing?->slug ?: CatalogSlug::unique($name, $bigId),
                'title' => $existing?->title ?: $this->guessGroupTitle($name),
                'description' => $existing?->description ?: (string) ($big['description'] ?? ''),
                'image_url' => $existing?->image_url ?: $this->imageOf($big),
                'sort_order' => $existing?->sort_order ?? $bigId,
                'visible' => $existing?->visible ?? true,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $category
     */
    private function upsertProduct(CatalogGroup $group, array $category, int $categoryId, int $bigId): Product
    {
        $name = Payload::name($category);
        $fulfillment = (string) ($category['fulfillment_type'] ?? '');
        $existing = Product::query()->where('shop2_category_id', $categoryId)->first()
            ?? $this->supplierCatalog()->findByRemote(ProductSupplierCatalog::SHOP2, (string) $categoryId);
        $handle = $existing?->handle ?: $this->handleFor($name, $categoryId);
        if ($existing === null) {
            $existing = $this->supplierCatalog()->findLiveOrAnyByKey($handle, $fulfillment !== '' ? $fulfillment : null, null, true)
                ?? $this->supplierCatalog()->findLiveOrAnyByKey(Str::slug($name), $fulfillment !== '' ? $fulfillment : null, null);
        }
        if ($existing) {
            $existing = $this->supplierCatalog()->resolveCanonical($existing);
            $handle = (string) $existing->handle;
        }

        $product = Product::query()->updateOrCreate(
            $existing ? ['id' => $existing->id] : ['shop2_category_id' => $categoryId],
            [
                'catalog_group_id' => $group->id,
                'shop2_category_id' => $existing?->shop2_category_id ?: $categoryId,
                'shop2_big_category_id' => $bigId,
                'handle' => $handle,
                'code' => $existing?->code ?: 's2t-'.$categoryId,
                'title' => $existing && $existing->title !== $existing->name_en ? $existing->title : $name,
                'name_en' => filled($existing?->name_en) ? $existing->name_en : $name,
                'publisher' => $existing?->publisher ?: 'Shop2TopUp',
                'category_name' => $group->title,
                'subtitle' => $existing?->subtitle ?: (string) ($category['fulfillment_type'] ?? 'شارژ'),
                'description' => $existing?->description ?: (string) ($category['description'] ?? ''),
                'thumbnail' => $existing?->thumbnail ?: ($this->imageOf($category) ?: '/image/catalog/pubg-mobile.svg'),
                'image_url' => $existing?->image_url,
                'fulfillment_type' => $fulfillment !== '' ? $fulfillment : (string) $existing?->fulfillment_type,
                'visible' => filled($existing?->fazercards_category_id)
                    ? (bool) $existing->visible
                    : $this->mvpProductVisible($categoryId),
                'status' => $existing?->status ?: 'active',
                'sort_order' => $existing?->sort_order ?? $categoryId,
                'is_popular' => $existing?->is_popular ?? false,
            ]
        );
        $this->supplierCatalog()->remember($product, ProductSupplierCatalog::SHOP2, (string) $categoryId);

        return $product;
    }

    private function supplierCatalog(): ProductSupplierCatalog
    {
        return app(ProductSupplierCatalog::class);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function upsertSku(Product $product, array $item, int $itemId, int $index): Sku
    {
        $existing = Sku::query()->where('shop2_item_id', $itemId)->first();
        $usd = Payload::usdPrice($item);
        $mvp = SkuMap::mvpStorefrontItemIds();
        $storefront = $mvp !== [] && in_array($itemId, $mvp, true);

        return Sku::query()->updateOrCreate(
            ['shop2_item_id' => $itemId],
            [
                'product_id' => $product->id,
                'code' => $existing?->code ?: 'item-'.$itemId,
                'name' => $existing?->name ?: Payload::name($item),
                'name_en' => filled($existing?->name_en) ? $existing->name_en : Payload::name($item),
                'fulfillment_type' => (string) ($item['fulfillment_type'] ?? ''),
                'cost_usd' => $usd,
                'price' => ($existing && $existing->price_manual)
                    ? $existing->price
                    : $this->pricing->sellTomanFor($existing, $usd, $product),
                'status' => $existing?->status ?: 'active',
                'visible' => $storefront,
                'sort_order' => $existing?->sort_order ?? ($index + 1) * 10,
            ]
        );
    }

    private function mvpProductVisible(int $categoryId): bool
    {
        $mvp = SkuMap::mvpCategoryIds();

        return $mvp !== [] && in_array($categoryId, $mvp, true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function imageOf(array $row): ?string
    {
        foreach (['image', 'image_url', 'icon', 'logo', 'thumbnail'] as $key) {
            if (! empty($row[$key]) && is_string($row[$key])) {
                $url = trim($row[$key]);
                if ($url === '') {
                    continue;
                }
                if (str_starts_with($url, '//')) {
                    return 'https:'.$url;
                }
                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    return $url;
                }

                return 'https://shop2topup.com/'.ltrim($url, '/');
            }
        }

        return null;
    }

    private function handleFor(string $name, int $id): string
    {
        $slug = Str::slug($name);

        return ($slug !== '' ? $slug : 'item').'-'.$id;
    }

    private function guessGroupTitle(string $name): string
    {
        if (preg_match('/gift|voucher|card/i', $name)) {
            return 'گیفت‌کارت';
        }
        if (preg_match('/game|top.?up|mobile/i', $name)) {
            return 'شارژ بازی';
        }

        return $name;
    }
}

<?php

namespace App\Services\Torob;

use App\Models\Product;
use App\Models\Sku;
use Carbon\Carbon;

class TorobProductMapper
{
    public function __construct(
        private TorobFeedCatalogCopy $catalogCopy,
        private TorobSplitPolicy $policy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(Product $product): array
    {
        return $this->productRow($product);
    }

    /**
     * One feed row, or one row per listed SKU when split-by-sku policy allows it.
     *
     * @return list<array<string, mixed>>
     */
    public function toRows(Product $product): array
    {
        $decision = $this->policy->decide($product);
        if (! $decision->split) {
            return [$this->productRow($product)];
        }

        $rows = [];
        foreach ($decision->skus as $sku) {
            $rows[] = $this->skuRow($product, $sku);
        }

        return $rows !== [] ? $rows : [$this->productRow($product)];
    }

    /**
     * @return list<int>
     */
    public function splitProductIds(): array
    {
        return $this->policy->enabledProductIds();
    }

    public function splitsBySku(Product $product): bool
    {
        return $this->policy->splits($product);
    }

    public function pageUniqueForSku(Product $product, Sku $sku): string
    {
        $canonical = $this->canonicalSplitSku($product);
        if ($canonical !== null && (int) $canonical->id === (int) $sku->id) {
            return (string) $product->id;
        }

        return $product->id.'_'.$sku->id;
    }

    /**
     * @return array{product_id: int, sku_id: int|null}|null
     */
    public function parsePageUnique(string $unique): ?array
    {
        $value = trim($unique);
        if (preg_match('/^(\d+)_(\d+)$/', $value, $matches) === 1) {
            return [
                'product_id' => (int) $matches[1],
                'sku_id' => (int) $matches[2],
            ];
        }
        if (preg_match('/^(\d+)$/', $value, $matches) === 1) {
            return [
                'product_id' => (int) $matches[1],
                'sku_id' => null,
            ];
        }

        return null;
    }

    public function canonicalSplitSku(Product $product): ?Sku
    {
        return $this->policy->canonicalSku($product);
    }

    public function splitTitle(Product $product, Sku $sku): string
    {
        return $this->catalogCopy->torobSkuTitle($product, $sku);
    }

    public function pageUrl(string $handle, ?int $skuId = null): string
    {
        $base = rtrim((string) config('torob.shop_base_url'), '/');
        $url = $base.'/catalog/'.ltrim($handle, '/');
        if ($skuId !== null && $skuId > 0) {
            return $url.'?sku='.$skuId;
        }

        return $url;
    }

    /**
     * @return array{handle: string, sku_id: int|null}|null
     */
    public function parseProductUrl(string $url): ?array
    {
        $parts = parse_url($url);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        if ($path === '' && str_starts_with($url, '/')) {
            $path = $url;
        }
        if (! preg_match('#/catalog/([^/]+)/?$#', $path, $matches)) {
            return null;
        }

        $handle = rawurldecode($matches[1]);
        if ($handle === '') {
            return null;
        }

        $skuId = null;
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        if (isset($query['sku']) && (is_int($query['sku']) || ctype_digit((string) $query['sku']))) {
            $skuId = (int) $query['sku'];
            if ($skuId < 1) {
                $skuId = null;
            }
        }

        return ['handle' => $handle, 'sku_id' => $skuId];
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(Product $product): array
    {
        return $this->finishRow($product, [
            'page_unique' => (string) $product->id,
            'page_url' => $this->pageUrl((string) $product->handle),
            'title' => $this->limit($this->catalogCopy->torobTitle($product), 500) ?? '',
            ...$this->priceInfo($product),
            'date_updated' => $this->iso($this->updatedAt($product)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function skuRow(Product $product, Sku $sku): array
    {
        return $this->finishRow($product, [
            'page_unique' => $this->pageUniqueForSku($product, $sku),
            'page_url' => $this->pageUrl((string) $product->handle, (int) $sku->id),
            'title' => $this->limit($this->splitTitle($product, $sku), 500) ?? '',
            'product_group_id' => (string) $product->id,
            ...$this->skuPriceInfo($product, $sku),
            'date_updated' => $this->iso($this->updatedAt($product, $sku)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function finishRow(Product $product, array $overrides): array
    {
        $shortDesc = $this->shortDesc($product);
        $subtitle = $this->limit($this->catalogCopy->torobSubtitle($product), 500);
        $categoryName = $this->limit($this->catalogCopy->torobCategoryName($product), 200);

        $row = array_merge([
            'category_name' => $categoryName,
            'image_links' => $this->imageLinks($product),
            'spec' => $this->spec($product),
            'short_desc' => $shortDesc,
            'date_added' => $this->iso($product->created_at),
        ], $overrides);

        if ($subtitle !== null && $subtitle !== '') {
            $row['subtitle'] = $subtitle;
        }
        if (($row['old_price'] ?? null) === null) {
            unset($row['old_price']);
        }
        if ($row['category_name'] === null || $row['category_name'] === '') {
            unset($row['category_name']);
        }
        if ($row['short_desc'] === null || $row['short_desc'] === '') {
            unset($row['short_desc']);
        }

        return $row;
    }

    /**
     * @return array{current_price: int, old_price: int|null, availability: bool}
     */
    private function skuPriceInfo(Product $product, Sku $sku): array
    {
        if (! $product->isOrderable() || ! $sku->isListedOnProductPage()) {
            return [
                'current_price' => 0,
                'old_price' => null,
                'availability' => false,
            ];
        }
        $price = $sku->sellPrice();
        if ($price <= 0) {
            return [
                'current_price' => 0,
                'old_price' => null,
                'availability' => false,
            ];
        }

        return [
            'current_price' => $price,
            'old_price' => $sku->hasActiveSale() ? $sku->listPrice() : null,
            'availability' => true,
        ];
    }

    /**
     * @return array{current_price: int, old_price: int|null, availability: bool}
     */
    private function priceInfo(Product $product): array
    {
        if (! $product->isOrderable()) {
            return [
                'current_price' => 0,
                'old_price' => null,
                'availability' => false,
            ];
        }

        $chosen = $this->policy->cheapestPricedSku($product);
        if ($chosen === null) {
            return [
                'current_price' => 0,
                'old_price' => null,
                'availability' => false,
            ];
        }
        $chosenPrice = $chosen->sellPrice();
        if ($chosenPrice <= 0) {
            return [
                'current_price' => 0,
                'old_price' => null,
                'availability' => false,
            ];
        }

        return [
            'current_price' => $chosenPrice,
            'old_price' => $chosen->hasActiveSale() ? $chosen->listPrice() : null,
            'availability' => true,
        ];
    }

    /**
     * @return object|array<string, string>
     */
    private function spec(Product $product): object|array
    {
        $spec = [
            'condition' => $this->catalogCopy->productCondition(),
        ];
        $platform = $product->storefrontPlatform();
        if (is_string($platform) && $platform !== '') {
            $spec['platform'] = $this->limit($platform, 200) ?? $platform;
        }
        $region = $product->storefrontRegion();
        if (is_string($region) && $region !== '') {
            $spec['region'] = $this->limit($region, 200) ?? $region;
        }

        return $spec;
    }

    /**
     * @return list<string>
     */
    private function imageLinks(Product $product): array
    {
        $url = $this->absoluteImage($product->image_url);
        if ($url === null) {
            return [];
        }

        return [$url];
    }

    private function absoluteImage(mixed $path): ?string
    {
        $value = trim((string) $path);
        if ($value === '' || str_ends_with($value, '/')) {
            return null;
        }
        if (preg_match('#^https?://#i', $value) === 1) {
            return strlen($value) <= 1000 ? $value : null;
        }
        if (str_starts_with($value, '//')) {
            $value = 'https:'.$value;

            return strlen($value) <= 1000 ? $value : null;
        }

        $base = rtrim((string) config('torob.shop_base_url'), '/');
        $url = $base.'/'.ltrim($value, '/');

        return strlen($url) <= 1000 ? $url : null;
    }

    private function shortDesc(Product $product): ?string
    {
        $text = trim(strip_tags((string) $product->seo_description));
        if ($text === '') {
            return null;
        }

        return $this->limit($text, 500);
    }

    private function updatedAt(Product $product, ?Sku $sku = null): mixed
    {
        $dates = array_filter([
            $product->updated_at,
            $sku?->updated_at,
            $sku === null ? $product->getAttribute('skus_max_updated_at') : null,
        ]);
        if ($sku === null && $product->relationLoaded('skus')) {
            foreach ($product->skus as $related) {
                if ($related->updated_at !== null) {
                    $dates[] = $related->updated_at;
                }
            }
        }
        if ($dates === []) {
            return $product->created_at;
        }

        return collect($dates)->sortDesc()->first();
    }

    private function iso(mixed $date): string
    {
        $timezone = (string) config('torob.timezone', 'Asia/Tehran');

        return Carbon::parse($date)->timezone($timezone)->format('Y-m-d\TH:i:sP');
    }

    private function limit(?string $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}

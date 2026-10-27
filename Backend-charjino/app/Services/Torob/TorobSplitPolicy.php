<?php

namespace App\Services\Torob;

use App\Models\Product;
use App\Models\Sku;

class TorobSplitPolicy
{
    private bool $simulateAll = false;

    public function __construct(
        private TorobFeedCatalogCopy $catalogCopy,
        private TorobSplitCanonicalStore $canonicalStore,
        private TorobSplitRuntimeStore $runtimeStore,
    ) {}

    public function withSimulateAll(bool $on = true): self
    {
        $clone = clone $this;
        $clone->simulateAll = $on;

        return $clone;
    }

    /**
     * @return list<int>
     */
    public function enabledProductIds(): array
    {
        return $this->uniqueInts(array_merge(
            $this->intList(config('torob.split_by_sku.enabled_product_ids', [])),
            $this->intList(config('torob.split_by_sku_product_ids', [])),
            $this->runtimeStore->productIds(),
        ));
    }

    /**
     * @return list<string>
     */
    public function enabledGroupSlugs(): array
    {
        return $this->uniqueStrings(array_merge(
            $this->stringList(config('torob.split_by_sku.enabled_group_slugs', [])),
            $this->runtimeStore->groupSlugs(),
        ));
    }

    /**
     * @return list<int>
     */
    public function excludeProductIds(): array
    {
        return $this->intList(config('torob.split_by_sku.exclude_product_ids', []));
    }

    public function maxItemsPerProduct(): int
    {
        return max(1, (int) config('torob.split_by_sku.max_items_per_product', 30));
    }

    public function ruleVersion(): string
    {
        $version = trim((string) config('torob.split_by_sku.rule_version', 'v2'));

        return $version !== '' ? $version : 'v2';
    }

    public function ruleFingerprint(): string
    {
        $ids = $this->enabledProductIds();
        $slugs = $this->enabledGroupSlugs();
        $exclude = $this->excludeProductIds();

        return hash('sha256', json_encode([
            'version' => $this->ruleVersion(),
            'enabled_product_ids' => $ids,
            'enabled_group_slugs' => $slugs,
            'exclude_product_ids' => $exclude,
            'max_items_per_product' => $this->maxItemsPerProduct(),
            'canonical' => $this->canonicalStore->fingerprint(),
            'runtime' => $this->runtimeStore->fingerprint(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    public function isExcluded(Product $product): bool
    {
        return in_array((int) $product->id, $this->excludeProductIds(), true);
    }

    public function isEnabled(Product $product): bool
    {
        if ($this->simulateAll) {
            return true;
        }
        if (in_array((int) $product->id, $this->enabledProductIds(), true)) {
            return true;
        }
        $slug = trim((string) ($product->catalogGroup?->slug ?? ''));

        return $slug !== '' && in_array($slug, $this->enabledGroupSlugs(), true);
    }

    public function decide(Product $product, bool $treatAsEnabled = false): TorobSplitDecision
    {
        return $this->evaluate($product, $treatAsEnabled, $this->canonicalSku($product));
    }

    public function splits(Product $product): bool
    {
        return $this->decide($product)->split;
    }

    public function canonicalSku(Product $product): ?Sku
    {
        $storedId = $this->canonicalStore->get((int) $product->id);
        if ($storedId !== null) {
            foreach ($this->allSkus($product) as $sku) {
                if ((int) $sku->id === $storedId) {
                    return $sku;
                }
            }
        }

        $listed = $this->listedSkus($product);
        foreach ($listed as $sku) {
            if (strtolower(trim((string) $sku->fazercards_offer_id)) === '5_usd') {
                return $sku;
            }
        }

        return $this->cheapestPricedSku($product) ?? ($listed[0] ?? null);
    }

    public function cheapestPricedSku(Product $product): ?Sku
    {
        $chosen = null;
        $chosenPrice = null;
        foreach ($this->listedSkus($product) as $sku) {
            $price = $sku->sellPrice();
            if ($price <= 0) {
                continue;
            }
            if ($chosenPrice === null || $price < $chosenPrice) {
                $chosen = $sku;
                $chosenPrice = $price;
            }
        }

        return $chosen;
    }

    /**
     * @return list<Sku>
     */
    public function listedSkus(Product $product): array
    {
        $listed = [];
        foreach ($this->productPageSkus($product) as $sku) {
            if ($sku->isListedOnProductPage()) {
                $listed[] = $sku;
            }
        }

        return $listed;
    }

    public function isOrderableSku(Product $product, Sku $sku): bool
    {
        return $product->isOrderable()
            && $sku->isListedOnProductPage()
            && $sku->sellPrice() > 0;
    }

    /**
     * @return list<int>
     */
    private function uniqueInts(array $ids): array
    {
        $unique = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $unique[$id] = $id;
            }
        }

        return array_values($unique);
    }

    /**
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $unique[$value] = $value;
            }
        }

        return array_values($unique);
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return $this->uniqueInts($values);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return $this->uniqueStrings($values);
    }

    private function evaluate(Product $product, bool $treatAsEnabled, ?Sku $canonical): TorobSplitDecision
    {
        if ($this->isExcluded($product)) {
            return TorobSplitDecision::skip(TorobSplitDecision::REASON_EXCLUDED, $canonical);
        }

        if (! $treatAsEnabled && ! $this->isEnabled($product)) {
            return TorobSplitDecision::skip(TorobSplitDecision::REASON_NOT_ENABLED, $canonical);
        }

        $listed = $this->listedSkus($product);
        $orderableCount = 0;
        foreach ($listed as $sku) {
            if ($this->isOrderableSku($product, $sku)) {
                $orderableCount++;
            }
        }

        if ($orderableCount === 0) {
            return TorobSplitDecision::skip(TorobSplitDecision::REASON_NO_ORDERABLE_SKU, $canonical);
        }

        $emit = $this->emitSkus($listed, $canonical);
        $keepUnavailableCanonical = $orderableCount < 2
            && $canonical !== null
            && count($emit) >= 2;
        if ($orderableCount < 2 && ! $keepUnavailableCanonical) {
            return TorobSplitDecision::skip(TorobSplitDecision::REASON_SINGLE_SKU, $canonical);
        }

        if (count($emit) > $this->maxItemsPerProduct()) {
            return TorobSplitDecision::skip(TorobSplitDecision::REASON_OVER_MAX, $canonical);
        }

        if (! $this->catalogCopy->usesLegacySplitTitle($product)) {
            foreach ($emit as $sku) {
                if ($this->catalogCopy->persianSkuTitle($product, $sku) === null) {
                    return TorobSplitDecision::skip(TorobSplitDecision::REASON_UNKNOWN_MAPPING, $canonical);
                }
            }
        }

        $baseKey = $this->catalogCopy->normalizeTitleKey($this->catalogCopy->torobTitle($product));
        $seen = [];
        foreach ($emit as $sku) {
            $title = $this->catalogCopy->torobSkuTitle($product, $sku);
            $key = $this->catalogCopy->normalizeTitleKey($title);
            if ($key === '' || $key === $baseKey) {
                return TorobSplitDecision::skip(TorobSplitDecision::REASON_EMPTY_LABELS, $canonical);
            }
            if (isset($seen[$key])) {
                return TorobSplitDecision::skip(TorobSplitDecision::REASON_AMBIGUOUS_TITLES, $canonical);
            }
            $seen[$key] = true;
        }

        if ($canonical === null) {
            return TorobSplitDecision::skip(TorobSplitDecision::REASON_SINGLE_SKU, null);
        }

        return TorobSplitDecision::yes($emit, $canonical);
    }

    /**
     * @param  list<Sku>  $listed
     * @return list<Sku>
     */
    private function emitSkus(array $listed, ?Sku $canonical): array
    {
        $emit = [];
        $seen = [];
        foreach ($listed as $sku) {
            $include = $sku->sellPrice() > 0
                || ($canonical !== null && (int) $sku->id === (int) $canonical->id);
            if (! $include) {
                continue;
            }
            $emit[] = $sku;
            $seen[(int) $sku->id] = true;
        }
        if ($canonical !== null && ! isset($seen[(int) $canonical->id])) {
            $emit[] = $canonical;
        }

        return $emit;
    }

    /**
     * @return list<Sku>
     */
    private function allSkus(Product $product): array
    {
        if ($product->relationLoaded('skus')) {
            return $product->skus->all();
        }

        return $product->skus()->get()->all();
    }

    /**
     * @return iterable<int, Sku>
     */
    private function productPageSkus(Product $product): iterable
    {
        if ($product->relationLoaded('skus')) {
            return $product->skus;
        }

        return $product->skus()->forProductPage()->get();
    }
}

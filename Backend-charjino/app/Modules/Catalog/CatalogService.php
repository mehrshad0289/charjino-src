<?php

namespace App\Modules\Catalog;

use App\Exceptions\ApiException;
use App\Http\Resources\HomeProductResource;
use App\Models\Banner;
use App\Models\CatalogGroup;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\BuyGuide\BuyGuideResolver;
use App\Modules\Pricing\PricingService;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\SteamTopupAmount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogService
{
    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        return StorefrontHomeCache::remember(fn (): array => $this->buildHomePayload());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHomePayload(): array
    {
        $grouped = [];
        foreach (HomeSections::storefrontPlan() as $section) {
            /** @var CatalogGroup $group */
            $group = $section['group'];
            $items = $this->homeProducts($group, $section['productIds'], $section['limit']);
            if ($items->isEmpty()) {
                continue;
            }
            $grouped[] = [
                'id' => $group->id,
                'slug' => $group->slug,
                'name' => $group->storefrontTitle(),
            ];
        }

        if ($grouped === [] && ! CatalogGroup::query()->where('visible', true)->exists()) {
            $fallback = $this->storefrontProducts()->limit(HomeSections::defaultLimit() * 2)->get();
            $grouped = $fallback
                ->groupBy(fn (Product $product) => $product->catalogGroup?->storefrontTitle() ?: $product->category_name)
                ->map(fn ($items, $name) => [
                    'id' => null,
                    'slug' => CatalogSlug::base((string) $name),
                    'name' => StorefrontCopy::groupTitle((string) $name),
                ])
                ->values()
                ->all();
        }

        $popular = $this->storefrontProducts()
            ->where('is_popular', true)
            ->limit(8)
            ->get();

        [$flashSale, $flashSaleEndsAt] = $this->storefrontFlashSale();
        $copy = HomeCopy::get();
        if ($flashSaleEndsAt !== null) {
            $copy['flashSaleEndsAt'] = $flashSaleEndsAt;
        }

        return $copy + [
            'introHtml' => HomeHtml::sanitize($copy['introHtml'] ?? null),
            'banners' => Banner::query()
                ->where('visible', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Banner $banner) => [
                    'id' => $banner->id,
                    'src' => $banner->storefrontSrc(),
                    'mediaType' => $banner->resolvedMediaType(),
                    'title' => $banner->title,
                    'href' => $banner->href,
                ])
                ->values()
                ->all(),
            'flashSale' => collect($flashSale)->map(fn (array $row) => HomeProductResource::fromFlash($row))->values()->all(),
            'groups' => $grouped,
            'popular' => $popular->map(fn (Product $product) => (new HomeProductResource($product))->resolve())->values()->all(),
        ];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    private function storefrontFlashSale(): array
    {
        $cmsItems = FlashSaleItem::query()
            ->with(['sku.product'])
            ->where('visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (FlashSaleItem $item) => $item->isStorefrontWorthy());

        $listedSkuIds = $cmsItems->pluck('sku_id')->filter()->map(fn ($id) => (int) $id)->all();
        $hiddenSkuIds = FlashSaleItem::query()
            ->where('visible', false)
            ->whereNotNull('sku_id')
            ->pluck('sku_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $exclude = array_values(array_unique([...$listedSkuIds, ...$hiddenSkuIds]));

        $orphans = Sku::query()
            ->with('product')
            ->where('visible', true)
            ->where('status', 'active')
            ->whereNotNull('sale_price')
            ->whereNotNull('sale_ends_at')
            ->where('sale_ends_at', '>=', now())
            ->whereColumn('sale_price', '<', 'price')
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('id', $exclude))
            ->whereHas('product', fn ($query) => $query->where('visible', true)->where('status', 'active'))
            ->orderBy('id')
            ->limit(24)
            ->get()
            ->filter(fn (Sku $sku) => FlashSaleSync::storefrontWorthy($sku));

        $items = $cmsItems
            ->map(fn (FlashSaleItem $item) => $item->toApi())
            ->concat($orphans->map(fn (Sku $sku) => FlashSaleSync::toApiFromSku($sku)))
            ->values()
            ->all();

        return [$items, $this->flashSaleCountdownEndsAt($cmsItems, $orphans)];
    }

    /**
     * @param  Collection<int, FlashSaleItem>  $cmsItems
     * @param  Collection<int, Sku>  $orphans
     */
    private function flashSaleCountdownEndsAt(Collection $cmsItems, Collection $orphans): ?string
    {
        $configured = HomeCopy::get()['flashSaleEndsAt'] ?? null;
        if (is_string($configured) && $configured !== '') {
            try {
                if (Carbon::parse($configured)->gte(now())) {
                    return $configured;
                }
            } catch (\Throwable) {
            }
        }

        $latest = null;
        foreach ($cmsItems as $item) {
            $ends = $item->sku?->sale_ends_at;
            if ($ends !== null && ($latest === null || $ends->gt($latest))) {
                $latest = $ends;
            }
        }
        foreach ($orphans as $sku) {
            $ends = $sku->sale_ends_at;
            if ($ends !== null && ($latest === null || $ends->gt($latest))) {
                $latest = $ends;
            }
        }

        return $latest?->toIso8601String();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return Cache::remember(
            'storefront:categories:v2:'.StorefrontHomeCache::facetVersion(),
            StorefrontHomeCache::TTL_SECONDS,
            fn (): array => $this->buildCategoriesNav(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCategoriesNav(): array
    {
        $items = $this->visibleGroups()
            ->withCount(['products as products_count' => fn ($query) => $query->where('status', 'active')->where('visible', true)])
            ->get()
            ->map
            ->toNavApi()
            ->values()
            ->all();
        $existing = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['slug'])) {
                $existing[(string) $item['slug']] = true;
            }
        }
        foreach (BrandHub::slugs() as $slug) {
            if (isset($existing[$slug])) {
                continue;
            }
            $items[] = BrandHub::toNavApi($slug, 0);
        }

        return $items;
    }

    /**
     * @return array{category: array<string, mixed>, products: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int, totalPages: int}}
     */
    public function category(string $slug, ?int $page = null, ?int $perPage = null): array
    {
        [$page, $perPage] = $this->normalizePage($page, $perPage);
        $key = 'storefront:category:v1:'.StorefrontHomeCache::facetVersion().':'.$slug.':'.$page.':'.$perPage;

        return Cache::remember($key, StorefrontHomeCache::TTL_SECONDS, fn (): array => $this->buildCategoryPage($slug, $page, $perPage));
    }

    /**
     * @return array{category: array<string, mixed>, products: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int, totalPages: int}}
     */
    private function buildCategoryPage(string $slug, int $page, int $perPage): array
    {
        $group = $this->visibleGroups()
            ->withCount(['products as products_count' => fn ($query) => $query->where('status', 'active')->where('visible', true)])
            ->where('slug', $slug)
            ->first();

        if (! $group && ! BrandHub::is($slug)) {
            throw new ApiException('دسته پیدا نشد.', 404);
        }

        $query = $this->storefrontProducts($group);
        if (! $group && BrandHub::is($slug)) {
            BrandHub::constrain($query, $slug);
        }
        $total = (clone $query)->count();
        $items = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Product $product) => $product->toCardApi())
            ->values()
            ->all();

        return [
            'category' => $group ? $group->toNavApi() : BrandHub::toNavApi($slug, $total),
            'products' => $items,
            'meta' => $this->meta($page, $perPage, $total),
        ];
    }

    /**
     * @return list<array<string, mixed>>|array{items: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int, totalPages: int}}
     */
    public function products(
        ?string $q = null,
        bool $popularOnly = false,
        ?string $categorySlug = null,
        ?int $page = null,
        ?int $perPage = null,
        ?int $minPrice = null,
        ?int $maxPrice = null,
        ?string $platform = null,
        ?string $region = null,
        ?string $sort = null,
        ?string $excludeCategorySlug = null,
        ?bool $indexable = null,
    ): array {
        $categorySlug = is_string($categorySlug) ? trim($categorySlug) : '';
        $group = $categorySlug !== ''
            ? $this->visibleGroups()->where('slug', $categorySlug)->first()
            : null;
        $query = $this->storefrontProducts($group, true);
        $this->excludeStorefrontCategory($query, $excludeCategorySlug);

        if ($popularOnly) {
            $query->where('is_popular', true);
        }
        if ($indexable !== null) {
            $query->where('indexable', $indexable);
        }

        $needle = SearchQuery::normalize((string) $q);
        if ($needle !== '') {
            $handles = app(CatalogTextIdResolver::class)->handles($needle, $group?->slug);
            if (is_array($handles)) {
                if ($handles === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('handle', $handles);
                }
            } else {
                CatalogSearch::apply($query, $needle, $group?->slug === 'gift-card');
            }
        }

        $this->applyPriceRange($query, $minPrice, $maxPrice);

        $platform = CatalogAttr::publicPlatform($platform) ?? '';
        $region = CatalogAttr::publicRegion($region) ?? '';

        if ($categorySlug !== '' && ! $group) {
            if (BrandHub::is($categorySlug)) {
                BrandHub::constrain($query, $categorySlug);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $paginate = $page !== null || $perPage !== null || $categorySlug !== '';
        if ($paginate) {
            [$page, $perPage] = $this->normalizePage($page, $perPage);
            $cacheable = $needle === '' && $minPrice === null && $maxPrice === null && $indexable === null && ! $popularOnly;
            $build = fn (): array => $this->paginateStorefrontProducts(
                $query,
                $platform,
                $region,
                $categorySlug,
                $needle,
                $sort,
                $group,
                $page,
                $perPage,
            );
            if ($cacheable) {
                $key = 'storefront:products:v3:'.StorefrontHomeCache::facetVersion().':'.md5(implode(':', [
                    $categorySlug,
                    (string) ($excludeCategorySlug ?? ''),
                    $platform,
                    $region,
                    (string) ($sort ?? ''),
                    (string) $page,
                    (string) $perPage,
                ]));

                return Cache::remember($key, StorefrontHomeCache::TTL_SECONDS, $build);
            }

            return $build();
        }

        $this->applyPlatform($query, $platform);
        $this->applyRegion($query, $region);
        $this->applyBrowseSort($query, $sort, $group, $needle);

        if ($needle !== '') {
            $query->limit(20);
        } elseif ($popularOnly) {
            $query->limit(8);
        } else {
            $query->limit(40);
        }

        return $query
            ->get()
            ->map(fn (Product $product) => $product->toCardApi())
            ->values()
            ->all();
    }

    /**
     * @return array{currency: string, amount: string, usd: string, toman: int}
     */
    public function steamTopupQuote(string $currency, string $amount): array
    {
        $adapter = app(FazerCardsAdapter::class);
        $payload = [];
        try {
            if ($adapter->configured()) {
                $payload = $adapter->steamRatesPayload();
            }
        } catch (\Throwable) {
        }
        $priced = SteamTopupAmount::validate($currency, $amount, $payload);

        return [
            'currency' => $priced['currency'],
            'amount' => $priced['amount'],
            'usd' => $priced['usd'],
            'toman' => app(PricingService::class)->sellToman($priced['usd']),
        ];
    }

    /**
     * @return array{status: string, target: ?string}
     */
    public function resolvePath(string $path): array
    {
        $normalized = trim($path);
        $key = 'storefront:resolve:v1:'.StorefrontHomeCache::facetVersion().':'.md5(mb_strtolower($normalized));

        return Cache::remember(
            $key,
            StorefrontHomeCache::TTL_SECONDS,
            fn (): array => app(CatalogUrlLifecycle::class)->resolve($normalized),
        );
    }

    /**
     * @return array{minPrice: int|null, skus: list<array{id: int, code: string, price: int, originalPrice: int|null, saleEndsAt: string|null}>}
     */
    public function productPrices(string $handle): array
    {
        $this->throwSlugRedirect($handle);
        $found = Product::query()
            ->with(['skus' => fn ($query) => $query->where('status', 'active')->where('visible', true)->orderBy('sort_order')->orderBy('id')])
            ->where('status', 'active')
            ->notStorefrontExcluded()
            ->where(function ($query) use ($handle) {
                $normalized = mb_strtolower($handle);
                $query->whereRaw('LOWER(handle) = ?', [$normalized])
                    ->orWhereRaw('LOWER(code) = ?', [$normalized]);
            })
            ->first();

        $lifecycle = app(CatalogUrlLifecycle::class);
        if (! $found || $found->isStorefrontExcluded()) {
            throw new ApiException('محصول پیدا نشد.', 404);
        }
        if (! $found->storefrontAvailable() && ! $lifecycle->isTemporaryUnavailable($found)) {
            throw new ApiException('محصول پیدا نشد.', 404);
        }

        return [
            'minPrice' => $found->storefrontMinPrice(),
            'skus' => $found->skus
                ->map(function (Sku $sku) {
                    $onSale = $sku->hasActiveSale();

                    return [
                        'id' => $sku->id,
                        'code' => $sku->code,
                        'price' => $sku->sellPrice(),
                        'originalPrice' => $onSale ? $sku->listPrice() : null,
                        'saleEndsAt' => $onSale ? $sku->sale_ends_at?->toIso8601String() : null,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    public function product(string $handle): array
    {
        $key = 'storefront:product:v1:'.StorefrontHomeCache::facetVersion().':'.mb_strtolower($handle);

        return $this->withLivePurchaseValidation(
            Cache::remember($key, StorefrontHomeCache::TTL_SECONDS, fn (): array => $this->loadStorefrontProduct($handle))
        );
    }

    /**
     * Validation mode follows the live Fazer validate-id catalog (900s), not the 1h product cache.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withLivePurchaseValidation(array $payload): array
    {
        $schema = is_array($payload['purchaseSchema'] ?? null) ? $payload['purchaseSchema'] : null;
        if ($schema === null) {
            return $payload;
        }
        $validation = is_array($schema['validation'] ?? null) ? $schema['validation'] : [];
        $family = FazerCardsIds::familyOf($payload['fulfillmentType'] ?? null, $validation['validateCategoryId'] ?? null);
        $categoryId = trim((string) ($validation['validateCategoryId'] ?? ''));
        $schema['validation'] = [
            'mode' => PurchaseSchema::validationMode($family, ['validateCategoryId' => $categoryId]),
            'validateCategoryId' => $family === FazerCardsIds::TOPUP
                ? ($categoryId !== '' ? $categoryId : null)
                : ($validation['validateCategoryId'] ?? null),
        ];
        $payload['purchaseSchema'] = $schema;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadStorefrontProduct(string $handle): array
    {
        $this->throwSlugRedirect($handle);
        $found = Product::query()
            ->with(['catalogGroup', 'searchTags'])
            ->with(['skus' => fn ($query) => $query->forProductPage()])
            ->where('status', 'active')
            ->notStorefrontExcluded()
            ->where(function ($query) use ($handle) {
                $normalized = mb_strtolower($handle);
                $query->whereRaw('LOWER(handle) = ?', [$normalized])
                    ->orWhereRaw('LOWER(code) = ?', [$normalized]);
            })
            ->first();

        $lifecycle = app(CatalogUrlLifecycle::class);
        if (! $found || $found->isStorefrontExcluded()) {
            throw new ApiException('محصول پیدا نشد.', 404);
        }
        if (! $found->storefrontAvailable() && ! $lifecycle->isTemporaryUnavailable($found)) {
            throw new ApiException('محصول پیدا نشد.', 404);
        }

        $group = $found->catalogGroup;
        if ($group === null && filled($found->category_name)) {
            $group = CatalogGroup::query()
                ->where('slug', CatalogSlug::base((string) $found->category_name))
                ->first();
        }
        if ($group && ! $group->visible) {
            throw new ApiException('محصول پیدا نشد.', 404);
        }

        $schema = is_array($found->purchase_schema) ? $found->purchase_schema : [];
        $schemaFields = is_array($schema['fields'] ?? null) ? $schema['fields'] : null;
        if (is_array($schemaFields)) {
            $inputFields = array_values(array_map(function (array $field): array {
                return [
                    'name' => (string) ($field['key'] ?? $field['name'] ?? ''),
                    'label' => (string) ($field['labelFa'] ?? $field['label'] ?? ''),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'required' => (bool) ($field['required'] ?? true),
                    'options' => $field['options'] ?? null,
                    'help' => $field['help'] ?? null,
                    'pattern' => $field['pattern'] ?? null,
                ];
            }, $schemaFields));
        } else {
            $inputFields = BuyerInputFields::sanitize(is_array($found->inputs) ? $found->inputs : []);
            $kind = BuyerIdentity::kind($found);
            if ($inputFields === [] && $found->needsBuyerIdentity()) {
                $inputFields = [BuyerInputFields::defaultIdField($kind)];
            } elseif ($inputFields !== []) {
                $inputFields = BuyerInputFields::applyKind($inputFields, $kind);
            }
        }

        $buyGuide = app(BuyGuideResolver::class)->forProduct($found);
        $packs = SkuPackFilters::decorate($found, $found->skus);
        $similar = [];
        $alternate = null;
        if (! $found->isOrderable()) {
            $live = $lifecycle->liveAlternate($found);
            $alternate = $live?->toCardApi();
            if ($group) {
                $similar = $this->storefrontProducts($group)
                    ->reorder()
                    ->orderByDesc('id')
                    ->where('id', '!=', $found->id)
                    ->limit(8)
                    ->get()
                    ->map(fn (Product $item) => $item->toCardApi())
                    ->values()
                    ->all();
            }
            if (is_array($alternate)) {
                $similar = collect([$alternate])
                    ->concat($similar)
                    ->unique('id')
                    ->take(8)
                    ->values()
                    ->all();
            }
        }

        return $found->toApi() + [
            'skus' => $packs['skus'],
            'packFilters' => $packs['filters'],
            'requirements' => $found->requirements ?? [],
            'inputFields' => $inputFields,
            'buyGuide' => $buyGuide,
            'similar' => $similar,
            'alternate' => $alternate,
            'codm' => CodmCatalog::payload($found),
            'category' => $group ? [
                'id' => $group->id,
                'slug' => $group->slug,
                'title' => $group->storefrontTitle(),
                ...$group->purchaseGuidePayload(),
            ] : null,
        ];
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     */
    private function homeProducts(CatalogGroup $group, array $productIds, int $limit): Collection
    {
        $limit = max(1, min(HomeSections::LIMIT_MAX, $limit));
        if ($productIds !== []) {
            $wanted = array_slice($productIds, 0, $limit);
            $found = $this->storefrontProducts($group)
                ->whereIn('id', $wanted)
                ->get()
                ->keyBy('id');
            $picked = collect($wanted)
                ->map(fn (int $id) => $found->get($id))
                ->filter()
                ->values();
            if ($picked->isNotEmpty()) {
                return $picked;
            }
        }

        return $this->storefrontProducts($group)->limit($limit)->get();
    }

    private function cachedFacetMeta(Builder $query, string $platform, string $region, string $categorySlug, string $needle): array
    {
        if ($categorySlug === '' || $needle !== '') {
            return $this->facetMeta($query, $platform, $region, $categorySlug);
        }

        $key = 'storefront:facets:'.StorefrontHomeCache::facetVersion().':'.$categorySlug.':'.mb_strtolower($platform).':'.mb_strtolower($region);

        return Cache::remember($key, StorefrontHomeCache::TTL_SECONDS, fn () => $this->facetMeta(clone $query, $platform, $region, $categorySlug));
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyBrowseSort(Builder $query, ?string $sort, ?CatalogGroup $group, string $needle): void
    {
        $sort = is_string($sort) ? trim($sort) : '';
        if ($sort === 'id') {
            $query->reorder()->orderBy('id');

            return;
        }
        if ($sort === 'new') {
            $query->reorder()->orderByDesc('id');

            return;
        }
        if ($sort === 'price') {
            $query->reorder()->orderByRaw($this->minPriceOrderSql().' ASC')->orderByDesc('id');

            return;
        }
        if ($needle !== '' && $group?->slug === 'gift-card') {
            $like = '%'.mb_strtolower($needle).'%';
            $query->reorder()
                ->orderByRaw('CASE WHEN LOWER(title) LIKE ? THEN 0 WHEN LOWER(COALESCE(platform, \'\')) LIKE ? THEN 1 ELSE 2 END', [$like, $like])
                ->orderByRaw($this->listedRankOrderSql())
                ->orderByDesc('id');
        }
    }

    private function minPriceOrderSql(): string
    {
        $now = now()->toDateTimeString();
        $effective = "CASE WHEN skus.sale_price > 0 AND skus.sale_price < skus.price AND skus.sale_ends_at IS NOT NULL AND skus.sale_ends_at >= '{$now}' THEN skus.sale_price ELSE skus.price END";

        return "(SELECT MIN({$effective}) FROM skus WHERE skus.product_id = products.id AND skus.status = 'active' AND skus.visible = 1)";
    }

    /**
     * @param  Builder<Product>  $query
     * @return array{platforms: list<string>, regions: list<string>}
     */
    private function facetMeta(Builder $query, string $platform, string $region, string $categorySlug = ''): array
    {
        $forPlatforms = clone $query;
        $this->applyRegion($forPlatforms, $region);
        $forRegions = clone $query;
        $this->applyPlatform($forRegions, $platform);

        return [
            'platforms' => $this->facetPlatforms($forPlatforms, $categorySlug),
            'regions' => $this->facetRegions($forRegions, $categorySlug),
        ];
    }

    /**
     * @param  Builder<Product>  $query
     * @return list<string>
     */
    private function facetPlatforms(Builder $query, string $categorySlug = ''): array
    {
        $values = (clone $query)
            ->reorder()
            ->select('products.platform')
            ->whereNotNull('products.platform')
            ->where('products.platform', '!=', '')
            ->distinct()
            ->pluck('products.platform')
            ->all();

        if ($categorySlug === GameKeyPlatform::GROUP_SLUG) {
            return GameKeyPlatform::mergeStorefrontFacets($values);
        }

        return GameKeyPlatform::mergeFacets($values);
    }

    /**
     * @param  Builder<Product>  $query
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function paginateStorefrontProducts(
        Builder $query,
        string $platform,
        string $region,
        string $categorySlug,
        string $needle,
        ?string $sort,
        ?CatalogGroup $group,
        int $page,
        int $perPage,
    ): array {
        $facets = $this->cachedFacetMeta($query, $platform, $region, $categorySlug, $needle);
        $this->applyPlatform($query, $platform);
        $this->applyRegion($query, $region);
        $this->applyBrowseSort($query, $sort, $group, $needle);
        $total = (clone $query)->count();
        $items = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Product $product) => $product->toCardApi())
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => $this->meta($page, $perPage, $total) + $facets,
        ];
    }

    /**
     * @param  Builder<Product>  $query
     * @return list<string>
     */
    private function facetRegions(Builder $query, string $categorySlug = ''): array
    {
        $fromProducts = (clone $query)
            ->reorder()
            ->select('products.region')
            ->whereNotNull('products.region')
            ->where('products.region', '!=', '')
            ->distinct()
            ->pluck('products.region')
            ->all();
        if ($categorySlug === 'steam-gifts') {
            return CatalogAttr::uniqueSorted($fromProducts, true);
        }
        $fromSkus = Sku::query()
            ->whereIn('product_id', (clone $query)->reorder()->select('products.id'))
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->pluck('region')
            ->all();

        return CatalogAttr::uniqueSorted([...$fromProducts, ...$fromSkus], true);
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyPlatform(Builder $query, string $platform): void
    {
        if ($platform === '') {
            return;
        }
        GameKeyPlatform::applyFilter($query, $platform);
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyRegion(Builder $query, string $region): void
    {
        if ($region === '') {
            return;
        }
        $needle = mb_strtolower($region);
        $query->where(function ($inner) use ($needle) {
            $inner->whereRaw('LOWER(products.region) = ?', [$needle])
                ->orWhereHas('skus', fn ($sku) => $sku->whereRaw('LOWER(skus.region) = ?', [$needle]));
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyPriceRange(Builder $query, ?int $minPrice, ?int $maxPrice): void
    {
        $minPrice = $minPrice !== null && $minPrice > 0 ? $minPrice : null;
        $maxPrice = $maxPrice !== null && $maxPrice > 0 ? $maxPrice : null;
        if ($minPrice === null && $maxPrice === null) {
            return;
        }

        $effective = 'CASE WHEN skus.sale_price > 0 AND skus.sale_price < skus.price AND skus.sale_ends_at IS NOT NULL AND skus.sale_ends_at >= ? THEN skus.sale_price ELSE skus.price END';
        $bounds = ' >= ?';
        $boundValues = [$minPrice ?? 0];
        if ($minPrice !== null && $maxPrice !== null) {
            $bounds = ' BETWEEN ? AND ?';
            $boundValues = [$minPrice, $maxPrice];
        } elseif ($maxPrice !== null) {
            $bounds = ' <= ?';
            $boundValues = [$maxPrice];
        }

        $query->whereIn($query->getModel()->getQualifiedKeyName(), function ($sub) use ($effective, $bounds, $boundValues) {
            $sub->from('skus')
                ->select('skus.product_id')
                ->where('skus.status', 'active')
                ->where('skus.visible', true)
                ->groupBy('skus.product_id')
                ->havingRaw('MIN('.$effective.')'.$bounds, array_merge([now()->toDateTimeString()], $boundValues));
        });
    }

    /**
     * همان کوئری فهرست catalog و sitemap: products() همیشه storefrontProducts($group, true) را صدا می‌زند.
     *
     * @return Builder<Product>
     */
    public function storefrontListableQuery(): Builder
    {
        return $this->storefrontProducts(null, true);
    }

    /**
     * @return Builder<Product>
     */
    private function storefrontProducts(?CatalogGroup $group = null, bool $includeUnavailable = false): Builder
    {
        $query = Product::query()
            ->with('catalogGroup')
            ->with(['skus' => fn ($query) => $query->where('status', 'active')->where('visible', true)])
            ->where('status', 'active')
            ->notStorefrontExcluded()
            ->whereHas('skus', fn ($query) => $query->where('status', 'active'));
        if (! $group) {
            $query->where(fn ($inner) => $this->inVisibleGroup($inner));
        }
        if ($includeUnavailable) {
            $cutoff = now()->subDays(app(CatalogUrlLifecycle::class)->unavailableDays());
            $query->where(function (Builder $visibility) use ($cutoff) {
                $visibility->where('visible', true)
                    ->orWhere(function (Builder $oos) use ($cutoff) {
                        $oos->where('visible', false)
                            ->whereNotNull('unavailable_at')
                            ->where('unavailable_at', '>=', $cutoff);
                    });
            });
        } else {
            $query->where('visible', true);
        }
        if ($group) {
            $query->where('catalog_group_id', $group->id);
        }
        if ($group?->slug === 'steam-gifts') {
            $query->orderByDesc('steam_gift_appid')->orderByDesc('id');
        } elseif ($group?->slug === 'game-keys') {
            $query->orderByRaw($this->appidOrderSql())
                ->orderByDesc('id');
        } elseif (in_array($group?->slug, ['gift-card', 'game-topup', 'manual-services'], true)) {
            $query->orderByRaw($this->listedRankOrderSql())
                ->orderByDesc('id');
        } else {
            $query->orderBy('sort_order')->orderByDesc('id');
        }

        return $query;
    }

    /**
     * @return Builder<CatalogGroup>
     */
    private function visibleGroups(): Builder
    {
        return CatalogGroup::query()
            ->where('visible', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function excludeStorefrontCategory(Builder $query, ?string $excludeCategorySlug): void
    {
        $slug = is_string($excludeCategorySlug) ? trim($excludeCategorySlug) : '';
        if ($slug === '') {
            return;
        }
        $group = $this->visibleGroups()->where('slug', $slug)->first();
        if ($group) {
            $query->where(function (Builder $inner) use ($group) {
                $inner->whereNull('catalog_group_id')
                    ->orWhere('catalog_group_id', '!=', $group->id);
            });
        }
        if ($slug === 'steam-gifts') {
            $query->where('handle', 'not like', 'steam-gift-%');
        }
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function inVisibleGroup(Builder $query): void
    {
        $query->where(function ($inner) {
            $inner->whereNull('catalog_group_id')
                ->orWhereHas('catalogGroup', fn ($group) => $group->where('visible', true));
        });
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function normalizePage(?int $page, ?int $perPage): array
    {
        return [
            max(1, $page ?? 1),
            min(100, max(1, $perPage ?? 20)),
        ];
    }

    /**
     * @return array{page: int, perPage: int, total: int, totalPages: int}
     */
    private function meta(int $page, int $perPage, int $total): array
    {
        return [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function appidOrderSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST(json_extract(requirements, \'$.appid\') AS INTEGER) DESC'
            : 'CAST(JSON_UNQUOTE(JSON_EXTRACT(requirements, \'$.appid\')) AS UNSIGNED) DESC';
    }

    private function throwSlugRedirect(string $handle): void
    {
        $resolved = $this->resolvePath('/catalog/'.$handle);
        if (($resolved['status'] ?? '') === 'redirect' && filled($resolved['target'] ?? null)) {
            throw new ApiException('آدرس عوض شده است.', 301, 'SLUG_REDIRECT', data: ['to' => $resolved['target']]);
        }
    }

    private function listedRankOrderSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'COALESCE(CAST(json_extract(requirements, \'$.listedRank\') AS INTEGER), 2147483647) ASC'
            : 'COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(requirements, \'$.listedRank\')) AS UNSIGNED), 2147483647) ASC';
    }
}

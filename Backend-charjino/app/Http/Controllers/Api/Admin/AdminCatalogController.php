<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\SearchTag;
use App\Models\Sku;
use App\Modules\Catalog\BuyerInputFields;
use App\Modules\Catalog\CatalogAttr;
use App\Modules\Catalog\CatalogMedia;
use App\Modules\Catalog\CatalogOrigin;
use App\Modules\Catalog\CatalogSlug;
use App\Modules\Catalog\GameKeyPlatform;
use App\Modules\Catalog\PurchaseGuide;
use App\Modules\Pricing\PricingService;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\Shop2\Shop2Client;
use App\Support\AdminPage;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCatalogController extends Controller
{
    public function groups(Request $request): JsonResponse
    {
        $query = CatalogOrigin::withGroupOriginFlags(
            CatalogGroup::query()->withCount('products')
        );
        $source = trim((string) $request->query('source', ''));
        if ($source !== '') {
            CatalogOrigin::applyGroupFilter($query, $source);
        }
        $query->orderBy('sort_order')->orderBy('id');

        if ($request->query->has('page') || $request->query->has('perPage')) {
            $page = AdminPage::query($request, 10);
            $paginator = $query->paginate($page['perPage'], ['*'], 'page', $page['page']);

            return ApiResponse::ok([
                'items' => collect($paginator->items())->map->toAdmin()->values()->all(),
                'meta' => AdminPage::meta($paginator),
            ]);
        }

        $rows = $query->get()->map->toAdmin()->values();

        return ApiResponse::ok(['items' => $rows]);
    }

    public function group(CatalogGroup $group): JsonResponse
    {
        $group->loadCount('products');
        $group->loadExists([
            'products as has_shop2_products' => fn ($query) => $query->whereNotNull('shop2_category_id'),
            'products as has_fazer_products' => fn ($query) => $query->whereNotNull('fazercards_category_id')->where('fazercards_category_id', '!=', ''),
        ]);

        return ApiResponse::ok($group->toAdmin());
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'nameEn' => ['nullable', 'string', 'max:160'],
            'name' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:catalog_groups,slug'],
            'description' => ['nullable', 'string', 'max:4000'],
            'purchaseGuide' => ['nullable', 'string', 'max:20000'],
            'purchaseGuideItems' => ['nullable', 'array', 'max:20'],
            'purchaseGuideItems.*.type' => ['nullable', 'string', 'in:text,image,video,gif'],
            'purchaseGuideItems.*.title' => ['nullable', 'string', 'max:120'],
            'purchaseGuideItems.*.body' => ['nullable', 'string', 'max:8000'],
            'purchaseGuideItems.*.src' => ['nullable', 'string', 'max:500'],
            'purchaseGuideItems.*.caption' => ['nullable', 'string', 'max:300'],
            'imageUrl' => ['nullable', 'string', 'max:500'],
            'visible' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ]);

        $title = $data['title'];
        $nameEn = isset($data['nameEn']) ? trim((string) $data['nameEn']) : '';
        $group = CatalogGroup::query()->create([
            'shop2_big_category_id' => null,
            'name' => $data['name'] ?? ($nameEn !== '' ? $nameEn : $title),
            'slug' => $data['slug'] ?? CatalogSlug::unique($title),
            'title' => $title,
            'name_en' => $nameEn !== '' ? $nameEn : null,
            'description' => CatalogAttr::multiline($data['description'] ?? null),
            'purchase_guide' => array_key_exists('purchaseGuideItems', $data)
                ? PurchaseGuide::encode(is_array($data['purchaseGuideItems'] ?? null) ? $data['purchaseGuideItems'] : [])
                : CatalogAttr::multiline($data['purchaseGuide'] ?? null),
            'image_url' => $data['imageUrl'] ?? null,
            'visible' => $data['visible'] ?? true,
            'sort_order' => $data['sortOrder'] ?? 0,
        ]);

        return ApiResponse::ok($group->loadCount('products')->toAdmin(), 'دسته ساخته شد.');
    }

    public function updateGroup(Request $request, CatalogGroup $group): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'nameEn' => ['nullable', 'string', 'max:160'],
            'name' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('catalog_groups', 'slug')->ignore($group->id)],
            'description' => ['nullable', 'string', 'max:4000'],
            'seoTitle' => ['nullable', 'string', 'max:180'],
            'seoDescription' => ['nullable', 'string', 'max:300'],
            'purchaseGuide' => ['nullable', 'string', 'max:20000'],
            'purchaseGuideItems' => ['nullable', 'array', 'max:20'],
            'purchaseGuideItems.*.type' => ['nullable', 'string', 'in:text,image,video,gif'],
            'purchaseGuideItems.*.title' => ['nullable', 'string', 'max:120'],
            'purchaseGuideItems.*.body' => ['nullable', 'string', 'max:8000'],
            'purchaseGuideItems.*.src' => ['nullable', 'string', 'max:500'],
            'purchaseGuideItems.*.caption' => ['nullable', 'string', 'max:300'],
            'imageUrl' => ['nullable', 'string', 'max:500'],
            'visible' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($data['slug'])) {
            $group->slug = $data['slug'];
        }

        $nameEn = $group->name_en;
        if (array_key_exists('nameEn', $data)) {
            $value = is_string($data['nameEn']) ? trim($data['nameEn']) : '';
            $nameEn = $value !== '' ? $value : null;
        }

        $group->fill([
            'title' => $data['title'] ?? $group->title,
            'name_en' => $nameEn,
            'name' => $data['name'] ?? $group->name,
            'description' => array_key_exists('description', $data) ? CatalogAttr::multiline($data['description'] ?? null) : $group->description,
            'seo_title' => array_key_exists('seoTitle', $data)
                ? (trim((string) ($data['seoTitle'] ?? '')) !== '' ? trim((string) $data['seoTitle']) : null)
                : $group->seo_title,
            'seo_description' => array_key_exists('seoDescription', $data)
                ? (trim((string) ($data['seoDescription'] ?? '')) !== '' ? trim((string) $data['seoDescription']) : null)
                : $group->seo_description,
            'purchase_guide' => array_key_exists('purchaseGuideItems', $data)
                ? PurchaseGuide::encode(is_array($data['purchaseGuideItems'] ?? null) ? $data['purchaseGuideItems'] : [])
                : (array_key_exists('purchaseGuide', $data) ? CatalogAttr::multiline($data['purchaseGuide'] ?? null) : $group->purchase_guide),
            'image_url' => array_key_exists('imageUrl', $data) ? $data['imageUrl'] : $group->image_url,
            'visible' => $data['visible'] ?? $group->visible,
            'sort_order' => $data['sortOrder'] ?? $group->sort_order,
        ])->save();

        return ApiResponse::ok($group->fresh()->loadCount('products')->toAdmin(), 'دسته ذخیره شد.');
    }

    public function purgeUnlinkedGroups(): JsonResponse
    {
        return ApiResponse::ok(CatalogOrigin::purgeUnlinkedGroups(), 'دسته‌های ساخته‌شدهٔ بدون تأمین‌کننده حذف شدند.');
    }

    public function purgeShop2Catalog(): JsonResponse
    {
        return ApiResponse::ok(CatalogOrigin::purgeShop2Catalog(), 'کاتالوگ Shop2 از دیتابیس حذف شد.');
    }

    public function destroyGroup(CatalogGroup $group): JsonResponse
    {
        $group->loadExists([
            'products as has_shop2_products' => fn ($query) => $query->whereNotNull('shop2_category_id'),
            'products as has_fazer_products' => fn ($query) => $query->whereNotNull('fazercards_category_id')->where('fazercards_category_id', '!=', ''),
        ]);

        if (! CatalogOrigin::isManualGroup($group)) {
            $group->visible = false;
            $group->save();

            return ApiResponse::ok(
                $group->fresh()->loadCount('products')->toAdmin(),
                'دستهٔ همگام‌شده حذف فیزیکی نمی‌شود؛ از فروشگاه مخفی شد.'
            );
        }

        if ($group->products()->exists()) {
            throw new ApiException('این دسته محصول دارد. اول محصولات را جابه‌جا یا مخفی کن.', 409, 'CATEGORY_HAS_PRODUCTS');
        }

        $group->delete();

        return ApiResponse::ok(['id' => $group->id], 'دسته حذف شد.');
    }

    public function products(Request $request): JsonResponse
    {
        $page = AdminPage::query($request);
        $query = Product::query()->with('catalogGroup')->withCount('skus');

        $category = trim((string) $request->query('category', ''));
        if ($category !== '') {
            if (ctype_digit($category)) {
                $query->where('catalog_group_id', (int) $category);
            } else {
                $groupId = CatalogGroup::query()->where('slug', $category)->value('id');
                $query->where('catalog_group_id', $groupId ?: 0);
            }
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $visible = $request->query('visible');
        if ($visible === '1' || $visible === 'true') {
            $query->where('visible', true);
        } elseif ($visible === '0' || $visible === 'false') {
            $query->where('visible', false);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('title', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('handle', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhereHas('skus', function ($sku) use ($like) {
                        $sku->where('name', 'like', $like)->orWhere('name_en', 'like', $like);
                    });
            });
        }

        $source = trim((string) $request->query('source', ''));
        if ($source !== '') {
            CatalogOrigin::applyProductFilter($query, $source);
        }

        $platform = CatalogAttr::publicPlatform(trim((string) $request->query('platform', ''))) ?? '';
        $region = CatalogAttr::publicRegion(trim((string) $request->query('region', ''))) ?? '';
        $facets = $this->productFacets($query, $platform, $region);
        $this->applyAdminPlatform($query, $platform);
        $this->applyAdminRegion($query, $region);

        $paginator = $query->orderBy('sort_order')->orderBy('id')->paginate($page['perPage'], ['*'], 'page', $page['page']);

        return ApiResponse::ok([
            'items' => collect($paginator->items())->map(fn (Product $product) => $product->toAdmin())->all(),
            'meta' => AdminPage::meta($paginator) + $facets,
        ]);
    }

    public function product(Product $product): JsonResponse
    {
        return ApiResponse::ok($product->load('skus', 'catalogGroup', 'searchTags')->toAdmin());
    }

    public function excluded(Request $request): JsonResponse
    {
        $page = AdminPage::query($request);
        $query = Product::query()
            ->whereNotNull('excluded_reason')
            ->where('excluded_reason', '!=', '')
            ->with('skus')
            ->orderBy('excluded_reason')
            ->orderBy('title');
        $reason = trim((string) $request->query('reason', ''));
        if ($reason !== '') {
            $query->where('excluded_reason', $reason);
        }
        $paginator = $query->paginate($page['perPage'], ['*'], 'page', $page['page']);

        return ApiResponse::ok([
            'items' => collect($paginator->items())->map(fn (Product $product) => $product->toAdmin())->all(),
            'meta' => AdminPage::meta($paginator),
        ]);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'nameEn' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'seoTitle' => ['nullable', 'string', 'max:180'],
            'seoDescription' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:8000'],
            'publisher' => ['nullable', 'string', 'max:120'],
            'imageUrl' => ['nullable', 'string', 'max:2048'],
            'isPopular' => ['sometimes', 'boolean'],
            'visible' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,draft,hidden'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'catalogGroupId' => ['sometimes', 'nullable', 'integer', 'exists:catalog_groups,id'],
            'handle' => ['sometimes', 'string', 'max:120', Rule::unique('products', 'handle')->ignore($product->id)],
            'inputs' => ['sometimes', 'array'],
            'fazercardsCategoryId' => ['sometimes', 'nullable', 'string', 'max:120'],
            'searchTags' => ['sometimes'],
            'marginPercent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'clearSkuMargins' => ['sometimes', 'boolean'],
            'regionNote' => ['sometimes', 'nullable', 'string', 'max:500'],
            'purchaseSchema' => ['sometimes', 'array'],
            'clearExcluded' => ['sometimes', 'boolean'],
            'substituteProductId' => ['sometimes', 'nullable', 'integer', 'exists:products,id'],
        ]);

        if (array_key_exists('catalogGroupId', $data)) {
            $product->catalog_group_id = $data['catalogGroupId'];
            $group = $data['catalogGroupId'] ? CatalogGroup::query()->find($data['catalogGroupId']) : null;
            if ($group) {
                $product->category_name = $group->title;
            }
        }
        if (isset($data['handle'])) {
            $product->handle = $data['handle'];
        }
        if (array_key_exists('inputs', $data)) {
            $product->inputs = BuyerInputFields::sanitize($data['inputs'], true);
        }
        if (array_key_exists('fazercardsCategoryId', $data)) {
            $value = is_string($data['fazercardsCategoryId']) ? trim($data['fazercardsCategoryId']) : '';
            $product->fazercards_category_id = $value !== '' ? $value : null;
        }

        $nameEn = $product->name_en;
        if (array_key_exists('nameEn', $data)) {
            $value = is_string($data['nameEn']) ? trim($data['nameEn']) : '';
            $nameEn = $value !== '' ? $value : null;
        }

        if (array_key_exists('imageUrl', $data)) {
            $cover = is_string($data['imageUrl']) ? trim($data['imageUrl']) : '';
            $product->image_url = $cover !== '' ? $cover : null;
            if ($cover !== '') {
                $product->thumbnail = CatalogMedia::publicSrc($cover);
            }
        }

        if (array_key_exists('substituteProductId', $data)) {
            $substitute = $data['substituteProductId'];
            $product->substitute_product_id = $substitute && (int) $substitute !== (int) $product->id
                ? (int) $substitute
                : null;
        }
        if (array_key_exists('description', $data) && filled($data['description'])) {
            \App\Modules\BuyGuide\BuyGuidePublicValidator::assertText(
                \App\Modules\BuyGuide\BuyGuidePlainText::from((string) $data['description'])
            );
        }
        $product->fill([
            'title' => $data['title'] ?? $product->title,
            'name_en' => $nameEn,
            'subtitle' => array_key_exists('subtitle', $data) ? $data['subtitle'] : $product->subtitle,
            'seo_title' => array_key_exists('seoTitle', $data)
                ? (trim((string) ($data['seoTitle'] ?? '')) !== '' ? trim((string) $data['seoTitle']) : null)
                : $product->seo_title,
            'seo_description' => array_key_exists('seoDescription', $data)
                ? (trim((string) ($data['seoDescription'] ?? '')) !== '' ? trim((string) $data['seoDescription']) : null)
                : $product->seo_description,
            'description' => array_key_exists('description', $data) ? CatalogAttr::multiline($data['description'] ?? null) : $product->description,
            'publisher' => $data['publisher'] ?? $product->publisher,
            'is_popular' => $data['isPopular'] ?? $product->is_popular,
            'visible' => $data['visible'] ?? $product->visible,
            'status' => $data['status'] ?? $product->status,
            'sort_order' => $data['sortOrder'] ?? $product->sort_order,
        ]);
        if (array_key_exists('marginPercent', $data)) {
            $product->margin_percent = PricingService::normalizeOverride($data['marginPercent']);
        }
        $schema = is_array($product->purchase_schema) ? $product->purchase_schema : [];
        if (array_key_exists('regionNote', $data)) {
            $schema['regionNote'] = $data['regionNote'];
        }
        if (array_key_exists('purchaseSchema', $data) && is_array($data['purchaseSchema'])) {
            $incoming = $data['purchaseSchema'];
            if (array_key_exists('regionNote', $incoming)) {
                $schema['regionNote'] = $incoming['regionNote'];
            }
            if (isset($incoming['fields']) && is_array($incoming['fields']) && isset($schema['fields']) && is_array($schema['fields'])) {
                $byKey = [];
                foreach ($incoming['fields'] as $field) {
                    if (is_array($field) && isset($field['key'])) {
                        $byKey[(string) $field['key']] = $field;
                    }
                }
                $schema['fields'] = array_map(function ($field) use ($byKey) {
                    if (! is_array($field)) {
                        return $field;
                    }
                    $key = (string) ($field['key'] ?? '');
                    if ($key !== '' && isset($byKey[$key])) {
                        foreach (['labelFa', 'help', 'label'] as $prop) {
                            if (array_key_exists($prop, $byKey[$key])) {
                                $field[$prop] = $byKey[$key][$prop];
                            }
                        }
                    }

                    return $field;
                }, $schema['fields']);
            }
        }
        if ($schema !== []) {
            $product->purchase_schema = $schema;
        }
        if (! empty($data['clearExcluded'])) {
            $reason = (string) $product->excluded_reason;
            if (in_array($reason, ['credential_field', 'login_required'], true)) {
                throw new ApiException('محصولات با فیلد ورود یا رمز را نمی‌توان به ویترین برگرداند.', 400, 'ORDER_STATE_INVALID');
            }
            $product->excluded_reason = null;
            $product->visible = true;
            $product->status = 'active';
        }
        $product->save();

        if (array_key_exists('searchTags', $data)) {
            SearchTag::syncLabels($product, $this->searchTagLabels($data['searchTags']));
        }
        $pricing = app(PricingService::class);
        if (! empty($data['clearSkuMargins'])) {
            $product->skus()->update(['margin_percent' => null]);
            $product->unsetRelation('skus');
        }
        if (array_key_exists('marginPercent', $data) || ! empty($data['clearSkuMargins'])) {
            $pricing->repriceProduct($product->fresh() ?? $product);
        }

        return ApiResponse::ok($product->fresh()->load('skus', 'catalogGroup', 'searchTags')->toAdmin(), 'محصول ذخیره شد.');
    }

    /**
     * @return list<string>
     */
    private function searchTagLabels(mixed $raw): array
    {
        if (is_string($raw)) {
            $parts = preg_split('/[,،\n]+/u', $raw) ?: [];
        } elseif (is_array($raw)) {
            $parts = $raw;
        } else {
            return [];
        }
        $out = [];
        foreach ($parts as $part) {
            if (! is_scalar($part)) {
                continue;
            }
            $label = trim((string) $part);
            if ($label !== '') {
                $out[] = $label;
            }
        }

        return $out;
    }

    public function hideProduct(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'substituteProductId' => ['sometimes', 'nullable', 'integer', 'exists:products,id'],
        ]);
        if (array_key_exists('substituteProductId', $data)) {
            $substitute = $data['substituteProductId'];
            $product->substitute_product_id = $substitute && (int) $substitute !== (int) $product->id
                ? (int) $substitute
                : null;
        }
        $product->status = 'hidden';
        $product->visible = false;
        $product->save();

        return ApiResponse::ok($product->fresh()->load('skus')->toAdmin(), 'محصول مخفی شد.');
    }

    public function refreshRequirements(Product $product, Shop2Client $shop2): JsonResponse
    {
        $categoryId = (int) ($product->shop2_category_id ?: 0);
        if ($categoryId < 1) {
            throw new ApiException('این محصول به تأمین‌کننده وصل نیست.', 400, 'PRODUCT_UNAVAILABLE');
        }

        $fields = BuyerInputFields::sanitize($shop2->requirements($categoryId));
        $product->requirements = $fields;
        $product->inputs = $fields;
        $product->save();

        return ApiResponse::ok($product->fresh()->load('skus', 'catalogGroup')->toAdmin(), 'فیلدهای بازیکن به‌روز شد.');
    }

    public function updateSku(Request $request, Sku $sku): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'nameEn' => ['nullable', 'string', 'max:160'],
            'visible' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,draft,hidden'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'salePrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'saleEndsAt' => ['sometimes', 'nullable', 'date'],
            'fazercardsCategoryId' => ['sometimes', 'nullable', 'string', 'max:120'],
            'fazercardsOfferId' => ['sometimes', 'nullable', 'string', 'max:120'],
            'costUsd' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'giftSkuId' => ['sometimes', 'nullable', 'integer'],
            'marginPercent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $nameEn = $sku->name_en;
        if (array_key_exists('nameEn', $data)) {
            $value = is_string($data['nameEn']) ? trim($data['nameEn']) : '';
            $nameEn = $value !== '' ? $value : null;
        }

        $sku->fill([
            'name' => $data['name'] ?? $sku->name,
            'name_en' => $nameEn,
            'visible' => $data['visible'] ?? $sku->visible,
            'status' => $data['status'] ?? $sku->status,
        ]);

        $pricing = app(PricingService::class);
        $reprice = false;
        if (array_key_exists('marginPercent', $data)) {
            $sku->margin_percent = PricingService::normalizeOverride($data['marginPercent']);
            $sku->price_manual = false;
            $reprice = true;
        }

        if (array_key_exists('price', $data)) {
            $sku->price = Money::roundToman((int) $data['price']);
            $sku->price_manual = true;
            $reprice = false;
        }

        if (array_key_exists('costUsd', $data)) {
            $usd = Money::string($data['costUsd']);
            if (! Money::isPositive($usd)) {
                throw new ApiException('قیمت دلاری بسته را بنویس.', 422, 'GIFT_USD_REQUIRED');
            }
            $sku->cost_usd = $usd;
            $sku->live_cost_usd = null;
            if (! array_key_exists('price', $data)) {
                $sku->price_manual = false;
                $reprice = true;
            }
        }

        if (array_key_exists('salePrice', $data) || array_key_exists('saleEndsAt', $data)) {
            [$salePrice, $saleEnds] = $this->normalizedSale(
                $sku,
                $data['salePrice'] ?? null,
                $data['saleEndsAt'] ?? null,
            );
            $sku->sale_price = $salePrice;
            $sku->sale_ends_at = $saleEnds;
        }

        if (array_key_exists('fazercardsCategoryId', $data)) {
            $value = is_string($data['fazercardsCategoryId']) ? trim($data['fazercardsCategoryId']) : '';
            $sku->fazercards_category_id = $value !== '' ? $value : null;
        }
        if (array_key_exists('fazercardsOfferId', $data)) {
            $value = is_string($data['fazercardsOfferId']) ? trim($data['fazercardsOfferId']) : '';
            $sku->fazercards_offer_id = $value !== '' ? $value : null;
        }
        if (array_key_exists('giftSkuId', $data)) {
            $giftId = (int) ($data['giftSkuId'] ?? 0);
            if ($giftId < 1) {
                throw new ApiException('گیفت‌کارت معادل این بسته را انتخاب کن.', 422, 'GIFT_SKU_INVALID');
            }
            $gift = $this->giftCardSku($giftId);
            if ($gift->id === $sku->id) {
                throw new ApiException('بسته نمی‌تواند معادل خودش باشد.', 422, 'GIFT_SKU_INVALID');
            }
            $sku->gift_sku_id = $gift->id;
            if (! filled($sku->region) && filled($gift->region)) {
                $sku->region = $gift->region;
            }
        }

        $sku->save();
        if ($reprice) {
            $pricing->repriceSku($sku->fresh(['product']) ?? $sku);
        }

        return ApiResponse::ok($sku->fresh(['product'])->toAdmin(), 'بسته ذخیره شد.');
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'nameEn' => ['required', 'string', 'max:160'],
            'catalogGroupId' => ['required', 'integer', 'exists:catalog_groups,id'],
            'platform' => ['required', 'string', 'max:40'],
            'region' => ['nullable', 'string', 'max:40'],
            'publisher' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:8000'],
            'imageUrl' => ['nullable', 'string', 'max:2048'],
        ]);

        $group = CatalogGroup::query()->findOrFail((int) $data['catalogGroupId']);
        $platform = GameKeyPlatform::canonical($data['platform']) ?? CatalogAttr::normalize($data['platform']);
        $region = CatalogAttr::publicRegion($data['region'] ?? null);
        $nameEn = trim($data['nameEn']);
        $handle = $this->uniqueHandle($nameEn);
        $cover = trim((string) ($data['imageUrl'] ?? ''));

        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => 'local-'.$handle,
            'title' => trim($data['title']),
            'name_en' => $nameEn,
            'publisher' => trim((string) ($data['publisher'] ?? '')) !== '' ? trim((string) $data['publisher']) : 'شارژینو',
            'category_name' => $group->title,
            'subtitle' => 'تحویل گیفت‌کارت معادل',
            'description' => CatalogAttr::multiline($data['description'] ?? null),
            'thumbnail' => $cover !== '' ? CatalogMedia::publicSrc($cover) : '',
            'image_url' => $cover !== '' ? $cover : null,
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => null,
            'gift_proxy' => true,
            'platform' => $platform,
            'region' => $region,
            'inputs' => [],
            'sort_order' => 0,
        ]);

        return ApiResponse::ok($product->load('skus', 'catalogGroup')->toAdmin(), 'محصول گیفت‌معادل ساخته شد.');
    }

    public function storeSku(Request $request, Product $product): JsonResponse
    {
        if (! $product->gift_proxy) {
            throw new ApiException('بستهٔ گیفت‌معادل فقط روی محصول گیفت‌معادل ساخته می‌شود.', 422, 'GIFT_PROXY_REQUIRED');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'nameEn' => ['required', 'string', 'max:160'],
            'costUsd' => ['required', 'numeric', 'min:0'],
            'giftSkuId' => ['required', 'integer'],
        ]);

        $usd = Money::string($data['costUsd']);
        if (! Money::isPositive($usd)) {
            throw new ApiException('قیمت دلاری بسته را بنویس.', 422, 'GIFT_USD_REQUIRED');
        }
        $gift = $this->giftCardSku((int) $data['giftSkuId']);
        $nameEn = trim($data['nameEn']);
        $code = $this->uniqueSkuCode($product, $nameEn);
        $pricing = app(PricingService::class);

        $sku = Sku::query()->create([
            'product_id' => $product->id,
            'gift_sku_id' => $gift->id,
            'code' => $code,
            'name' => trim($data['name']),
            'name_en' => $nameEn,
            'price' => $pricing->sellTomanFor(null, $usd, $product),
            'price_manual' => false,
            'cost_usd' => $usd,
            'live_cost_usd' => null,
            'region' => $gift->region ?: $product->region,
            'status' => 'active',
            'visible' => true,
            'sort_order' => (int) $product->skus()->count(),
        ]);

        return ApiResponse::ok($sku->toAdmin(), 'بسته اضافه شد.');
    }

    public function giftCards(Request $request): JsonResponse
    {
        $platform = mb_strtolower(CatalogAttr::normalize((string) $request->query('platform', '')));
        $region = mb_strtolower(CatalogAttr::normalize((string) $request->query('region', '')));
        $q = trim((string) $request->query('q', ''));

        $query = Sku::query()
            ->with('product:id,title,platform,region')
            ->where('fulfillment_type', FazerCardsIds::GIFTCARD)
            ->where('status', 'active')
            ->where('visible', true)
            ->whereNotNull('fazercards_offer_id')
            ->where('fazercards_offer_id', '!=', '');

        if ($platform !== '') {
            $query->whereHas('product', fn ($product) => $product->whereRaw('lower(platform) = ?', [$platform]));
        }
        if ($region !== '') {
            $query->where(function ($inner) use ($region) {
                $inner->whereRaw('lower(coalesce(region, "")) = ?', [$region])
                    ->orWhereHas('product', fn ($product) => $product->whereRaw('lower(coalesce(region, "")) = ?', [$region]));
            });
        }
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('name', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhereHas('product', fn ($product) => $product->where('title', 'like', $like)->orWhere('name_en', 'like', $like));
            });
        }

        $items = $query->orderBy('name')->limit(100)->get()->map(function (Sku $sku) {
            return [
                'id' => $sku->id,
                'name' => $sku->name,
                'nameEn' => $sku->name_en,
                'productTitle' => $sku->product?->title,
                'platform' => CatalogAttr::publicPlatform($sku->product?->platform),
                'region' => CatalogAttr::publicRegion($sku->region ?: $sku->product?->region),
                'costUsd' => $sku->usdCost(),
            ];
        })->values()->all();

        return ApiResponse::ok(['items' => $items]);
    }

    private function giftCardSku(int $id): Sku
    {
        $sku = Sku::query()->find($id);
        if (! $sku instanceof Sku || $sku->supplierSku() !== $sku || ! $sku->isFazerCards()) {
            throw new ApiException('فقط بستهٔ گیفت‌کارت تأمین‌کننده را می‌توان معادل کرد.', 422, 'GIFT_SKU_INVALID');
        }
        $family = FazerCardsIds::familyOf($sku->fulfillment_type, $sku->fazercards_category_id);
        if ($family !== FazerCardsIds::GIFTCARD) {
            throw new ApiException('فقط بستهٔ گیفت‌کارت تأمین‌کننده را می‌توان معادل کرد.', 422, 'GIFT_SKU_INVALID');
        }

        return $sku;
    }

    private function uniqueHandle(string $nameEn): string
    {
        $base = Str::slug($nameEn);
        if ($base === '') {
            $base = 'gift-item';
        }
        $handle = $base;
        $n = 2;
        while (Product::query()->where('handle', $handle)->exists()) {
            $handle = $base.'-'.$n;
            $n++;
        }

        return $handle;
    }

    private function uniqueSkuCode(Product $product, string $nameEn): string
    {
        $base = Str::slug($nameEn);
        if ($base === '') {
            $base = 'pack';
        }
        $code = $base;
        $n = 2;
        while ($product->skus()->where('code', $code)->exists()) {
            $code = $base.'-'.$n;
            $n++;
        }

        return $code;
    }

    /**
     * @return array{0: int|null, 1: Carbon|null}
     */
    private function normalizedSale(Sku $sku, mixed $rawSale, mixed $rawEnds): array
    {
        $saleEmpty = $rawSale === null || $rawSale === '' || (int) $rawSale === 0;
        $endsValue = is_string($rawEnds) ? trim($rawEnds) : '';
        $endsEmpty = $endsValue === '';
        if ($saleEmpty && $endsEmpty) {
            return [null, null];
        }
        if ($saleEmpty || $endsEmpty) {
            throw new ApiException('برای تخفیف هم قیمت و هم تاریخ پایان لازم است.', 422, 'SALE_INCOMPLETE');
        }

        $salePrice = Money::roundToman((int) $rawSale);
        $listPrice = Money::roundToman((int) $sku->price);
        if ($salePrice < Money::TOMAN_STEP) {
            throw new ApiException('قیمت تخفیف خیلی کم است.', 422, 'SALE_TOO_LOW');
        }
        if ($salePrice >= $listPrice) {
            throw new ApiException('قیمت تخفیف باید کمتر از قیمت فروش باشد.', 422, 'SALE_TOO_HIGH');
        }
        if (! Money::isCredibleSale($salePrice, $listPrice)) {
            throw new ApiException('تخفیف بیش از حد زیاد است؛ قیمت ویژه باید حداقل ۳۰٪ قیمت اصلی باشد.', 422, 'SALE_DISCOUNT_TOO_DEEP');
        }

        $ends = Carbon::parse($endsValue, (string) config('app.timezone'))->endOfDay();
        if ($ends->lt(now())) {
            throw new ApiException('تاریخ پایان تخفیف باید امروز یا بعد باشد.', 422, 'SALE_ENDED');
        }

        return [$salePrice, $ends];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return array{platforms: list<string>, regions: list<string>}
     */
    private function productFacets($query, string $platform, string $region): array
    {
        $forPlatforms = clone $query;
        $this->applyAdminRegion($forPlatforms, $region);
        $forRegions = clone $query;
        $this->applyAdminPlatform($forRegions, $platform);

        $platforms = (clone $forPlatforms)
            ->reorder()
            ->select('products.platform')
            ->whereNotNull('products.platform')
            ->where('products.platform', '!=', '')
            ->distinct()
            ->pluck('products.platform')
            ->all();
        $fromProducts = (clone $forRegions)
            ->reorder()
            ->select('products.region')
            ->whereNotNull('products.region')
            ->where('products.region', '!=', '')
            ->distinct()
            ->pluck('products.region')
            ->all();
        $fromSkus = Sku::query()
            ->whereIn('product_id', (clone $forRegions)->reorder()->select('products.id'))
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->pluck('region')
            ->all();

        return [
            'platforms' => GameKeyPlatform::mergeFacets($platforms),
            'regions' => CatalogAttr::uniqueSorted([...$fromProducts, ...$fromSkus], true),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     */
    private function applyAdminPlatform($query, string $platform): void
    {
        if ($platform === '') {
            return;
        }
        GameKeyPlatform::applyFilter($query, $platform);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     */
    private function applyAdminRegion($query, string $region): void
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
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Sku;
use App\Modules\Catalog\HomeCopy;
use App\Modules\Catalog\HomeHtml;
use App\Modules\Catalog\HomeSections;
use App\Modules\Catalog\MediaType;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHomeController extends Controller
{
    public function show(): JsonResponse
    {
        $banners = Banner::query()->orderBy('sort_order')->orderBy('id')->get()->map->toAdmin()->values();
        $flash = FlashSaleItem::query()->orderBy('sort_order')->orderBy('id')->get()->map->toAdmin()->values();
        $popular = Product::query()
            ->with('catalogGroup')
            ->where('is_popular', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map->toAdmin()
            ->values();

        return ApiResponse::ok(HomeCopy::get() + [
            'banners' => $banners,
            'flashSale' => $flash,
            'popular' => $popular,
            'sections' => HomeSections::adminRows(),
        ]);
    }

    public function updateCopy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'flashSaleTitle' => ['nullable', 'string', 'max:80'],
            'flashSaleSubtitle' => ['nullable', 'string', 'max:200'],
            'flashSaleEndsAt' => ['nullable', 'date'],
            'popularTitle' => ['nullable', 'string', 'max:80'],
            'popularSubtitle' => ['nullable', 'string', 'max:200'],
            'introHtml' => ['nullable', 'string', 'max:8000'],
        ]);

        if (array_key_exists('flashSaleTitle', $data) && $data['flashSaleTitle'] !== null) {
            Setting::putValue('flash_sale_title', $data['flashSaleTitle']);
        }
        if (array_key_exists('flashSaleSubtitle', $data) && $data['flashSaleSubtitle'] !== null) {
            Setting::putValue('flash_sale_subtitle', $data['flashSaleSubtitle']);
        }
        if (array_key_exists('flashSaleEndsAt', $data)) {
            Setting::putValue(
                'flash_sale_ends_at',
                $data['flashSaleEndsAt'] ? (string) $data['flashSaleEndsAt'] : null
            );
        }
        if (array_key_exists('popularTitle', $data) && $data['popularTitle'] !== null) {
            Setting::putValue('popular_title', $data['popularTitle']);
        }
        if (array_key_exists('popularSubtitle', $data) && $data['popularSubtitle'] !== null) {
            Setting::putValue('popular_subtitle', $data['popularSubtitle']);
        }
        if (array_key_exists('introHtml', $data) && $data['introHtml'] !== null) {
            Setting::putValue('home_intro_html', HomeHtml::sanitize($data['introHtml']));
        }

        return ApiResponse::ok(HomeCopy::get(), 'متن صفحهٔ اصلی ذخیره شد.');
    }

    public function updateSections(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sections' => ['required', 'array', 'max:40'],
            'sections.*.groupId' => ['required', 'integer', 'min:1'],
            'sections.*.enabled' => ['required', 'boolean'],
            'sections.*.limit' => ['required', 'integer', 'min:1', 'max:'.HomeSections::LIMIT_MAX],
            'sections.*.productIds' => ['sometimes', 'array', 'max:'.HomeSections::LIMIT_MAX],
            'sections.*.productIds.*' => ['integer', 'min:1'],
        ]);
        HomeSections::store($data['sections']);

        return ApiResponse::ok(['sections' => HomeSections::adminRows()], 'بخش‌های صفحهٔ اصلی ذخیره شد.');
    }

    public function storeBanner(Request $request): JsonResponse
    {
        $data = $this->bannerRules($request);
        $banner = Banner::query()->create($this->bannerAttrs($data));

        return ApiResponse::ok($banner->fresh()->toAdmin(), 'اسلاید ذخیره شد.');
    }

    public function updateBanner(Request $request, Banner $banner): JsonResponse
    {
        $data = $this->bannerRules($request, true);
        $banner->fill($this->bannerAttrs($data, $banner))->save();

        return ApiResponse::ok($banner->fresh()->toAdmin(), 'اسلاید ذخیره شد.');
    }

    public function destroyBanner(Banner $banner): JsonResponse
    {
        $banner->delete();

        return ApiResponse::ok(['id' => $banner->id], 'اسلاید حذف شد.');
    }

    public function storeFlashSale(Request $request): JsonResponse
    {
        $data = $this->flashRules($request);
        $attrs = $this->flashAttrs($data);
        $this->assertCredibleFlash($attrs);
        $skuId = $attrs['sku_id'] ?? null;
        $item = $skuId
            ? FlashSaleItem::query()->updateOrCreate(['sku_id' => $skuId], $attrs)
            : FlashSaleItem::query()->create($attrs);

        return ApiResponse::ok($item->fresh()->toAdmin(), 'آیتم فروش ویژه ذخیره شد.');
    }

    public function updateFlashSale(Request $request, FlashSaleItem $flashSaleItem): JsonResponse
    {
        $data = $this->flashRules($request, true);
        $attrs = $this->flashAttrs($data, $flashSaleItem);
        if (($attrs['visible'] ?? true) && ! Money::isCredibleSale((int) $attrs['price'], (int) $attrs['original_price'])) {
            $this->assertCredibleFlash($attrs);
        }
        $flashSaleItem->fill($attrs)->save();

        return ApiResponse::ok($flashSaleItem->fresh()->toAdmin(), 'آیتم فروش ویژه ذخیره شد.');
    }

    public function destroyFlashSale(FlashSaleItem $flashSaleItem): JsonResponse
    {
        $flashSaleItem->delete();

        return ApiResponse::ok(['id' => $flashSaleItem->id], 'آیتم فروش ویژه حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function bannerRules(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'src' => [$required, 'string', 'max:500'],
            'title' => [$required, 'string', 'max:160'],
            'href' => [$required, 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:400'],
            'mediaType' => ['nullable', 'in:image,gif,video'],
            'visible' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function bannerAttrs(array $data, ?Banner $existing = null): array
    {
        $src = $data['src'] ?? $existing?->src ?? '';

        return [
            'src' => $src,
            'title' => $data['title'] ?? $existing?->title,
            'href' => $data['href'] ?? $existing?->href,
            'description' => array_key_exists('description', $data)
                ? (string) ($data['description'] ?? '')
                : ($existing?->description ?? ''),
            'media_type' => $data['mediaType'] ?? MediaType::fromSrc($src),
            'visible' => $data['visible'] ?? $existing?->visible ?? true,
            'sort_order' => $data['sortOrder'] ?? $existing?->sort_order ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function flashRules(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'skuId' => [$required, 'integer', 'exists:skus,id'],
            'price' => [$required, 'integer', 'min:0'],
            'originalPrice' => [$required, 'integer', 'min:0'],
            'image' => ['nullable', 'string', 'max:500'],
            'visible' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function flashAttrs(array $data, ?FlashSaleItem $existing = null): array
    {
        $skuId = $data['skuId'] ?? $existing?->sku_id;
        $sku = $skuId ? Sku::query()->with('product')->findOrFail($skuId) : null;
        $product = $sku?->product;

        return [
            'sku_id' => $sku?->id,
            'image' => $data['image'] ?? $existing?->image ?? $product?->displayImage() ?? '',
            'original_price' => Money::roundToman((int) ($data['originalPrice'] ?? $existing?->original_price ?? 0)),
            'price' => Money::roundToman((int) ($data['price'] ?? $existing?->price ?? 0)),
            'product_code' => $product?->code ?? $existing?->product_code ?? '',
            'product_handle' => $product?->handle ?? $existing?->product_handle ?? '',
            'product_name' => $product?->title ?? $existing?->product_name ?? '',
            'product_variant_code' => $sku?->code ?? $existing?->product_variant_code ?? '',
            'product_variant_name' => $sku?->name ?? $existing?->product_variant_name ?? '',
            'visible' => $data['visible'] ?? $existing?->visible ?? true,
            'sort_order' => $data['sortOrder'] ?? $existing?->sort_order ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function assertCredibleFlash(array $attrs): void
    {
        if (Money::isCredibleSale((int) $attrs['price'], (int) $attrs['original_price'])) {
            return;
        }

        throw new ApiException(
            'تخفیف فروش ویژه غیرمنطقی است. قیمت ویژه باید کمتر از قیمت اصلی و حداقل ۳۰٪ آن باشد.',
            422,
            'SALE_DISCOUNT_TOO_DEEP',
        );
    }
}

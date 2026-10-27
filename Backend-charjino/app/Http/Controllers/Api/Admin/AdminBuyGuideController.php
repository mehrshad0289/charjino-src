<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyGuide;
use App\Models\BuyGuideMedia;
use App\Modules\BuyGuide\BuyGuideMediaService;
use App\Modules\BuyGuide\BuyGuideService;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBuyGuideController extends Controller
{
    public function coverage(BuyGuideService $guides): JsonResponse
    {
        return ApiResponse::ok(['items' => $guides->coverage()]);
    }

    public function index(Request $request, BuyGuideService $guides): JsonResponse
    {
        $productId = $request->filled('product_id') ? (int) $request->query('product_id') : null;

        return ApiResponse::ok([
            'items' => $guides->list(
                $request->query('category'),
                $request->query('subcategory'),
                $productId,
            ),
        ]);
    }

    public function show(BuyGuide $guide, BuyGuideService $guides): JsonResponse
    {
        return ApiResponse::ok($guides->show($guide));
    }

    public function store(Request $request, BuyGuideService $guides): JsonResponse
    {
        $data = $this->validated($request, false);
        $guide = $guides->create($data, AuthUser::id($request));

        return ApiResponse::ok($guides->show($guide), 'راهنما ذخیره شد.', 201);
    }

    public function update(Request $request, BuyGuide $guide, BuyGuideService $guides): JsonResponse
    {
        $data = $this->validated($request, true);
        $guide = $guides->update($guide, $data, AuthUser::id($request));

        return ApiResponse::ok($guides->show($guide), 'راهنما به‌روز شد.');
    }

    public function destroy(BuyGuide $guide, BuyGuideService $guides): JsonResponse
    {
        $guides->delete($guide);

        return ApiResponse::ok(null, 'راهنما حذف شد.');
    }

    public function duplicate(Request $request, BuyGuide $guide, BuyGuideService $guides): JsonResponse
    {
        $data = $request->validate([
            'scopeType' => ['required', 'in:category,subcategory,product'],
            'category' => ['nullable', 'string', 'max:40'],
            'subcategory' => ['nullable', 'string', 'max:80'],
            'productId' => ['nullable', 'integer'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string'],
        ]);
        $copy = $guides->duplicate($guide, $data, AuthUser::id($request));

        return ApiResponse::ok($guides->show($copy), 'کپی راهنما ساخته شد.', 201);
    }

    public function publish(Request $request, BuyGuide $guide, BuyGuideService $guides): JsonResponse
    {
        $guide = $guides->publish($guide, AuthUser::id($request));

        return ApiResponse::ok($guides->show($guide), 'راهنما منتشر شد.');
    }

    public function unpublish(Request $request, BuyGuide $guide, BuyGuideService $guides): JsonResponse
    {
        $guide = $guides->unpublish($guide, AuthUser::id($request));

        return ApiResponse::ok($guides->show($guide), 'راهنما از انتشار خارج شد.');
    }

    public function resolvePreview(Request $request, BuyGuideService $guides): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        return ApiResponse::ok($guides->resolvePreview((int) $data['product_id']));
    }

    public function regionOptions(Request $request, BuyGuideService $guides): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:40'],
            'subcategory' => ['nullable', 'string', 'max:80'],
        ]);

        return ApiResponse::ok([
            'items' => $guides->regionOptions($data['category'], (string) ($data['subcategory'] ?? '')),
        ]);
    }

    public function uploadMedia(Request $request, BuyGuideMediaService $media): JsonResponse
    {
        $kind = $request->input('kind', 'image');
        $request->validate([
            'alt' => ['required', 'string', 'min:3', 'max:180'],
            'kind' => ['nullable', 'in:image,video'],
            'file' => $kind === 'video'
                ? ['required', 'file', 'mimetypes:video/mp4', 'max:51200']
                : ['required', 'image', 'max:6144'],
        ]);
        $file = $request->file('file');
        $row = $kind === 'video'
            ? $media->storeVideo($file, (string) $request->input('alt'))
            : $media->storeImage($file, (string) $request->input('alt'));

        return ApiResponse::ok($row->toApi(), 'رسانه ذخیره شد.');
    }

    public function updateMedia(Request $request, BuyGuideMedia $media, BuyGuideMediaService $service): JsonResponse
    {
        $data = $request->validate([
            'alt' => ['required', 'string', 'min:3', 'max:180'],
        ]);
        $row = $service->updateAlt($media, $data['alt']);

        return ApiResponse::ok($row->toApi(), 'متن جایگزین ذخیره شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial): array
    {
        return $request->validate([
            'scopeType' => [$partial ? 'nullable' : 'required', 'in:category,subcategory,product'],
            'category' => ['nullable', 'string', 'max:40'],
            'subcategory' => ['nullable', 'string', 'max:80'],
            'productId' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'intro' => ['nullable', 'string', 'max:20000'],
            'status' => ['nullable', 'in:draft,published'],
            'lastReviewedAt' => ['nullable', 'date'],
            'seoTitle' => ['nullable', 'string', 'max:180'],
            'seoDescription' => ['nullable', 'string', 'max:300'],
            'sections' => ['nullable', 'array'],
            'sections.*.type' => ['required_with:sections', 'in:before_buy,fields,purchase_steps,redeem,regions,errors,faq,videos,sources'],
            'sections.*.inherit' => ['nullable', 'boolean'],
            'sections.*.data' => ['nullable', 'array'],
        ]);
    }
}

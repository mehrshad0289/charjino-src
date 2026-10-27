<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Modules\Blog\BlogImageService;
use App\Modules\Catalog\MediaType;
use App\Modules\Pricing\FxRateService;
use App\Modules\Pricing\PricingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function show(PricingService $pricing): JsonResponse
    {
        return ApiResponse::ok($pricing->snapshot());
    }

    public function update(Request $request, PricingService $pricing): JsonResponse
    {
        $data = $request->validate([
            'usdTomanRate' => ['nullable', 'numeric', 'min:0'],
            'marginPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fxMode' => ['nullable', 'in:auto,manual'],
        ]);

        if (array_key_exists('marginPercent', $data) && $data['marginPercent'] !== null) {
            Setting::putValue('margin_percent', (string) $data['marginPercent']);
        }
        if (array_key_exists('fxMode', $data) && $data['fxMode']) {
            Setting::putValue('fx_mode', $data['fxMode']);
        }
        if (array_key_exists('usdTomanRate', $data) && $data['usdTomanRate'] !== null) {
            Setting::putValue('usd_toman_rate', (string) $data['usdTomanRate']);
            Setting::putValue('usd_toman_source', 'manual');
            Setting::putValue('usd_toman_updated_at', now()->toIso8601String());
            Setting::putValue('fx_mode', $data['fxMode'] ?? 'manual');
        }

        $pricing->repriceAllSkus();

        return ApiResponse::ok($pricing->snapshot(), 'تنظیمات قیمت ذخیره شد.');
    }

    public function refreshFx(FxRateService $fx): JsonResponse
    {
        Setting::putValue('fx_mode', 'auto');

        return ApiResponse::ok($fx->refresh(), 'نرخ دلار به‌روز شد.');
    }

    public function upload(Request $request, BlogImageService $blogImages): JsonResponse
    {
        $purpose = $request->input('purpose', 'catalog');
        $banner = $purpose === 'banner';
        $blog = $purpose === 'blog';

        $request->validate([
            'purpose' => ['nullable', 'in:catalog,banner,blog'],
            'file' => $banner
                ? ['required', 'file', 'max:8192', 'mimes:jpeg,jpg,png,gif,webp,mp4,webm']
                : ['required', 'image', 'max:4096', $blog ? 'mimes:jpeg,jpg,png,webp' : 'mimes:jpeg,jpg,png,gif,webp'],
            'alt' => ['nullable', 'string', 'max:180'],
            'role' => ['nullable', 'in:inline,cover'],
        ]);

        if ($blog) {
            $stored = $blogImages->store(
                $request->file('file'),
                (string) $request->input('alt', ''),
                $request->input('role') === 'cover',
            );

            return ApiResponse::ok([
                ...$stored,
                'mediaType' => 'image',
            ], 'تصویر ذخیره شد.');
        }

        $file = $request->file('file');
        $folder = $banner ? 'banners' : 'catalog';
        $path = $file->store($folder, 'public');

        return ApiResponse::ok([
            'url' => '/storage/'.$path,
            'path' => $path,
            'mediaType' => MediaType::fromUpload($file->getMimeType(), $file->getClientOriginalName()),
        ], $banner ? 'رسانه ذخیره شد.' : 'تصویر ذخیره شد.');
    }
}

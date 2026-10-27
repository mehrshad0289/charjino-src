<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\CatalogService;
use App\Modules\SmartSearch\SmartSearchService;
use App\Modules\Supplier\FazerCards\SteamCoverResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly SmartSearchService $smartSearch,
    ) {}

    public function home(): JsonResponse
    {
        return $this->cachedOk($this->catalog->home());
    }

    public function categories(): JsonResponse
    {
        return $this->cachedOk($this->catalog->categories());
    }

    public function category(Request $request, string $slug): JsonResponse
    {
        return $this->cachedOk($this->catalog->category(
            $slug,
            $this->optionalPage($request, 'page'),
            $this->optionalPage($request, 'perPage'),
        ));
    }

    public function smartSearch(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 10)));
        $mode = trim((string) $request->query('mode', 'auto'));
        if ($mode === '') {
            $mode = 'auto';
        }
        $group = $this->optionalAttr($request, 'group');
        $platform = $this->optionalAttr($request, 'platform');
        $region = $this->optionalAttr($request, 'region');

        return ApiResponse::ok($this->smartSearch->search($q, $page, $perPage, $mode, $group, $platform, $region));
    }

    public function products(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $excludeCategory = trim((string) $request->query('excludeCategory', ''));

        return $this->cachedOk($this->catalog->products(
            $q !== '' ? $q : null,
            $request->boolean('popular'),
            $category !== '' ? $category : null,
            $this->optionalPage($request, 'page'),
            $this->optionalPage($request, 'perPage'),
            $this->optionalToman($request, 'minPrice'),
            $this->optionalToman($request, 'maxPrice'),
            $this->optionalAttr($request, 'platform'),
            $this->optionalAttr($request, 'region'),
            $this->optionalAttr($request, 'sort'),
            $excludeCategory !== '' ? $excludeCategory : null,
            $request->has('indexable') ? $request->boolean('indexable') : null,
        ));
    }

    public function steamCover(int $appId): JsonResponse
    {
        $url = app(SteamCoverResolver::class)->url($appId);
        if ($url === '') {
            return ApiResponse::fail('کاور استیم پیدا نشد.', 404);
        }

        return ApiResponse::ok(['url' => $url]);
    }

    public function product(string $handle): JsonResponse
    {
        return $this->cachedOk($this->catalog->product($handle));
    }

    public function productPrices(string $handle): JsonResponse
    {
        return ApiResponse::ok($this->catalog->productPrices($handle));
    }

    public function resolve(Request $request): JsonResponse
    {
        $path = trim((string) $request->query('path', ''));

        return $this->cachedOk($this->catalog->resolvePath($path));
    }

    public function steamTopupQuote(Request $request): JsonResponse
    {
        $currency = strtoupper(trim((string) $request->query('currency', '')));
        $amount = trim((string) $request->query('amount', ''));

        return ApiResponse::ok($this->catalog->steamTopupQuote($currency, $amount));
    }

    private function cachedOk(mixed $data): JsonResponse
    {
        return ApiResponse::ok($data)->header(
            'Cache-Control',
            'public, max-age=60, s-maxage=3600, stale-while-revalidate=86400'
        );
    }

    private function optionalPage(Request $request, string $key): ?int
    {
        if (! $request->filled($key)) {
            return null;
        }

        return (int) $request->query($key);
    }

    private function optionalToman(Request $request, string $key): ?int
    {
        if (! $request->filled($key)) {
            return null;
        }

        $value = (int) $request->query($key);

        return $value > 0 ? $value : null;
    }

    private function optionalAttr(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));

        return $value !== '' ? $value : null;
    }
}

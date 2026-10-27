<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFavorite;
use App\Modules\Catalog\FavoriteService;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $favorites) {}

    public function index(Request $request): JsonResponse
    {
        $userId = AuthUser::id($request);
        $productId = (int) $request->query('productId', 0);
        if ($productId > 0) {
            return ApiResponse::ok([
                'productId' => $productId,
                'favorited' => $this->favorites->isFavorited($userId, $productId),
            ]);
        }

        if ($request->boolean('idsOnly')) {
            return ApiResponse::ok([
                'productIds' => $this->favorites->idsForUser($userId),
            ]);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('perPage', 20)));
        $paginator = $this->favorites->listForUser($userId, $page, $perPage);

        return ApiResponse::ok([
            'items' => collect($paginator->items())
                ->map(fn (UserFavorite $favorite) => $this->favorites->toCustomerItem($favorite))
                ->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'productId' => ['required', 'integer', 'min:1'],
        ]);
        $favorite = $this->favorites->add(AuthUser::id($request), (int) $data['productId']);
        $favorite->load(['product.catalogGroup', 'product.skus']);

        return ApiResponse::ok([
            'favorited' => true,
            'item' => $this->favorites->toCustomerItem($favorite),
        ]);
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $this->favorites->remove(AuthUser::id($request), $product);

        return ApiResponse::ok([
            'productId' => $product,
            'favorited' => false,
        ]);
    }
}

<?php

namespace App\Modules\Catalog;

use App\Exceptions\ApiException;
use App\Models\Product;
use App\Models\UserFavorite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FavoriteService
{
    /**
     * @return LengthAwarePaginator<int, UserFavorite>
     */
    public function listForUser(string $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return UserFavorite::query()
            ->with(['product.catalogGroup', 'product.skus'])
            ->where('user_id', $userId)
            ->whereHas('product', function ($query) {
                $query->where('status', 'active')->where('visible', true);
            })
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function isFavorited(string $userId, int $productId): bool
    {
        return UserFavorite::query()->where('user_id', $userId)->where('product_id', $productId)->exists();
    }

    /**
     * @return list<int>
     */
    public function idsForUser(string $userId): array
    {
        return UserFavorite::query()
            ->where('user_id', $userId)
            ->whereHas('product', function ($query) {
                $query->where('status', 'active')->where('visible', true);
            })
            ->orderByDesc('id')
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function add(string $userId, int $productId): UserFavorite
    {
        $product = Product::query()
            ->where('id', $productId)
            ->where('status', 'active')
            ->where('visible', true)
            ->first();
        if (! $product) {
            throw new ApiException('محصول پیدا نشد.', 404, 'PRODUCT_NOT_FOUND');
        }

        return UserFavorite::query()->firstOrCreate([
            'user_id' => $userId,
            'product_id' => $product->id,
        ]);
    }

    public function remove(string $userId, int $productId): void
    {
        UserFavorite::query()->where('user_id', $userId)->where('product_id', $productId)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function toCustomerItem(UserFavorite $favorite): array
    {
        $product = $favorite->product;
        if (! $product) {
            return [
                'id' => $favorite->id,
                'productId' => $favorite->product_id,
                'product' => null,
            ];
        }

        return [
            'id' => $favorite->id,
            'productId' => $favorite->product_id,
            'product' => [
                'id' => $product->id,
                'handle' => $product->handle,
                'title' => $product->storefrontTitle(),
                'thumbnail' => $product->displayImage(),
                'minPrice' => $product->storefrontMinPrice(),
            ],
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Modules\Catalog\StorefrontCopy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight homepage card: id, slug, name, image, price, badge.
 *
 * @mixin Product
 */
class HomeProductResource extends JsonResource
{
    /**
     * @return array{id: int, slug: string, name: string, image: string, price: int, badge: array<string, mixed>}
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $group = $product->relationLoaded('catalogGroup') ? $product->catalogGroup : null;
        $groupTitle = $group?->storefrontTitle() ?: StorefrontCopy::groupTitle($product->category_name);

        return self::card(
            (int) $product->id,
            (string) $product->handle,
            StorefrontCopy::productTitle($product->title, $groupTitle),
            (string) $product->displayImage(),
            (int) ($product->storefrontMinPrice() ?? 0),
            array_filter([
                'platform' => $product->storefrontPlatform() ?: null,
                'region' => $product->storefrontRegion() ?: null,
                'publisher' => $product->storefrontPublisher() ?: null,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: int, slug: string, name: string, image: string, price: int, badge: array<string, mixed>}
     */
    public static function fromFlash(array $row): array
    {
        return self::card(
            (int) ($row['id'] ?? 0),
            (string) ($row['productHandle'] ?? ''),
            (string) ($row['productName'] ?? ''),
            (string) ($row['image'] ?? ''),
            (int) ($row['price'] ?? 0),
            array_filter([
                'originalPrice' => isset($row['originalPrice']) ? (int) $row['originalPrice'] : null,
                'variant' => isset($row['productVariantName']) && $row['productVariantName'] !== ''
                    ? (string) $row['productVariantName']
                    : null,
            ], fn ($value) => $value !== null && $value !== ''),
        );
    }

    /**
     * @param  array<string, mixed>  $badge
     * @return array{id: int, slug: string, name: string, image: string, price: int, badge: array<string, mixed>}
     */
    public static function card(int $id, string $slug, string $name, string $image, int $price, array $badge = []): array
    {
        return [
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'image' => $image,
            'price' => $price,
            'badge' => $badge,
        ];
    }
}

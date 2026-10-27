<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontHomeCache;
use App\Modules\Catalog\CatalogAttr;
use App\Modules\Catalog\CatalogOrigin;
use App\Modules\Catalog\CatalogMedia;
use App\Modules\Catalog\CatalogSlug;
use App\Modules\Catalog\PurchaseGuide;
use App\Modules\Catalog\StorefrontCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogGroup extends Model
{
    use FlushesStorefrontHomeCache;
    use Concerns\SearchIndexable;

    protected $fillable = [
        'shop2_big_category_id',
        'name',
        'slug',
        'title',
        'name_en',
        'description',
        'seo_title',
        'seo_description',
        'purchase_guide',
        'image_url',
        'visible',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (CatalogGroup $group): void {
            if (filled($group->slug)) {
                return;
            }

            $group->slug = CatalogSlug::unique(
                (string) ($group->name ?: $group->title),
                $group->shop2_big_category_id,
            );
        });
    }

    protected function casts(): array
    {
        return [
            'shop2_big_category_id' => 'integer',
            'visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function searchIndexName(): string
    {
        return 'taxonomies';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function storefrontTitle(): string
    {
        return StorefrontCopy::groupTitle($this->title, $this->name);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->storefrontTitle(),
            'titleEn' => $this->name_en,
            'description' => CatalogAttr::multiline($this->description),
            'seoTitle' => $this->seo_title,
            'seoDescription' => $this->seo_description,
            ...$this->purchaseGuidePayload(),
            'imageUrl' => CatalogMedia::publicSrc($this->image_url, ''),
            'productsCount' => $this->products_count ?? $this->visibleProducts()->count(),
            'updatedAt' => $this->updated_at?->toAtomString(),
        ];
    }

    /**
     * Category chips / browse nav — no purchase guide HTML.
     *
     * @return array<string, mixed>
     */
    public function toNavApi(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->storefrontTitle(),
            'titleEn' => $this->name_en,
            'description' => null,
            'seoTitle' => $this->seo_title,
            'seoDescription' => $this->seo_description,
            'imageUrl' => CatalogMedia::publicSrc($this->image_url, ''),
            'productsCount' => $this->products_count ?? $this->visibleProducts()->count(),
            'updatedAt' => $this->updated_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        $source = CatalogOrigin::group($this);

        return [
            'id' => $this->id,
            'shop2BigCategoryId' => $this->shop2_big_category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'title' => $this->title,
            'nameEn' => $this->name_en,
            'description' => CatalogAttr::multiline($this->description),
            'seoTitle' => $this->seo_title,
            'seoDescription' => $this->seo_description,
            ...$this->purchaseGuidePayload(),
            'imageUrl' => CatalogMedia::publicSrc($this->image_url, ''),
            'visible' => (bool) $this->getAttribute('visible'),
            'source' => $source,
            'synced' => $source !== CatalogOrigin::MANUAL,
            'sortOrder' => $this->sort_order,
            'productsCount' => $this->products_count ?? $this->products()->count(),
        ];
    }

    public function visibleProducts(): HasMany
    {
        return $this->products()->where('status', 'active')->where('visible', true);
    }

    /**
     * @return array{purchaseGuide: string|null, purchaseGuideItems: list<array<string, mixed>>}
     */
    public function purchaseGuidePayload(): array
    {
        $items = PurchaseGuide::decode($this->purchase_guide);

        return [
            'purchaseGuide' => PurchaseGuide::plaintext($items),
            'purchaseGuideItems' => $items,
        ];
    }
}

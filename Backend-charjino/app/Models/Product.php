<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontHomeCache;
use App\Modules\BuyGuide\BuyGuideHtml;
use App\Modules\Catalog\BuyerIdentity;
use App\Modules\Catalog\BuyerInputFields;
use App\Modules\Catalog\CatalogAttr;
use App\Modules\Catalog\CatalogMedia;
use App\Modules\Catalog\CatalogOrigin;
use App\Modules\Catalog\CatalogUrlLifecycle;
use App\Modules\Catalog\CodmCatalog;
use App\Modules\Catalog\ProductNormalizedKey;
use App\Modules\Catalog\PurchaseSchema;
use App\Modules\Catalog\StorefrontCopy;
use App\Modules\Codm\CodmRegionCheck;
use App\Modules\Pricing\PricingService;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsMedia;
use App\Modules\Supplier\FazerCards\SteamCoverResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    use Concerns\SearchIndexable;
    use FlushesStorefrontHomeCache;

    protected $fillable = [
        'catalog_group_id',
        'shop2_category_id',
        'shop2_big_category_id',
        'fazercards_category_id',
        'steam_gift_appid',
        'handle',
        'normalized_key',
        'normalized_kind',
        'normalized_region',
        'normalized_unique',
        'code',
        'title',
        'name_en',
        'publisher',
        'category_name',
        'subtitle',
        'seo_title',
        'seo_description',
        'description',
        'thumbnail',
        'image_url',
        'is_popular',
        'visible',
        'indexable',
        'excluded_reason',
        'fulfillment_type',
        'gift_proxy',
        'margin_percent',
        'platform',
        'region',
        'inputs',
        'purchase_schema',
        'requirements',
        'sort_order',
        'status',
        'synced_at',
        'unavailable_at',
        'substitute_product_id',
    ];

    protected function casts(): array
    {
        return [
            'shop2_category_id' => 'integer',
            'shop2_big_category_id' => 'integer',
            'steam_gift_appid' => 'integer',
            'is_popular' => 'boolean',
            'visible' => 'boolean',
            'indexable' => 'boolean',
            'gift_proxy' => 'boolean',
            'margin_percent' => 'decimal:4',
            'inputs' => 'array',
            'purchase_schema' => 'array',
            'requirements' => 'array',
            'sort_order' => 'integer',
            'comments_count' => 'integer',
            'synced_at' => 'datetime',
            'unavailable_at' => 'datetime',
            'substitute_product_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            ProductNormalizedKey::apply($product);
            $handle = (string) $product->handle;
            if (str_starts_with($handle, 'steam-gift-')) {
                $appId = (int) substr($handle, strlen('steam-gift-'));
                $product->steam_gift_appid = $appId > 0 ? $appId : null;
            }
            if ($product->exists && $product->isDirty(['visible', 'status', 'excluded_reason', 'handle', 'substitute_product_id'])) {
                app(CatalogUrlLifecycle::class)->syncFromProduct($product);
            }
        });
        static::created(function (Product $product): void {
            if ($product->getAttribute('visible') === null) {
                $product->setAttribute('visible', true);
            }
            if ($product->status === null || $product->status === '') {
                $product->status = 'active';
            }
            app(CatalogUrlLifecycle::class)->syncFromProduct($product);
        });
        static::updated(function (Product $product): void {
            if ($product->wasChanged(['visible', 'status', 'excluded_reason', 'handle'])) {
                app(CatalogUrlLifecycle::class)->auditProduct($product);
            }
        });
    }

    protected function searchIndexName(): string
    {
        return 'products';
    }

    public function catalogGroup(): BelongsTo
    {
        return $this->belongsTo(CatalogGroup::class);
    }

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class)->orderBy('sort_order')->orderBy('id');
    }

    public function searchTags(): BelongsToMany
    {
        return $this->belongsToMany(SearchTag::class, 'product_search_tag')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProductComment::class);
    }

    public function supplierIdentities(): HasMany
    {
        return $this->hasMany(ProductSupplierIdentity::class);
    }

    public function displayImage(): string
    {
        $src = CatalogMedia::catalogSrc($this->image_url ?: $this->thumbnail);
        if (FazerCardsMedia::isLegacySteamHeader($src)) {
            $cached = Cache::get(SteamCoverResolver::cacheKey(FazerCardsMedia::appIdFromUrl($src)));
            if (is_string($cached) && $cached !== '' && FazerCardsMedia::isHashedSteamHeader($cached)) {
                return $cached;
            }
        }
        if ($src !== '') {
            return $src;
        }

        $family = FazerCardsIds::familyOf($this->fulfillment_type, $this->fazercards_category_id);
        if ($family === FazerCardsIds::STEAM_GIFT) {
            $steam = FazerCardsMedia::steamHeader(FazerCardsIds::rawCategory($this->fazercards_category_id));
            if ($steam !== '') {
                return $steam;
            }
        }
        if ($family === FazerCardsIds::STEAM_TOPUP) {
            return '/image/catalog/steam.svg';
        }
        if (in_array($family, [FazerCardsIds::TELEGRAM_STARS, FazerCardsIds::TELEGRAM_PREMIUM], true)) {
            return '/image/catalog/telegram.svg';
        }
        if ($family === FazerCardsIds::GAMEKEY) {
            $requirements = is_array($this->requirements) ? $this->requirements : [];
            $steam = FazerCardsMedia::steamHeader($requirements['appid'] ?? '');
            if ($steam !== '') {
                return $steam;
            }
        }

        return CatalogMedia::publicSrc('');
    }

    /**
     * @return array<string, mixed>
     */
    public function storefrontTitle(): string
    {
        $group = $this->relationLoaded('catalogGroup') ? $this->catalogGroup : null;

        return StorefrontCopy::productTitle($this->title, $group?->storefrontTitle() ?: $this->category_name);
    }

    public function storefrontPublisher(): string
    {
        return StorefrontCopy::publisher($this->publisher);
    }

    public function storefrontPlatform(): ?string
    {
        return CatalogAttr::publicPlatform($this->platform);
    }

    public function storefrontRegion(): ?string
    {
        $own = CatalogAttr::publicRegion($this->region);
        if ($own !== null) {
            return $own;
        }
        if (! $this->relationLoaded('skus')) {
            return null;
        }
        $unique = [];
        foreach ($this->skus as $sku) {
            $region = CatalogAttr::publicRegion($sku->region);
            if ($region === null) {
                continue;
            }
            $unique[mb_strtolower($region)] = $region;
        }

        return count($unique) === 1 ? array_values($unique)[0] : null;
    }

    public function needsBuyerIdentity(): bool
    {
        if ($this->gift_proxy) {
            return false;
        }
        $schema = is_array($this->purchase_schema) ? $this->purchase_schema : [];
        if (array_key_exists('fields', $schema) && is_array($schema['fields'])) {
            return $schema['fields'] !== [];
        }
        $family = FazerCardsIds::familyOf($this->fulfillment_type, $this->fazercards_category_id);

        return ! in_array($family, [FazerCardsIds::GAMEKEY, FazerCardsIds::GIFTCARD, FazerCardsIds::MANUAL], true);
    }

    public function isStorefrontExcluded(): bool
    {
        if (filled($this->excluded_reason)) {
            return true;
        }
        $handle = strtolower(trim((string) $this->handle));
        if (str_starts_with($handle, 'manual-')) {
            return true;
        }
        $family = FazerCardsIds::familyOf($this->fulfillment_type, $this->fazercards_category_id);

        return in_array($family, [FazerCardsIds::MANUAL, FazerCardsIds::FUNPAY], true);
    }

    /**
     * همان شرط isStorefrontExcluded در کوئری فهرست و جزئیات.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeNotStorefrontExcluded(Builder $query): void
    {
        $reason = $query->qualifyColumn('excluded_reason');
        $handle = $query->qualifyColumn('handle');
        $type = $query->qualifyColumn('fulfillment_type');
        $remote = $query->qualifyColumn('fazercards_category_id');
        $families = FazerCardsIds::FAMILIES;
        $allowedFamilies = array_values(array_diff($families, [FazerCardsIds::MANUAL, FazerCardsIds::FUNPAY]));

        $query->where(function (Builder $inner) use ($reason) {
            $inner->whereNull($reason)->orWhere($reason, '');
        })
            ->where($handle, 'not like', 'manual-%')
            ->where(function (Builder $inner) use ($type, $remote, $families, $allowedFamilies) {
                $inner->whereIn($type, $allowedFamilies)
                    ->orWhere(function (Builder $inferred) use ($type, $remote, $families) {
                        $inferred->where(function (Builder $unknownType) use ($type, $families) {
                            $unknownType->whereNull($type)
                                ->orWhere($type, '')
                                ->orWhereNotIn($type, $families);
                        })->where(function (Builder $category) use ($remote) {
                            $category->whereNull($remote)
                                ->orWhere($remote, '')
                                ->orWhere(function (Builder $ok) use ($remote) {
                                    $ok->where($remote, 'not like', FazerCardsIds::MANUAL.':%')
                                        ->where($remote, 'not like', FazerCardsIds::FUNPAY.':%');
                                });
                        });
                    });
            });
    }

    public function storefrontMinPrice(): ?int
    {
        $min = null;
        foreach ($this->skus as $sku) {
            if ($sku->status !== 'active') {
                continue;
            }
            $price = $sku->sellPrice();
            if ($price <= 0) {
                continue;
            }
            $min = $min === null ? $price : min($min, $price);
        }

        return $min;
    }

    public function toApi(): array
    {
        $group = $this->relationLoaded('catalogGroup') ? $this->catalogGroup : null;
        $inputFields = BuyerInputFields::sanitize(is_array($this->inputs) ? $this->inputs : []);
        $groupTitle = $group?->storefrontTitle() ?: StorefrontCopy::groupTitle($this->category_name);

        return [
            'id' => $this->id,
            'categoryName' => $groupTitle !== '' ? $groupTitle : (string) $this->category_name,
            'code' => $this->code,
            'handle' => $this->handle,
            'inputs' => array_values(array_map(fn (array $field) => $field['name'], $inputFields)),
            'isPopular' => (bool) $this->is_popular,
            'indexable' => (bool) $this->indexable,
            'publisher' => $this->storefrontPublisher(),
            'subtitle' => $this->subtitle,
            'shortDescription' => $this->cardShortDescription(),
            'brand' => $this->storefrontPlatform(),
            'description' => $this->description,
            'descriptionHtml' => filled($this->description)
                ? BuyGuideHtml::fromMarkdown((string) $this->description)
                : null,
            'thumbnail' => $this->displayImage(),
            'title' => StorefrontCopy::productTitle($this->title, $groupTitle),
            'titleEn' => $this->name_en,
            'seoTitle' => $this->seo_title,
            'seoDescription' => $this->seo_description,
            'minPrice' => $this->storefrontMinPrice(),
            'platform' => $this->storefrontPlatform(),
            'region' => $this->storefrontRegion(),
            'regionRestriction' => (bool) (is_array($this->requirements) ? ($this->requirements['regionRestriction'] ?? false) : false),
            'regionAvailability' => is_array($this->requirements) ? ($this->requirements['regionAvailability'] ?? null) : null,
            'purchaseSchema' => $this->publicPurchaseSchema(),
            'regionNote' => is_array($this->purchase_schema) ? ($this->purchase_schema['regionNote'] ?? null) : null,
            'fulfillmentType' => $this->fulfillment_type,
            'giftProxy' => (bool) $this->gift_proxy,
            'validateMode' => BuyerIdentity::validateMode($this),
            'identityKind' => BuyerIdentity::kind($this),
            'searchTags' => $this->relationLoaded('searchTags')
                ? $this->searchTags->pluck('label')->values()->all()
                : [],
            'updatedAt' => $this->updated_at?->toAtomString(),
            'available' => $this->storefrontAvailable(),
            'temporarilyUnavailable' => app(CatalogUrlLifecycle::class)->isTemporaryUnavailable($this),
            'isOrderable' => $this->isOrderable(),
        ];
    }

    /**
     * Listing/home card payload — no description, schema, or search tags.
     *
     * @return array<string, mixed>
     */
    public function toCardApi(): array
    {
        $group = $this->relationLoaded('catalogGroup') ? $this->catalogGroup : null;
        $groupTitle = $group?->storefrontTitle() ?: StorefrontCopy::groupTitle($this->category_name);

        return [
            'id' => $this->id,
            'categoryName' => $groupTitle !== '' ? $groupTitle : (string) $this->category_name,
            'code' => $this->code,
            'handle' => $this->handle,
            'inputs' => [],
            'isPopular' => (bool) $this->is_popular,
            'indexable' => (bool) $this->indexable,
            'publisher' => $this->storefrontPublisher(),
            'subtitle' => $this->cardShortDescription(),
            'shortDescription' => $this->cardShortDescription(),
            'brand' => $this->storefrontPlatform(),
            'thumbnail' => $this->displayImage(),
            'title' => StorefrontCopy::productTitle($this->title, $groupTitle),
            'titleEn' => $this->name_en,
            'minPrice' => $this->storefrontMinPrice(),
            'platform' => $this->storefrontPlatform(),
            'region' => $this->storefrontRegion(),
            'regionRestriction' => (bool) (is_array($this->requirements) ? ($this->requirements['regionRestriction'] ?? false) : false),
            'fulfillmentType' => $this->fulfillment_type,
            'giftProxy' => (bool) $this->gift_proxy,
            'available' => $this->storefrontAvailable(),
            'isOrderable' => $this->isOrderable(),
            'updatedAt' => $this->updated_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function publicPurchaseSchema(): ?array
    {
        $schema = is_array($this->purchase_schema) ? $this->purchase_schema : null;
        if ($schema === null) {
            return null;
        }
        $family = FazerCardsIds::familyOf($this->fulfillment_type, $this->fazercards_category_id);
        $categoryId = FazerCardsIds::rawCategory($this->fazercards_category_id);
        $validation = is_array($schema['validation'] ?? null) ? $schema['validation'] : [];
        $schema['validation'] = [
            'mode' => PurchaseSchema::validationMode($family, [
                'validateCategoryId' => $categoryId !== '' ? $categoryId : ($validation['validateCategoryId'] ?? ''),
            ]),
            'validateCategoryId' => $family === FazerCardsIds::TOPUP
                ? ($categoryId !== '' ? $categoryId : ($validation['validateCategoryId'] ?? null))
                : ($validation['validateCategoryId'] ?? null),
        ];
        if (CodmCatalog::is($this->fazercards_category_id) && is_array($schema['fields'] ?? null)) {
            $activision = CodmRegionCheck::variantForFazerCategory($this->fazercards_category_id) !== null;
            $schema['fields'] = array_map(function ($field) use ($activision) {
                if (! is_array($field)) {
                    return $field;
                }
                $key = strtolower((string) ($field['key'] ?? $field['name'] ?? ''));
                if ($activision && in_array($key, ['user_id', 'uid'], true)) {
                    $field['pattern'] = '^\d{5,25}$';
                    if (! isset($field['help']) || trim((string) $field['help']) === '') {
                        $field['help'] = 'UID عددی حدود ۲۰ رقم، زیر نیک‌نیم در Player Profile بازی';
                    }
                } else {
                    $field['pattern'] = '^\d+$';
                }

                return $field;
            }, $schema['fields']);
        }

        return $schema;
    }

    public function cardShortDescription(): string
    {
        $raw = trim(preg_replace('/\s+/u', ' ', (string) $this->subtitle) ?? '');
        if ($raw === '' || preg_match('/لورم|ایپسوم|اپیسوم|lorem\s*ipsum/iu', $raw) === 1) {
            return '';
        }

        return $raw;
    }

    public function storefrontAvailable(): bool
    {
        return $this->status === 'active' && (bool) $this->getAttribute('visible') && ! $this->isStorefrontExcluded();
    }

    public function isOrderable(): bool
    {
        return $this->storefrontAvailable() && ! app(CatalogUrlLifecycle::class)->isTemporaryUnavailable($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id,
            'catalogGroupId' => $this->catalog_group_id,
            'shop2CategoryId' => $this->shop2_category_id,
            'shop2BigCategoryId' => $this->shop2_big_category_id,
            'fazercardsCategoryId' => $this->fazercards_category_id,
            'handle' => $this->handle,
            'code' => $this->code,
            'title' => $this->title,
            'nameEn' => $this->name_en,
            'publisher' => $this->publisher,
            'categoryName' => $this->category_name,
            'subtitle' => $this->subtitle,
            'seoTitle' => $this->seo_title,
            'seoDescription' => $this->seo_description,
            'description' => $this->description,
            'thumbnail' => $this->displayImage(),
            'imageUrl' => $this->displayImage(),
            'displayImage' => $this->displayImage(),
            'isPopular' => (bool) $this->is_popular,
            'visible' => (bool) $this->getAttribute('visible'),
            'excludedReason' => $this->excluded_reason,
            'purchaseSchema' => is_array($this->purchase_schema) ? $this->purchase_schema : null,
            'syncedAt' => $this->synced_at?->toIso8601String(),
            'fulfillmentType' => $this->fulfillment_type,
            'giftProxy' => (bool) $this->gift_proxy,
            'marginPercent' => PricingService::formatMargin($this->margin_percent),
            'globalMarginPercent' => app(PricingService::class)->marginPercent(),
            'platform' => CatalogAttr::publicPlatform($this->platform),
            'region' => CatalogAttr::publicRegion($this->region),
            'status' => $this->status,
            'substituteProductId' => $this->substitute_product_id,
            'unavailableAt' => $this->unavailable_at?->toIso8601String(),
            'sortOrder' => $this->sort_order,
            'source' => CatalogOrigin::product($this),
            'searchTags' => $this->relationLoaded('searchTags')
                ? $this->searchTags->pluck('label')->values()->all()
                : [],
            'skusCount' => $this->skus_count ?? $this->skus()->count(),
            'skus' => $this->relationLoaded('skus')
                ? $this->skus->map(function (Sku $sku) {
                    $sku->setRelation('product', $this);

                    return $sku->toAdmin();
                })->values()->all()
                : [],
        ];
    }
}

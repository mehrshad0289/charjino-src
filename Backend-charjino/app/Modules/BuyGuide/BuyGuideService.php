<?php

namespace App\Modules\BuyGuide;

use App\Exceptions\ApiException;
use App\Models\BuyGuide;
use App\Models\BuyGuideSection;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Modules\Catalog\GameKeyPlatform;
use App\Modules\Catalog\GiftCardBrand;
use App\Modules\Supplier\FazerCards\FazerCardsIds;

final class BuyGuideService
{
    public function __construct(
        private readonly BuyGuidePresenter $presenter,
        private readonly BuyGuideResolver $resolver,
        private readonly StorefrontRevalidator $revalidator,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function coverage(): array
    {
        $guides = BuyGuide::query()->get()->groupBy(fn (BuyGuide $guide) => $guide->scope_key);
        $overrides = BuyGuide::query()
            ->where('scope_type', BuyGuideScope::PRODUCT)
            ->get()
            ->groupBy(fn (BuyGuide $guide) => $guide->category.'|'.$guide->subcategory);

        $tree = [];
        foreach (BuyGuideScope::CATEGORIES as $category) {
            $children = $this->coverageChildren($category);
            $catGuide = $guides->get(BuyGuideScope::key(BuyGuideScope::CATEGORY, $category))?->first();
            $productCount = array_sum(array_column($children, 'productCount'));
            if ($children === []) {
                $productCount = $this->countProducts($category, '');
            }
            $tree[] = [
                'category' => $category,
                'label' => BuyGuideScope::label($category),
                'productCount' => $productCount,
                'status' => $this->statusOf($catGuide),
                'lastReviewedAt' => $catGuide?->last_reviewed_at?->toDateString(),
                'hasOwnIntro' => filled($catGuide?->intro),
                'overrideCount' => $overrides->get($category.'|')?->count() ?? 0,
                'guideId' => $catGuide?->id,
                'duplicateRisk' => false,
                'children' => array_map(function (array $child) use ($guides, $overrides, $category) {
                    $key = BuyGuideScope::key(BuyGuideScope::SUBCATEGORY, $category, $child['subcategory']);
                    $guide = $guides->get($key)?->first();
                    $status = $this->statusOf($guide);

                    return $child + [
                        'status' => $status,
                        'lastReviewedAt' => $guide?->last_reviewed_at?->toDateString(),
                        'hasOwnIntro' => filled($guide?->intro),
                        'overrideCount' => $overrides->get($category.'|'.$child['subcategory'])?->count() ?? 0,
                        'guideId' => $guide?->id,
                        'duplicateRisk' => $status !== 'none' && ! filled($guide?->intro),
                    ];
                }, $children),
            ];
        }

        return $tree;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $category, ?string $subcategory, ?int $productId): array
    {
        $query = BuyGuide::query()->with('sections')->orderBy('category')->orderBy('subcategory');
        if ($category) {
            $query->where('category', $category);
        }
        if ($subcategory !== null && $subcategory !== '') {
            $query->where('subcategory', BuyGuideScope::normSub($subcategory));
        }
        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query->get()->map(fn (BuyGuide $guide) => $this->presenter->admin($guide))->all();
    }

    public function show(BuyGuide $guide): array
    {
        return $this->presenter->admin($guide);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, ?string $userId): BuyGuide
    {
        $normalized = $this->normalizeScope($input);
        $key = BuyGuideScope::key(
            $normalized['scope_type'],
            $normalized['category'],
            $normalized['subcategory'],
            $normalized['product_id'],
        );
        if (BuyGuide::query()->where('scope_key', $key)->exists()) {
            throw new ApiException('برای این سطح قبلاً راهنما ثبت شده است.', 409);
        }
        $clean = BuyGuideContentRules::assert($input + $normalized);
        $guide = new BuyGuide;
        $this->fillGuide($guide, $clean, $normalized, $key, $userId);
        $guide->save();
        $this->syncSections($guide, is_array($clean['sections'] ?? null) ? $clean['sections'] : []);
        $this->revalidator->schedule($guide->fresh(['product']) ?? $guide);

        return $guide->fresh(['sections', 'product']) ?? $guide;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(BuyGuide $guide, array $input, ?string $userId): BuyGuide
    {
        $normalized = $this->normalizeScope($input + [
            'scopeType' => $guide->scope_type,
            'category' => $guide->category,
            'subcategory' => $guide->subcategory,
            'productId' => $guide->product_id,
        ]);
        $clean = BuyGuideContentRules::assert($input + $normalized);
        $key = BuyGuideScope::key(
            $normalized['scope_type'],
            $normalized['category'],
            $normalized['subcategory'],
            $normalized['product_id'],
        );
        $this->fillGuide($guide, $clean, $normalized, $key, $userId);
        $guide->save();
        if (array_key_exists('sections', $input)) {
            $this->syncSections($guide, is_array($clean['sections'] ?? null) ? $clean['sections'] : []);
        }
        $this->revalidator->schedule($guide->fresh(['product']) ?? $guide);

        return $guide->fresh(['sections', 'product']) ?? $guide;
    }

    public function delete(BuyGuide $guide): void
    {
        $this->revalidator->schedule($guide);
        $guide->delete();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function duplicate(BuyGuide $guide, array $input, ?string $userId): BuyGuide
    {
        $guide->loadMissing('sections');
        $payload = [
            'scopeType' => $input['scopeType'] ?? $input['scope_type'] ?? null,
            'category' => $input['category'] ?? $guide->category,
            'subcategory' => $input['subcategory'] ?? '',
            'productId' => $input['productId'] ?? $input['product_id'] ?? null,
            'title' => $guide->title,
            'summary' => $guide->summary,
            'intro' => $guide->intro,
            'lastReviewedAt' => $guide->last_reviewed_at?->toDateString(),
            'seoTitle' => $guide->seo_title,
            'seoDescription' => $guide->seo_description,
            'status' => 'draft',
            'sections' => $guide->sections->map(fn (BuyGuideSection $section) => [
                'type' => $section->type,
                'inherit' => (bool) $section->inherit,
                'data' => is_array($section->data) ? $section->data : [],
            ])->all(),
        ];
        $types = is_array($input['sections'] ?? null) ? $input['sections'] : null;
        if (is_array($types)) {
            $wanted = array_flip($types);
            $payload['sections'] = array_values(array_filter(
                $payload['sections'],
                fn ($section) => isset($wanted[$section['type'] ?? '']),
            ));
        }

        return $this->create($payload, $userId);
    }

    public function publish(BuyGuide $guide, ?string $userId): BuyGuide
    {
        BuyGuideContentRules::assert($this->presenter->admin($guide));
        $guide->status = 'published';
        $guide->published_at = $guide->published_at ?? now();
        $guide->updated_by = $userId ?: $guide->updated_by;
        $guide->save();
        $this->revalidator->schedule($guide->fresh(['product']) ?? $guide);

        return $guide;
    }

    public function unpublish(BuyGuide $guide, ?string $userId): BuyGuide
    {
        $guide->status = 'draft';
        $guide->updated_by = $userId ?: $guide->updated_by;
        $guide->save();
        $this->revalidator->schedule($guide->fresh(['product']) ?? $guide);

        return $guide;
    }

    public function resolvePreview(int $productId): array
    {
        $product = Product::query()->with('catalogGroup')->find($productId);
        if (! $product) {
            throw new ApiException('محصول پیدا نشد.', 404);
        }
        $resolved = $this->resolver->forProduct($product, true);
        if ($resolved === null) {
            throw new ApiException('برای این محصول راهنمایی وجود ندارد.', 404);
        }

        return $resolved;
    }

    /**
     * @return list<array{code: string, label: string, compat: string, product_handle: string}>
     */
    public function regionOptions(string $category, string $subcategory): array
    {
        $sub = BuyGuideScope::normSub($subcategory);
        $query = Product::query()->where('status', 'active')->where('visible', true);
        $family = match ($category) {
            BuyGuideScope::GIFT_CARD => FazerCardsIds::GIFTCARD,
            BuyGuideScope::GAME_KEYS => FazerCardsIds::GAMEKEY,
            BuyGuideScope::GAME_TOPUP => FazerCardsIds::TOPUP,
            BuyGuideScope::STEAM_GIFTS => FazerCardsIds::STEAM_GIFT,
            BuyGuideScope::STEAM_WALLET => FazerCardsIds::STEAM_TOPUP,
            default => null,
        };
        if ($family) {
            $query->where('fulfillment_type', $family);
        }
        if ($category === BuyGuideScope::GIFT_CARD && $sub !== '') {
            $query->where('platform', $sub);
        }
        if ($category === BuyGuideScope::GAME_KEYS && $sub !== '') {
            $query->where('platform', $sub);
        }
        if ($category === BuyGuideScope::GAME_TOPUP && $sub !== '') {
            $query->whereHas('catalogGroup', fn ($inner) => $inner->where('slug', $sub));
        }

        return $query->orderBy('region')->orderBy('id')->limit(200)->get()
            ->map(function (Product $product) {
                $region = (string) ($product->region ?: '—');

                return [
                    'code' => $region,
                    'label' => $region,
                    'compat' => 'حساب با کشور/منطقه‌ی «'.$region.'»',
                    'product_handle' => (string) $product->handle,
                    'product_id' => $product->id,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{scope_type: string, category: string, subcategory: string, product_id: int|null}
     */
    private function normalizeScope(array $input): array
    {
        $type = (string) ($input['scopeType'] ?? $input['scope_type'] ?? '');
        $category = (string) ($input['category'] ?? '');
        $subcategory = BuyGuideScope::normSub((string) ($input['subcategory'] ?? ''));
        $productId = isset($input['productId']) ? (int) $input['productId'] : (isset($input['product_id']) ? (int) $input['product_id'] : null);
        if (! in_array($type, [BuyGuideScope::CATEGORY, BuyGuideScope::SUBCATEGORY, BuyGuideScope::PRODUCT], true)) {
            throw new ApiException('سطح راهنما نامعتبر است.', 422);
        }
        if ($type === BuyGuideScope::PRODUCT) {
            if (! $productId) {
                throw new ApiException('محصول لازم است.', 422);
            }
            $product = Product::query()->with('catalogGroup')->find($productId);
            if (! $product) {
                throw new ApiException('محصول پیدا نشد.', 404);
            }
            $mapped = BuyGuideScope::forProduct($product);
            if ($mapped === null) {
                throw new ApiException('این محصول راهنمای خرید ندارد.', 422);
            }
            $category = $mapped['category'];
            $subcategory = $mapped['subcategory'];
        }
        if (! in_array($category, BuyGuideScope::CATEGORIES, true)) {
            throw new ApiException('دستهٔ راهنما نامعتبر است.', 422);
        }
        if ($type === BuyGuideScope::SUBCATEGORY && $subcategory === '') {
            throw new ApiException('زیردسته لازم است.', 422);
        }
        if ($type === BuyGuideScope::CATEGORY) {
            $subcategory = '';
            $productId = null;
        }
        if ($type === BuyGuideScope::SUBCATEGORY) {
            $productId = null;
        }

        return [
            'scope_type' => $type,
            'category' => $category,
            'subcategory' => $subcategory,
            'product_id' => $productId,
        ];
    }

    /**
     * @param  array<string, mixed>  $clean
     * @param  array{scope_type: string, category: string, subcategory: string, product_id: int|null}  $scope
     */
    private function fillGuide(BuyGuide $guide, array $clean, array $scope, string $key, ?string $userId): void
    {
        $guide->scope_key = $key;
        $guide->scope_type = $scope['scope_type'];
        $guide->category = $scope['category'];
        $guide->subcategory = $scope['subcategory'];
        $guide->product_id = $scope['product_id'];
        $guide->title = $clean['title'] !== '' ? $clean['title'] : null;
        $guide->summary = $clean['summary'] !== '' ? $clean['summary'] : null;
        $guide->intro = $clean['intro'] !== '' ? $clean['intro'] : null;
        $guide->seo_title = $clean['seo_title'] !== '' ? $clean['seo_title'] : null;
        $guide->seo_description = $clean['seo_description'] !== '' ? $clean['seo_description'] : null;
        if (array_key_exists('lastReviewedAt', $clean) || array_key_exists('last_reviewed_at', $clean)) {
            $raw = $clean['lastReviewedAt'] ?? $clean['last_reviewed_at'] ?? null;
            $guide->last_reviewed_at = $raw ?: null;
        }
        $status = (string) ($clean['status'] ?? $guide->status ?? 'draft');
        $guide->status = in_array($status, ['draft', 'published'], true) ? $status : 'draft';
        if ($guide->status === 'published' && ! $guide->published_at) {
            $guide->published_at = now();
        }
        $guide->updated_by = $userId;
    }

    /**
     * @param  list<mixed>  $sections
     */
    private function syncSections(BuyGuide $guide, array $sections): void
    {
        $guide->sections()->delete();
        $order = 0;
        foreach ($sections as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = (string) ($row['type'] ?? '');
            if (! in_array($type, BuyGuideScope::SECTION_TYPES, true)) {
                continue;
            }
            $data = is_array($row['data'] ?? null) ? $row['data'] : $this->dataFromRow($type, $row);
            BuyGuideSection::query()->create([
                'buy_guide_id' => $guide->id,
                'type' => $type,
                'inherit' => array_key_exists('inherit', $row)
                    ? (bool) $row['inherit']
                    : $guide->scope_type !== BuyGuideScope::CATEGORY,
                'sort_order' => $order++,
                'data' => $data,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function dataFromRow(string $type, array $row): array
    {
        unset($row['type'], $row['inherit'], $row['source']);

        return match ($type) {
            'before_buy' => ['notes' => $row['notes'] ?? []],
            'fields' => ['items' => $row['items'] ?? []],
            'purchase_steps' => ['steps' => $row['steps'] ?? []],
            'redeem' => ['tabs' => $row['tabs'] ?? []],
            'regions' => ['rows' => $row['rows'] ?? []],
            default => ['items' => $row['items'] ?? []],
        };
    }

    /**
     * @return list<array{subcategory: string, label: string, productCount: int}>
     */
    private function coverageChildren(string $category): array
    {
        if ($category === BuyGuideScope::GIFT_CARD) {
            return Product::query()
                ->where('fulfillment_type', FazerCardsIds::GIFTCARD)
                ->whereNotNull('platform')
                ->where('platform', '!=', '')
                ->selectRaw('platform as subcategory, count(*) as product_count')
                ->groupBy('platform')
                ->orderBy('platform')
                ->get()
                ->map(fn ($row) => [
                    'subcategory' => BuyGuideScope::normSub((string) $row->subcategory),
                    'label' => GiftCardBrand::label(BuyGuideScope::normSub((string) $row->subcategory)),
                    'productCount' => (int) $row->product_count,
                ])
                ->all();
        }
        if ($category === BuyGuideScope::GAME_KEYS) {
            return Product::query()
                ->where('fulfillment_type', FazerCardsIds::GAMEKEY)
                ->whereNotNull('platform')
                ->where('platform', '!=', '')
                ->selectRaw('platform as subcategory, count(*) as product_count')
                ->groupBy('platform')
                ->orderBy('platform')
                ->get()
                ->map(function ($row) {
                    $sub = GameKeyPlatform::canonical((string) $row->subcategory)
                        ?: BuyGuideScope::normSub((string) $row->subcategory);

                    return [
                        'subcategory' => $sub,
                        'label' => GameKeyPlatform::label($sub),
                        'productCount' => (int) $row->product_count,
                    ];
                })
                ->all();
        }
        if ($category === BuyGuideScope::GAME_TOPUP) {
            return CatalogGroup::query()
                ->whereHas('products', fn ($query) => $query->where('fulfillment_type', FazerCardsIds::TOPUP))
                ->withCount(['products as product_count' => fn ($query) => $query->where('fulfillment_type', FazerCardsIds::TOPUP)])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (CatalogGroup $group) => [
                    'subcategory' => (string) $group->slug,
                    'label' => $group->storefrontTitle(),
                    'productCount' => (int) $group->product_count,
                ])
                ->all();
        }
        if ($category === BuyGuideScope::TELEGRAM) {
            return [
                [
                    'subcategory' => 'stars',
                    'label' => 'ستاره تلگرام',
                    'productCount' => $this->countProducts($category, 'stars'),
                ],
                [
                    'subcategory' => 'premium',
                    'label' => 'پرمیوم تلگرام',
                    'productCount' => $this->countProducts($category, 'premium'),
                ],
            ];
        }

        return [];
    }

    private function countProducts(string $category, string $subcategory): int
    {
        $query = Product::query();
        $family = match ($category) {
            BuyGuideScope::GIFT_CARD => FazerCardsIds::GIFTCARD,
            BuyGuideScope::GAME_KEYS => FazerCardsIds::GAMEKEY,
            BuyGuideScope::GAME_TOPUP => FazerCardsIds::TOPUP,
            BuyGuideScope::TELEGRAM => null,
            BuyGuideScope::STEAM_WALLET => FazerCardsIds::STEAM_TOPUP,
            BuyGuideScope::STEAM_GIFTS => FazerCardsIds::STEAM_GIFT,
            default => null,
        };
        if ($category === BuyGuideScope::TELEGRAM) {
            $query->where('fulfillment_type', $subcategory === 'premium' ? FazerCardsIds::TELEGRAM_PREMIUM : FazerCardsIds::TELEGRAM_STARS);
        } elseif ($family) {
            $query->where('fulfillment_type', $family);
        }
        if ($subcategory !== '' && $category === BuyGuideScope::GIFT_CARD) {
            $query->where('platform', $subcategory);
        }
        if ($subcategory !== '' && $category === BuyGuideScope::GAME_KEYS) {
            $query->where('platform', $subcategory);
        }
        if ($subcategory !== '' && $category === BuyGuideScope::GAME_TOPUP) {
            $query->whereHas('catalogGroup', fn ($inner) => $inner->where('slug', $subcategory));
        }

        return $query->count();
    }

    private function statusOf(?BuyGuide $guide): string
    {
        if (! $guide) {
            return 'none';
        }

        return $guide->status === 'published' ? 'published' : 'draft';
    }
}

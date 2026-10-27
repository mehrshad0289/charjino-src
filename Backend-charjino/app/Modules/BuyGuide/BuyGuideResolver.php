<?php

namespace App\Modules\BuyGuide;

use App\Models\BuyGuide;
use App\Models\BuyGuideSection;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

final class BuyGuideResolver
{
    public function __construct(private readonly BuyGuidePresenter $presenter) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forProduct(Product $product, bool $includeDraft = false): ?array
    {
        $scope = BuyGuideScope::forProduct($product);
        if ($scope === null) {
            return null;
        }
        if ($includeDraft) {
            return $this->build($product, $scope, true);
        }
        $version = $this->versionKey($product, $scope);
        $cacheKey = 'buy-guide:'.$product->id.':'.$version;

        return Cache::remember($cacheKey, 600, fn () => $this->build($product, $scope, false));
    }

    /**
     * @param  array{category: string, subcategory: string}  $scope
     * @return array<string, mixed>|null
     */
    private function build(Product $product, array $scope, bool $includeDraft): ?array
    {
        $layers = $this->layers($product, $scope, $includeDraft);
        $title = $this->firstFilled($layers, 'title');
        $summary = $this->firstFilled($layers, 'summary');
        $intro = $this->firstFilled($layers, 'intro');
        $seoTitle = $this->firstFilled($layers, 'seo_title');
        $seoDescription = $this->firstFilled($layers, 'seo_description');
        $reviewed = $this->firstDate($layers);
        $sections = [];
        foreach (BuyGuideScope::SECTION_TYPES as $type) {
            $picked = $this->sectionFromLayers($layers, $type);
            if ($picked === null) {
                continue;
            }
            $row = $this->presenter->section($picked['section'], $product);
            $row['source'] = $picked['source'];
            unset($row['inherit']);
            $sections[] = $row;
        }
        $legacy = $this->presenter->legacyFromGroup($product->catalogGroup?->purchase_guide);
        if ($title === null && $summary === null && $intro === null && $sections === [] && $legacy === []) {
            return null;
        }

        return BuyGuidePublicCopy::presentPayload([
            'title' => $title ?: 'راهنمای خرید',
            'summary' => $summary,
            'introHtml' => $intro,
            'lastReviewedAt' => $reviewed,
            'scope' => [
                'category' => $scope['category'],
                'subcategory' => $scope['subcategory'] !== '' ? $scope['subcategory'] : null,
                'productId' => $product->id,
            ],
            'seo' => array_filter([
                'title' => $seoTitle,
                'description' => $seoDescription,
            ]),
            'sections' => $sections,
            'legacyItems' => $sections === [] ? $legacy : [],
        ]);
    }

    /**
     * @param  array{category: string, subcategory: string}  $scope
     * @return list<array{source: string, guide: BuyGuide}>
     */
    private function layers(Product $product, array $scope, bool $includeDraft): array
    {
        $query = BuyGuide::query()->with('sections')->where('category', $scope['category']);
        if (! $includeDraft) {
            $query->where('status', 'published');
        }
        $guides = $query->get();
        $out = [];
        $productGuide = $guides->first(fn (BuyGuide $guide) => $guide->scope_type === BuyGuideScope::PRODUCT && (int) $guide->product_id === (int) $product->id);
        $subGuide = $scope['subcategory'] !== ''
            ? $guides->first(fn (BuyGuide $guide) => $guide->scope_type === BuyGuideScope::SUBCATEGORY && $guide->subcategory === $scope['subcategory'])
            : null;
        $catGuide = $guides->first(fn (BuyGuide $guide) => $guide->scope_type === BuyGuideScope::CATEGORY);
        foreach ([['product', $productGuide], ['subcategory', $subGuide], ['category', $catGuide]] as [$source, $guide]) {
            if ($guide instanceof BuyGuide) {
                $out[] = ['source' => $source, 'guide' => $guide];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{source: string, guide: BuyGuide}>  $layers
     * @return array{source: string, section: BuyGuideSection}|null
     */
    private function sectionFromLayers(array $layers, string $type): ?array
    {
        foreach ($layers as $layer) {
            $section = $layer['guide']->sections->first(fn (BuyGuideSection $item) => $item->type === $type);
            if (! $section instanceof BuyGuideSection) {
                continue;
            }
            if ($layer['source'] !== 'category' && $section->inherit) {
                continue;
            }
            $data = is_array($section->data) ? $section->data : [];
            if ($this->isEmptyData($type, $data) && $layer['source'] !== 'category') {
                continue;
            }

            return ['source' => $layer['source'], 'section' => $section];
        }

        return null;
    }

    /**
     * @param  list<array{source: string, guide: BuyGuide}>  $layers
     */
    private function firstFilled(array $layers, string $field): ?string
    {
        foreach ($layers as $layer) {
            $value = trim((string) $layer['guide']->{$field});
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  list<array{source: string, guide: BuyGuide}>  $layers
     */
    private function firstDate(array $layers): ?string
    {
        foreach ($layers as $layer) {
            $date = $layer['guide']->last_reviewed_at?->toDateString();
            if (is_string($date) && $date !== '') {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param  array{category: string, subcategory: string}  $scope
     */
    private function versionKey(Product $product, array $scope): string
    {
        $stamps = BuyGuide::query()
            ->where('category', $scope['category'])
            ->where('status', 'published')
            ->where(function ($query) use ($product, $scope): void {
                $query->where('scope_type', BuyGuideScope::CATEGORY)
                    ->orWhere(function ($inner) use ($scope): void {
                        $inner->where('scope_type', BuyGuideScope::SUBCATEGORY)
                            ->where('subcategory', $scope['subcategory']);
                    })
                    ->orWhere(function ($inner) use ($product): void {
                        $inner->where('scope_type', BuyGuideScope::PRODUCT)
                            ->where('product_id', $product->id);
                    });
            })
            ->get(['id', 'updated_at'])
            ->map(fn (BuyGuide $guide) => $guide->id.':'.optional($guide->updated_at)->timestamp)
            ->implode('|');

        return $stamps !== '' ? sha1($stamps) : 'none';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyData(string $type, array $data): bool
    {
        return match ($type) {
            'before_buy' => ($data['notes'] ?? []) === [],
            'fields' => ($data['items'] ?? []) === [],
            'purchase_steps' => ($data['steps'] ?? []) === [],
            'redeem' => ($data['tabs'] ?? []) === [],
            'regions' => ($data['rows'] ?? []) === [],
            'errors', 'faq', 'videos', 'sources' => ($data['items'] ?? []) === [],
            default => $data === [],
        };
    }
}

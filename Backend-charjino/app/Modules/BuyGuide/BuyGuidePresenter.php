<?php

namespace App\Modules\BuyGuide;

use App\Models\BuyGuide;
use App\Models\BuyGuideMedia;
use App\Models\BuyGuideSection;
use App\Models\Product;
use App\Modules\Catalog\PurchaseGuide;

final class BuyGuidePresenter
{
    /**
     * @param  list<BuyGuideSection>|iterable<BuyGuideSection>  $sections
     * @return list<array<string, mixed>>
     */
    public function sections(iterable $sections, ?Product $current = null): array
    {
        $rows = [];
        foreach ($sections as $section) {
            if (! $section instanceof BuyGuideSection) {
                continue;
            }
            $rows[] = $this->section($section, $current);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function section(BuyGuideSection $section, ?Product $current = null): array
    {
        $data = is_array($section->data) ? $section->data : [];
        $payload = $this->hydrate($data, $current);

        return array_merge($payload, [
            'type' => $section->type,
            'inherit' => (bool) $section->inherit,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function admin(BuyGuide $guide): array
    {
        $guide->loadMissing(['sections', 'product:id,handle,title']);

        return [
            'id' => $guide->id,
            'scopeType' => $guide->scope_type,
            'scopeKey' => $guide->scope_key,
            'category' => $guide->category,
            'subcategory' => $guide->subcategory !== '' ? $guide->subcategory : null,
            'productId' => $guide->product_id,
            'productHandle' => $guide->product?->handle,
            'title' => $guide->title,
            'summary' => $guide->summary,
            'intro' => $guide->intro,
            'status' => $guide->status,
            'lastReviewedAt' => $guide->last_reviewed_at?->toDateString(),
            'seoTitle' => $guide->seo_title,
            'seoDescription' => $guide->seo_description,
            'publishedAt' => $guide->published_at?->toAtomString(),
            'updatedAt' => $guide->updated_at?->toAtomString(),
            'sections' => $this->sections($guide->sections),
        ];
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    public function publicPayload(array $resolved, Product $product, ?array $legacyItems): array
    {
        if ($resolved === [] && ($legacyItems === null || $legacyItems === [])) {
            return [];
        }
        $payload = $resolved;
        if (($payload['sections'] ?? []) === [] && is_array($legacyItems) && $legacyItems !== []) {
            $payload['legacyItems'] = $legacyItems;
            $payload['title'] = $payload['title'] ?? 'راهنمای خرید';
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legacyFromGroup(?string $raw): array
    {
        return PurchaseGuide::decode($raw);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrate(array $data, ?Product $current): array
    {
        $ids = [];
        array_walk_recursive($data, function ($value, $key) use (&$ids): void {
            if (in_array($key, ['media_id', 'thumbnail_media_id', 'video_media_id'], true) && is_numeric($value)) {
                $ids[] = (int) $value;
            }
        });
        $media = $ids === []
            ? collect()
            : BuyGuideMedia::query()->whereIn('id', array_unique($ids))->get()->keyBy('id');

        return $this->swapMedia($data, $media, $current);
    }

    /**
     * @param  mixed  $node
     * @param  \Illuminate\Support\Collection<int, BuyGuideMedia>  $media
     * @return mixed
     */
    private function swapMedia(mixed $node, $media, ?Product $current): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        $out = [];
        foreach ($node as $key => $value) {
            if ($key === 'media_id' && is_numeric($value)) {
                $out['media'] = $media->get((int) $value)?->toApi();
                continue;
            }
            if ($key === 'thumbnail_media_id' && is_numeric($value)) {
                $out['thumbnail'] = $media->get((int) $value)?->toApi();
                continue;
            }
            if ($key === 'video_media_id' && is_numeric($value)) {
                $out['video'] = $media->get((int) $value)?->toApi();
                continue;
            }
            $out[$key] = $this->swapMedia($value, $media, $current);
        }
        if (isset($out['product_handle'], $current) && is_string($out['product_handle'])) {
            $out['current'] = mb_strtolower($out['product_handle']) === mb_strtolower((string) $current->handle);
        }

        return $out;
    }
}

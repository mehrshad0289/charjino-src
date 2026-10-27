<?php

namespace App\Services\Torob;

use App\Models\Product;
use Illuminate\Support\Collection;

class TorobMappingAudit
{
    /**
     * @var list<string>
     */
    private const SCOPES = ['gift-card', 'game-topup', 'game-keys'];

    public function __construct(
        private TorobProductQueryService $query,
        private TorobSplitPolicy $policy,
        private TorobFeedCatalogCopy $copy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $simulate = $this->policy->withSimulateAll();
        $enabled = array_flip($this->policy->enabledProductIds());
        $blocked = array_flip(array_map('strval', config('torob_split_copy.blocked_group_slugs', [])));

        $byScope = [];
        foreach (self::SCOPES as $slug) {
            $byScope[$slug] = [
                'slug' => $slug,
                'products_examined' => 0,
                'enabled_skipped' => 0,
                'eligible_split' => 0,
                'exempt' => 0,
                'exemptions_by_reason' => [],
                'gap_counts' => [],
                'exempt_products' => [],
                'eligible_product_ids' => [],
            ];
        }

        $this->query->eachListableCursor(100, function (Collection $products) use (
            $simulate,
            $enabled,
            $blocked,
            &$byScope,
        ): void {
            foreach ($products as $product) {
                if (! $product instanceof Product) {
                    continue;
                }
                $slug = trim((string) ($product->catalogGroup?->slug ?? ''));
                if ($slug === '' || ! isset($byScope[$slug])) {
                    continue;
                }
                if (isset($blocked[$slug])) {
                    continue;
                }
                $handle = (string) $product->handle;
                if (str_contains(mb_strtolower($handle), 'game-pass')) {
                    continue;
                }
                $row = &$byScope[$slug];
                $row['products_examined']++;
                if (isset($enabled[(int) $product->id])) {
                    $row['enabled_skipped']++;
                    unset($row);

                    continue;
                }
                $decision = $simulate->decide($product);
                if ($decision->split) {
                    $row['eligible_split']++;
                    $row['eligible_product_ids'][] = (int) $product->id;

                    unset($row);

                    continue;
                }
                $reason = $decision->reason ?? 'unknown';
                if ($reason === TorobSplitDecision::REASON_NOT_ENABLED) {
                    unset($row);

                    continue;
                }
                $row['exempt']++;
                $row['exemptions_by_reason'][$reason] = ($row['exemptions_by_reason'][$reason] ?? 0) + 1;
                $detail = $this->exemptionDetail($product, $reason);
                $gapKey = $detail['primary_gap'] ?? $reason;
                $row['gap_counts'][$gapKey] = ($row['gap_counts'][$gapKey] ?? 0) + 1;
                if (count($row['exempt_products']) < 500) {
                    $row['exempt_products'][] = [
                        'id' => (int) $product->id,
                        'handle' => $handle,
                        'title' => (string) $product->title,
                        'reason' => $reason,
                        'primary_gap' => $gapKey,
                        'gap_breakdown' => $detail['gaps'],
                        'listed_skus' => count($simulate->listedSkus($product)),
                        'brand_needle' => $this->copy->brandNeedle($product),
                    ];
                }
                unset($row);
            }
        }, true);

        foreach ($byScope as $slug => &$scope) {
            sort($scope['eligible_product_ids']);
            arsort($scope['gap_counts']);
            arsort($scope['exemptions_by_reason']);
        }
        unset($scope);

        return [
            'generated_at' => now()->timezone('Asia/Tehran')->toIso8601String(),
            'enabled_product_count' => count($enabled),
            'scopes' => $byScope,
            'game_keys_split_policy' => $this->gameKeysPolicyNote(),
            'service_topup_split_policy' => $this->serviceTopupPolicyNote(),
        ];
    }

    /**
     * @return array{primary_gap: string, gaps: list<string>}
     */
    private function exemptionDetail(Product $product, string $reason): array
    {
        $gaps = [];
        if ($reason === TorobSplitDecision::REASON_UNKNOWN_MAPPING) {
            foreach ($this->policy->listedSkus($product) as $sku) {
                $gap = $this->copy->mappingGap($product, $sku);
                if ($gap !== '') {
                    $gaps[$gap] = ($gaps[$gap] ?? 0) + 1;
                }
            }
        }
        $primary = $reason;
        if ($gaps !== []) {
            arsort($gaps);
            $primary = (string) array_key_first($gaps);
        }

        return [
            'primary_gap' => $primary,
            'gaps' => array_keys($gaps),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gameKeysPolicyNote(): array
    {
        return [
            'split_appropriate' => false,
            'reason' => 'SKUهای کلید بازی معمولاً نسخه/پلتفرم هستند نه مبلغ ارزی؛ الگوی gift_card (مبلغ+واحد) جواب نمی‌دهد.',
            'proposed_title_pattern' => 'کلید بازی {نام فارسی بازی از عنوان کاتالوگ} ({کد ریجن})',
            'samples_if_persian_catalog_title' => [
                'کلید بازی (GLOBAL)',
                'کلید بازی (CIS)',
                'کلید بازی (EU)',
            ],
            'until_then' => 'unknown_mapping / unknown_amount → یک ردیف قدیمی',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceTopupPolicyNote(): array
    {
        return [
            'split_appropriate' => true,
            'when' => 'برند در torob_split_copy.brands و واحد در brand_units یا unit_aliases و عدد در نام SKU',
            'proposed_title_pattern' => 'شارژ {برند فارسی} {N} {واحد}',
            'samples' => [
                'شارژ پابجی موبایل 60 یوسی',
                'شارژ فری فایر 110 الماس',
                'شارژ ولورانت 475 ولورانت پوینت',
            ],
        ];
    }
}

<?php

$torobIntIds = static function (string $key, string $default = ''): array {
    return array_values(array_unique(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env($key, $default)),
    ), static fn (int $id): bool => $id > 0)));
};

$torobSlugs = static function (string $key, string $default = ''): array {
    return array_values(array_unique(array_filter(array_map(
        static fn (string $slug): string => trim($slug),
        explode(',', (string) env($key, $default)),
    ), static fn (string $slug): bool => $slug !== '')));
};

$enabledProductIds = $torobIntIds(
    'TOROB_SPLIT_BY_SKU_ENABLED_PRODUCT_IDS',
    (string) env('TOROB_SPLIT_BY_SKU_PRODUCT_IDS', '887'),
);

return [
    'shop_base_url' => rtrim((string) env('TOROB_SHOP_BASE_URL', env('SHOP_URL', 'https://charjino.ir')), '/'),
    'page_size' => 100,
    'timezone' => 'Asia/Tehran',
    'cache_ttl' => 300,
    'jwt_audience' => (string) env('TOROB_JWT_AUD', 'api.charjino.ir'),
    'jwt_skip' => filter_var(env('TOROB_JWT_SKIP', false), FILTER_VALIDATE_BOOLEAN),
    'public_key' => (string) env(
        'TOROB_PUBLIC_KEY',
        'MCowBQYDK2VwAyEAt6Mu4T0pBORY11W+QeM35UsmLO3vsf+6yKpFDEImFk0=',
    ),
    'jwt_verify_key' => (string) env('TOROB_JWT_VERIFY_KEY', ''),
    'excluded_product_ids' => $torobIntIds('TOROB_EXCLUDED_PRODUCT_IDS'),
    // Legacy alias of split_by_sku.enabled_product_ids (env default still Rewarble USD = 887).
    'split_by_sku_product_ids' => $enabledProductIds,
    'split_by_sku' => [
        'enabled_product_ids' => $enabledProductIds,
        'enabled_group_slugs' => $torobSlugs('TOROB_SPLIT_BY_SKU_ENABLED_GROUP_SLUGS'),
        'exclude_product_ids' => $torobIntIds('TOROB_SPLIT_BY_SKU_EXCLUDE_PRODUCT_IDS'),
        'max_items_per_product' => max(1, (int) env('TOROB_SPLIT_BY_SKU_MAX_ITEMS_PER_PRODUCT', 30)),
        'canonical_path' => storage_path('app/torob/split-canonical.json'),
        'runtime_path' => storage_path('app/torob/split-runtime.json'),
        'baseline_path' => storage_path('app/torob/split-baseline.json'),
        'rollout_dir' => storage_path('app/torob/rollout'),
        'rollout_source_dir' => database_path('data/torob/rollout'),
        'max_batch' => 500,
        // 1.5× applies only to the whole feed vs stored baseline (50,034 → cap 75,051).
        'max_feed_multiplier' => 1.5,
        // Per-stage gates (not per-category ratio): ≤500 products and ≤1500 extra feed rows.
        'max_stage_extra_rows' => 1500,
        'rule_version' => 'v3',
    ],
];

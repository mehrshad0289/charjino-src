<?php

namespace App\Services\Torob;

use App\Models\Product;
use App\Models\Sku;

/**
 * Torob-only titles and category hints. Storefront product.title is never modified.
 */
final class TorobFeedCatalogCopy
{
    private const SHOP_PREFIX_PATTERN = '/^(گیفت|خرید|شارژ)/u';

    private const STEAM_GIFT_KIND_GAME = 'game';

    private const STEAM_GIFT_KIND_DLC = 'dlc';

    private const STEAM_GIFT_KIND_SOUNDTRACK = 'soundtrack';

    private const STEAM_GIFT_KIND_BUNDLE = 'bundle';

    /**
     * @var array<string, string>
     */
    private const GROUP_TITLE_PREFIX = [
        'game-topup' => 'شارژ بازی',
        'gift-card' => 'گیفت کارت',
        'game-keys' => 'کلید بازی',
        'steam-wallet' => 'شارژ کیف پول استیم',
        'steam-gifts' => 'گیفت استیم',
        'telegram' => 'شارژ تلگرام',
        'manual-services' => 'خرید سرویس',
    ];

    /**
     * @var array<string, string>
     */
    private const GROUP_BREADCRUMB = [
        'game-topup' => 'شارژ بازی / موبایل و آنلاین',
        'gift-card' => 'گیفت کارت / دیجیتال',
        'game-keys' => 'بازی / کلید دیجیتال',
        'steam-wallet' => 'گیفت کارت / استیم / کیف پول',
        'steam-gifts' => 'گیفت کارت / استیم / بازی کامپیوتری',
        'telegram' => 'شارژ / تلگرام',
        'manual-services' => 'خدمات دیجیتال',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const STEAM_GIFT_PREFIX = [
        self::STEAM_GIFT_KIND_GAME => 'گیفت استیم',
        self::STEAM_GIFT_KIND_DLC => 'گیفت استیم DLC',
        self::STEAM_GIFT_KIND_SOUNDTRACK => 'گیفت استیم موسیقی متن',
        self::STEAM_GIFT_KIND_BUNDLE => 'گیفت استیم بسته',
    ];

    /**
     * @var array<string, string>
     */
    private const STEAM_GIFT_BREADCRUMB_SUFFIX = [
        self::STEAM_GIFT_KIND_GAME => '',
        self::STEAM_GIFT_KIND_DLC => ' / افزودنی (DLC)',
        self::STEAM_GIFT_KIND_SOUNDTRACK => ' / موسیقی متن',
        self::STEAM_GIFT_KIND_BUNDLE => ' / بسته',
    ];

    public function torobTitle(Product $product): string
    {
        $title = $this->normalizeSpaces((string) $product->title);
        if ($title === '') {
            return '';
        }

        $slug = trim((string) ($product->catalogGroup?->slug ?? ''));
        if ($slug === 'steam-gifts') {
            return $this->steamGiftTorobTitle($product, $title);
        }

        if (preg_match(self::SHOP_PREFIX_PATTERN, $title) === 1) {
            return $this->appendLiveTitleQualifiers($product, $title);
        }

        $prefix = self::GROUP_TITLE_PREFIX[$slug] ?? null;
        if ($prefix === null || $prefix === '') {
            return $this->appendLiveTitleQualifiers($product, $title);
        }

        $body = $this->stripGroupLeadPrefix($prefix, $title);

        return $this->appendLiveTitleQualifiers($product, $this->joinPrefix($prefix, $body));
    }

    public function torobSubtitle(Product $product): ?string
    {
        $slug = trim((string) ($product->catalogGroup?->slug ?? ''));
        $base = self::GROUP_BREADCRUMB[$slug] ?? null;
        if ($base === null || $base === '') {
            $groupTitle = trim((string) ($product->catalogGroup?->title ?? $product->catalogGroup?->name ?? ''));
            $base = $groupTitle !== '' ? $groupTitle : null;
        }
        if ($base === null) {
            return null;
        }
        if ($slug === 'steam-gifts') {
            $kind = $this->steamGiftKind($product, trim((string) $product->title));
            $suffix = self::STEAM_GIFT_BREADCRUMB_SUFFIX[$kind] ?? '';

            return $base.$suffix;
        }

        return $base;
    }

    public function torobCategoryName(Product $product): ?string
    {
        $subtitle = $this->torobSubtitle($product);

        return $subtitle !== null && $subtitle !== '' ? $subtitle : null;
    }

    /**
     * Digital goods condition for Torob panel (not in product_api_v3 schema; exposed via spec).
     */
    public function productCondition(): string
    {
        return 'نو';
    }

    public function torobSkuTitle(Product $product, Sku $sku): string
    {
        if ($this->usesLegacySplitTitle($product)) {
            return $this->legacySplitTitle($product, $sku);
        }
        $persian = $this->persianSkuTitle($product, $sku);

        return $persian ?? $this->torobTitle($product);
    }

    public function normalizeTitleKey(string $title): string
    {
        $title = strtr($title, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            'ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه', '‌' => '',
        ]);
        $title = mb_strtolower($this->normalizeSpaces($title));
        $stripped = preg_replace('/[^\p{L}\p{N}]+/u', '', $title);

        return is_string($stripped) ? $stripped : $title;
    }

    public function mappingGap(Product $product, Sku $sku): string
    {
        if ($this->usesLegacySplitTitle($product)) {
            return '';
        }
        if ($this->groupSpec($product) === null) {
            return 'unknown_group';
        }
        if ($this->persianBrand($product) === null) {
            return 'unknown_brand';
        }
        if ($this->persianSkuTitle($product, $sku) !== null) {
            return '';
        }
        $amount = $this->skuAmount($sku);
        if ($amount === null) {
            return 'unknown_amount';
        }
        if ($this->persianCurrency($amount['currency']) === null) {
            return 'unknown_currency:'.$amount['currency'];
        }
        $code = $this->regionCode($product, $sku);
        if ($code === null) {
            return 'unknown_region';
        }
        if ($this->persianRegion($code, $product, $sku) === null) {
            return 'unknown_region:'.$code;
        }

        return 'incomplete_title';
    }

    public function usesLegacySplitTitle(Product $product): bool
    {
        $ids = array_map('intval', config('torob_split_copy.legacy_split_product_ids', [887]));
        if (in_array((int) $product->id, $ids, true)) {
            return true;
        }
        $handle = mb_strtolower(trim((string) $product->handle));
        foreach (config('torob_split_copy.legacy_split_handles', ['giftcard-rewarble-usd']) as $legacy) {
            if ($handle === mb_strtolower(trim((string) $legacy))) {
                return true;
            }
        }

        return false;
    }

    private function legacySplitTitle(Product $product, Sku $sku): string
    {
        $base = $this->torobTitle($product);
        $amount = $this->skuNumericAmount($sku);
        if ($amount !== null) {
            $inserted = $this->insertAmountIntoTitle($base, $amount);
            if ($inserted !== $base) {
                return $this->normalizeSpaces($inserted);
            }
        }
        $label = $this->skuDistinctLabel($sku, $base);
        if ($label === '') {
            return $base;
        }

        return $this->normalizeSpaces($base.' — '.$label);
    }

    public function persianSkuTitle(Product $product, Sku $sku): ?string
    {
        $spec = $this->groupSpec($product);
        $brand = $this->persianBrand($product);
        if ($spec === null || $brand === null) {
            return null;
        }
        $kind = (string) ($spec['kind'] ?? 'gift_card');
        if ($kind === 'topup') {
            return $this->persianTopupTitle($spec, $brand, $product, $sku);
        }
        $amount = $this->skuAmount($sku);
        $unit = $amount !== null ? $this->persianCurrency($amount['currency']) : null;
        if ($amount === null || $unit === null) {
            return null;
        }
        $regionCode = $this->regionCode($product, $sku);
        $regionFa = $this->persianRegion($regionCode, $product, $sku);
        if (! empty($spec['require_region']) && ($regionCode === null || $regionFa === null)) {
            return null;
        }
        if ($regionCode !== null && $regionFa === null) {
            return null;
        }
        $parts = [(string) ($spec['prefix'] ?? 'گیفت کارت'), $brand];
        if ($regionFa !== null) {
            $parts[] = $regionFa;
        }
        $parts[] = $amount['amount'];
        $parts[] = $unit;
        $title = $this->normalizeSpaces(implode(' ', $parts));
        if ($regionCode !== null && $regionCode !== '') {
            $title .= ' ('.strtoupper($regionCode).')';
        }

        return $this->assertCompletePersianTitle($title);
    }

    /**
     * @return array{amount: string, currency: string}|null
     */
    private function skuAmount(Sku $sku): ?array
    {
        $offer = trim((string) $sku->fazercards_offer_id);
        if (preg_match('/^(\d+(?:\.\d+)?)_([a-z]{3})$/i', $offer, $matches) === 1) {
            $amount = $this->canonicalAmount($matches[1]);
            if ($amount !== null) {
                return ['amount' => $amount, 'currency' => strtoupper($matches[2])];
            }
        }
        $codes = $this->currencyPattern();
        $aliases = implode('|', array_map(static fn (string $token): string => preg_quote($token, '/'), array_keys($this->stringMap('currency_aliases'))));
        $unitPart = $codes.($aliases !== '' ? '|'.$aliases : '');
        if (preg_match('/(?<![\d.,])(\d+(?:[.,]\d+)?)\s*('.$unitPart.')/iu', (string) $sku->name, $matches) === 1) {
            $amount = $this->canonicalAmount($matches[1]);
            if ($amount !== null) {
                return ['amount' => $amount, 'currency' => $this->normalizeCurrencyToken($matches[2])];
            }
        }

        return null;
    }

    public function brandNeedle(Product $product): ?string
    {
        return $this->matchingBrandNeedle($product);
    }

    private function persianBrand(Product $product): ?string
    {
        $matched = $this->matchingBrandNeedle($product);

        return $matched !== null ? $this->stringMap('brands')[$matched] : null;
    }

    private function matchingBrandNeedle(Product $product): ?string
    {
        $handle = mb_strtolower(trim((string) $product->handle));
        foreach (array_keys($this->stringMap('brand_excluded_handle_fragments')) as $fragment) {
            if ($fragment !== '' && str_contains($handle, $fragment)) {
                return null;
            }
        }
        $platform = mb_strtolower(trim((string) ($product->platform ?? '')));
        $needles = array_keys($this->stringMap('brands'));
        usort($needles, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($needles as $needle) {
            if ($needle !== '' && (str_contains($handle, $needle) || ($platform !== '' && str_contains($platform, $needle)))) {
                return $needle;
            }
        }

        return null;
    }

    private function regionCode(Product $product, Sku $sku): ?string
    {
        $handle = mb_strtolower(trim((string) $product->handle));
        $pattern = $this->regionSuffixPattern();
        if ($pattern !== '' && preg_match($pattern, $handle, $matches) === 1) {
            return $this->normalizeRegionCode($matches[1]);
        }
        foreach ([$sku->region ?? '', $product->region ?? ''] as $raw) {
            $value = strtolower(trim((string) $raw));
            if ($value === '') {
                continue;
            }
            $normalized = $this->normalizeRegionCode($value);
            if (isset($this->stringMap('regions')[$normalized])) {
                return $normalized;
            }
        }

        return null;
    }

    private function persianRegion(?string $code, Product $product, Sku $sku): ?string
    {
        $map = $this->stringMap('regions');
        if ($code !== null && isset($map[$code])) {
            return $map[$code];
        }
        foreach ([$sku->region ?? '', $product->region ?? ''] as $raw) {
            $value = trim((string) $raw);
            if ($value !== '' && preg_match('/\p{Arabic}/u', $value) === 1) {
                return $value;
            }
        }

        return null;
    }

    private function persianCurrency(string $code): ?string
    {
        $code = strtoupper(trim($code));
        $map = $this->stringMap('currencies');
        if (isset($map[$code])) {
            return $map[$code];
        }
        $aliases = $this->stringMap('currency_aliases');
        if (isset($aliases[$code])) {
            return $map[strtoupper($aliases[$code])] ?? null;
        }

        return $map[$this->normalizeCurrencyToken($code)] ?? null;
    }

    private function normalizeCurrencyToken(string $token): string
    {
        $token = trim($token);
        $aliases = $this->stringMap('currency_aliases');
        if (isset($aliases[$token])) {
            return strtoupper($aliases[$token]);
        }

        return strtoupper($token);
    }

    private function normalizeRegionCode(string $code): string
    {
        $code = strtolower(trim($code));
        $aliases = $this->stringMap('region_aliases');

        return $aliases[$code] ?? $code;
    }

    /**
     * @return array{kind: string, prefix: string, require_region?: bool}|null
     */
    private function groupSpec(Product $product): ?array
    {
        $groups = config('torob_split_copy.groups', []);
        if (! is_array($groups)) {
            return null;
        }
        $slug = trim((string) ($product->catalogGroup?->slug ?? ''));
        if ($slug !== '' && isset($groups[$slug]) && is_array($groups[$slug])) {
            return $groups[$slug];
        }
        $handle = mb_strtolower(trim((string) $product->handle));
        if (str_starts_with($handle, 'giftcard-') || str_contains($handle, 'itunes') || str_contains($handle, 'google-play') || str_contains($handle, 'razer')) {
            return is_array($groups['gift-card'] ?? null) ? $groups['gift-card'] : null;
        }
        if (str_starts_with($handle, 'topup-')) {
            return is_array($groups['game-topup'] ?? null) ? $groups['game-topup'] : null;
        }

        return null;
    }

    /**
     * @param  array{kind: string, prefix: string, require_region?: bool}  $spec
     */
    private function persianTopupTitle(array $spec, string $brand, Product $product, Sku $sku): ?string
    {
        $name = trim((string) $sku->name);
        $unit = $this->topupUnit($product, $name);
        $amountText = $this->topupAmountText($name, $unit);
        if ($amountText === null || $unit === null) {
            return null;
        }
        $region = $this->topupRegionCode($product);
        if ($region === false) {
            return null;
        }
        $prefix = $this->normalizeSpaces((string) ($spec['prefix'] ?? 'شارژ'));
        $lead = $this->prefixAlreadyHasBrand($prefix, $brand)
            ? $prefix
            : $this->normalizeSpaces($prefix.' '.$brand);
        $title = $this->normalizeSpaces($lead.' '.$amountText.' '.$unit);
        $variant = $this->topupVariantLabel($product);
        if ($variant !== null) {
            $title = $this->normalizeSpaces($title.' '.$variant);
        }
        if (is_string($region) && $region !== '') {
            $regionFa = $this->persianRegion($region, $product, $sku);
            if ($regionFa === null) {
                return null;
            }
            $title = $this->normalizeSpaces($title.' '.$regionFa.' ('.strtoupper($region).')');
        }

        return $this->assertCompletePersianTitle($title);
    }

    private function topupAmountText(string $name, ?string $unit): ?string
    {
        $mapped = $this->skuNameLabel($name);
        if ($mapped !== null) {
            return $mapped;
        }
        if ($unit === null) {
            return null;
        }
        $unitAliases = [];
        foreach ($this->stringMap('unit_aliases') as $alias => $label) {
            if ($label === $unit) {
                $unitAliases[] = preg_quote((string) $alias, '/');
            }
        }
        $unitAliases[] = preg_quote($unit, '/');
        $unitPattern = implode('|', array_values(array_unique($unitAliases)));
        if (preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*\+\s*(\d+(?:[.,]\d+)?)\s*(?:'.$unitPattern.')\s*$/iu', $name, $matches) === 1) {
            $left = $this->canonicalAmount($matches[1]);
            $right = $this->canonicalAmount($matches[2]);
            if ($left === null || $right === null) {
                return null;
            }

            return $left.' + '.$right;
        }
        if (preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*(?:'.$unitPattern.')\s*$/iu', $name, $matches) === 1) {
            return $this->canonicalAmount($matches[1]);
        }

        return null;
    }

    private function skuNameLabel(string $name): ?string
    {
        $key = mb_strtolower(trim($name));
        $map = $this->stringMap('sku_name_labels');

        return $key !== '' && isset($map[$key]) ? $map[$key] : null;
    }

    private function canonicalAmount(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            return null;
        }
        if (str_contains($raw, ',')) {
            $parts = explode(',', $raw);
            if (count($parts) === 2 && strlen($parts[1]) === 3 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
                $raw = $parts[0].$parts[1];
            } else {
                return null;
            }
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $raw) !== 1) {
            return null;
        }
        $raw = ltrim($raw, '0');
        if ($raw === '' || str_starts_with($raw, '.')) {
            $raw = '0'.$raw;
        }
        if (! preg_match('/^\d+(?:\.\d+)?$/', $raw) || $raw === '0') {
            return null;
        }

        return $raw;
    }

    /**
     * @return string|false|null false = known brand but unknown region remainder (do not split)
     */
    private function topupRegionCode(Product $product): string|false|null
    {
        $needle = $this->matchingBrandNeedle($product);
        $brands = $this->stringMap('topup_region_handle_brands');
        if ($needle === null || $needle === '' || ! isset($brands[$needle])) {
            return null;
        }
        $handle = mb_strtolower(trim((string) $product->handle));
        if (preg_match('#(?:^|[-_])'.preg_quote($needle, '#').'(?:[-_]|$)#u', $handle) !== 1) {
            return null;
        }
        $remainder = trim((string) preg_replace('#^.*?'.preg_quote($needle, '#').'(?:[-_]|$)#u', '', $handle), '-_');
        if ($remainder === '') {
            return null;
        }
        $code = $this->normalizeRegionCode($remainder);
        if (! isset($this->stringMap('regions')[$code])) {
            return false;
        }

        return $code;
    }

    private function topupVariantLabel(Product $product): ?string
    {
        $handle = mb_strtolower(trim((string) $product->handle));
        $token = (string) strrchr($handle, '-');
        $token = $token !== '' ? ltrim($token, '-') : '';
        $labels = $this->stringMap('topup_variant_suffixes');

        return $token !== '' && isset($labels[$token]) ? $labels[$token] : null;
    }

    private function appendLiveTitleQualifiers(Product $product, string $title): string
    {
        $title = $this->appendGameKeyEdition($product, $title);

        return $this->appendTopupVariant($product, $title);
    }

    private function appendGameKeyEdition(Product $product, string $title): string
    {
        $slug = trim((string) ($product->catalogGroup?->slug ?? ''));
        if ($slug !== 'game-keys') {
            return $title;
        }
        $handle = mb_strtolower(trim((string) $product->handle));
        $labels = $this->stringMap('game_key_edition_labels');
        uksort($labels, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($labels as $token => $label) {
            if ($token !== '' && preg_match('#(?:^|[-_])'.preg_quote($token, '#').'(?:[-_]|$)#u', $handle) === 1) {
                if ($label !== '' && ! str_contains($title, $label)) {
                    return $this->normalizeSpaces($title.' '.$label);
                }

                return $title;
            }
        }

        return $title;
    }

    private function appendTopupVariant(Product $product, string $title): string
    {
        $slug = trim((string) ($product->catalogGroup?->slug ?? ''));
        if ($slug !== 'game-topup') {
            return $title;
        }
        $label = $this->topupVariantLabel($product);
        if ($label === null || $label === '' || str_contains($title, $label)) {
            return $title;
        }

        return $this->normalizeSpaces($title.' '.$label);
    }

    private function prefixAlreadyHasBrand(string $prefix, string $brand): bool
    {
        $prefixKey = $this->normalizeTitleKey($prefix);
        $brandKey = $this->normalizeTitleKey($brand);

        return $brandKey !== '' && str_ends_with($prefixKey, $brandKey);
    }

    private function topupUnit(Product $product, string $skuName): ?string
    {
        $needle = $this->matchingBrandNeedle($product);
        $brandUnits = $this->stringMap('brand_units');
        if ($needle !== null && isset($brandUnits[$needle])) {
            return $brandUnits[$needle];
        }
        $haystack = mb_strtolower($skuName);
        foreach ($this->stringMap('unit_aliases') as $alias => $label) {
            if ($alias !== '' && str_contains($haystack, mb_strtolower((string) $alias))) {
                return $label;
            }
        }

        return null;
    }

    private function assertCompletePersianTitle(string $title): ?string
    {
        $title = $this->normalizeSpaces($title);
        $body = preg_replace('/\s*\([A-Z]{2,8}\)$/', '', $title) ?? $title;
        if (preg_match('/[A-Za-z]/u', $body) === 1) {
            return null;
        }
        if (preg_match('/\.(?!\d)|(?<!\d)\./u', $body) === 1) {
            return null;
        }
        $plusStripped = preg_replace('/\d+(?:\.\d+)?\s*\+\s*\d+(?:\.\d+)?/', '', $body) ?? $body;
        if (str_contains($plusStripped, '+')) {
            return null;
        }
        $stripped = preg_replace('/\d+(?:\.\d+)?/', '', $plusStripped) ?? $plusStripped;
        if (preg_match('/^[\p{Arabic}\s\x{200C}\x{200D}]*$/u', $stripped) !== 1) {
            return null;
        }

        return $title;
    }

    private function currencyPattern(): string
    {
        $codes = array_keys($this->stringMap('currencies'));
        usort($codes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return implode('|', array_map(static fn (string $code): string => preg_quote($code, '/'), $codes));
    }

    private function regionSuffixPattern(): string
    {
        $codes = array_values(array_unique(array_merge(
            array_keys($this->stringMap('region_aliases')),
            array_keys($this->stringMap('regions')),
        )));
        usort($codes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        if ($codes === []) {
            return '';
        }

        return '/-('.implode('|', array_map(static fn (string $code): string => preg_quote($code, '/'), $codes)).')$/i';
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(string $key): array
    {
        $raw = config('torob_split_copy.'.$key, []);
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $needle => $label) {
            $needle = trim((string) $needle);
            $label = trim((string) $label);
            if ($needle !== '' && $label !== '') {
                $out[$needle] = $label;
            }
        }

        return $out;
    }

    public function skuNumericAmount(Sku $sku): ?int
    {
        $parsed = $this->skuAmount($sku);
        if ($parsed === null || str_contains($parsed['amount'], '.')) {
            return null;
        }
        $amount = (int) $parsed['amount'];

        return $amount > 0 ? $amount : null;
    }

    private function insertAmountIntoTitle(string $base, int $amount): string
    {
        if ($this->titleHasWholeAmount($base, $amount)) {
            return $base;
        }
        $token = (string) $amount;
        $patterns = [
            '/(?<![\d۰-۹٠-٩])(دلار)/u',
            '/(?<![\d۰-۹٠-٩])(یورو)/u',
            '/(?<![\d۰-۹٠-٩])(پوند)/u',
            '/(?<![\d۰-۹٠-٩])(تومان)/u',
            '/(?<![\d۰-۹٠-٩])(لیر)/u',
            '/(\((?:USD|EUR|GBP|IRT)\))/u',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $base) === 1) {
                $replaced = preg_replace($pattern, $token.' $1', $base, 1);

                return is_string($replaced) ? $this->normalizeSpaces($replaced) : $base;
            }
        }

        return $base;
    }

    private function titleHasWholeAmount(string $title, int $amount): bool
    {
        $normalized = $this->normalizeSpaces(strtr($title, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]));

        return preg_match('/(?<!\d)'.preg_quote((string) $amount, '/').'(?!\d)/u', $normalized) === 1;
    }

    private function skuDistinctLabel(Sku $sku, string $base): string
    {
        $parts = [];
        $name = trim((string) $sku->name);
        if ($name !== '' && ! $this->containsNormalized($base, $name)) {
            $parts[] = $name;
        }
        $region = trim((string) $sku->region);
        $joined = implode(' ', $parts);
        if ($region !== '' && ! $this->containsNormalized($base, $region) && ! $this->containsNormalized($joined, $region)) {
            $parts[] = $region;
        }
        $nameEn = trim((string) $sku->name_en);
        if ($parts === [] && $nameEn !== '' && ! $this->containsNormalized($base, $nameEn)) {
            $parts[] = $nameEn;
        }

        return $this->normalizeSpaces(implode(' — ', $parts));
    }

    private function containsNormalized(string $haystack, string $needle): bool
    {
        $hay = $this->normalizeTitleKey($haystack);
        $need = $this->normalizeTitleKey($needle);

        return $need !== '' && str_contains($hay, $need);
    }

    private function steamGiftKind(Product $product, string $title): string
    {
        $haystack = mb_strtolower($title.' '.(string) $product->name_en.' '.(string) $product->subtitle);

        if (preg_match('/\b(soundtrack|original soundtrack|ost)\b/u', $haystack) === 1
            || (str_contains($haystack, 'music') && str_contains($haystack, 'sound'))) {
            return self::STEAM_GIFT_KIND_SOUNDTRACK;
        }
        if (preg_match('/\b(dlc|downloadable content|expansion)\b/u', $haystack) === 1) {
            return self::STEAM_GIFT_KIND_DLC;
        }
        if (preg_match('/\b(bundle|complete pack|collection)\b/u', $haystack) === 1) {
            return self::STEAM_GIFT_KIND_BUNDLE;
        }

        return self::STEAM_GIFT_KIND_GAME;
    }

    private function steamGiftTorobTitle(Product $product, string $title): string
    {
        $kind = $this->steamGiftKind($product, $title);
        $prefix = self::STEAM_GIFT_PREFIX[$kind] ?? self::STEAM_GIFT_PREFIX[self::STEAM_GIFT_KIND_GAME];
        $body = $this->stripSteamGiftLeadPrefix($title);
        $body = $this->stripRedundantKindTokens($body, $kind);
        $body = $this->normalizeSpaces($body);

        if ($body === '') {
            return $prefix;
        }

        return $this->joinPrefix($prefix, $body);
    }

    private function stripSteamGiftLeadPrefix(string $title): string
    {
        $title = $this->normalizeSpaces($title);
        $patterns = [
            '/^(?:گیفت\s*استیم)(?:\s+(?:DLC|dlc|موسیقی\s*متن|بسته))?\s+/iu',
            '/^(?:گیفت|خرید|شارژ)(?:\s+استیم)?(?:\s+(?:DLC|dlc|موسیقی\s*متن|بسته))?\s+/iu',
            '/^(?:steam\s*gift)(?:\s+(?:dlc|soundtrack|bundle))?\s+/iu',
        ];

        $previous = null;
        while ($previous !== $title) {
            $previous = $title;
            foreach ($patterns as $pattern) {
                $next = preg_replace($pattern, '', $title);
                if (is_string($next)) {
                    $title = $this->normalizeSpaces($next);
                }
            }
            while (preg_match('/^(گیفت\s*استیم)\s+(گیفت\s*استیم)\s+/iu', $title) === 1) {
                $next = preg_replace('/^(گیفت\s*استیم)\s+(گیفت\s*استیم)\s+/iu', '$1 ', $title);
                $title = $this->normalizeSpaces(is_string($next) ? $next : $title);
            }
        }

        return trim($title);
    }

    private function stripRedundantKindTokens(string $body, string $kind): string
    {
        $body = trim($body);
        if ($body === '') {
            return $body;
        }

        if ($kind === self::STEAM_GIFT_KIND_DLC) {
            $body = preg_replace('/\s+\bDLC\b\s*$/iu', '', $body) ?? $body;
            $body = preg_replace('/^\bDLC\b\s+/iu', '', $body) ?? $body;
        } elseif ($kind === self::STEAM_GIFT_KIND_SOUNDTRACK) {
            $body = preg_replace('/\s+\b(?:soundtrack|original soundtrack|ost)\b\s*$/iu', '', $body) ?? $body;
            $body = preg_replace('/^\b(?:soundtrack|original soundtrack|ost)\b\s+/iu', '', $body) ?? $body;
        } elseif ($kind === self::STEAM_GIFT_KIND_BUNDLE) {
            $body = preg_replace('/\s+\b(?:bundle|complete pack|collection)\b\s*$/iu', '', $body) ?? $body;
            $body = preg_replace('/^\b(?:bundle|complete pack|collection)\b\s+/iu', '', $body) ?? $body;
        }

        return trim($body);
    }

    private function stripGroupLeadPrefix(string $prefix, string $title): string
    {
        $title = $this->normalizeSpaces($title);
        $prefix = $this->normalizeSpaces($prefix);
        if ($prefix === '') {
            return $title;
        }
        if (mb_stripos($title, $prefix) === 0) {
            return trim(mb_substr($title, mb_strlen($prefix)));
        }

        return $title;
    }

    private function normalizeSpaces(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $value;
    }

    private function joinPrefix(string $prefix, string $title): string
    {
        $prefix = $this->normalizeSpaces($prefix);
        $title = $this->normalizeSpaces($title);
        if ($prefix === '') {
            return $title;
        }
        if ($title === '') {
            return $prefix;
        }
        if (mb_stripos($title, $prefix) === 0) {
            $rest = trim(mb_substr($title, mb_strlen($prefix)));
            if ($rest === '' || mb_stripos($rest, $prefix) === 0) {
                return $this->joinPrefix($prefix, $rest);
            }

            return $title;
        }

        return $prefix.' '.$title;
    }
}

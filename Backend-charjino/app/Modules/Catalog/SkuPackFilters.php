<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use App\Models\Sku;
use App\Modules\Supplier\FazerCards\FazerCardsIds;

final class SkuPackFilters
{
    public const MIN_SKUS = 4;

    /**
     * @param  iterable<Sku>  $skus
     * @return array{filters: list<array{key: string, label: string, options: list<array{value: string, label: string}>}>, skus: list<array<string, mixed>>}
     */
    public static function decorate(Product $product, iterable $skus): array
    {
        $rows = [];
        foreach ($skus as $sku) {
            if ($sku instanceof Sku) {
                $rows[] = $sku;
            }
        }
        $family = self::familyOf($product);
        $annotated = [];
        foreach ($rows as $sku) {
            $values = self::valuesFor($family, $product, $sku);
            $payload = $sku->toApi();
            $payload['filters'] = $values;
            $annotated[] = ['values' => $values, 'payload' => $payload];
        }
        $filters = [];
        if (count($rows) >= self::MIN_SKUS) {
            foreach (self::dimensionsFor($family, $product) as $key => $label) {
                $options = self::optionsOf($annotated, $key);
                if (count($options) < 2) {
                    continue;
                }
                $filters[] = [
                    'key' => $key,
                    'label' => $label,
                    'options' => $options,
                ];
            }
        }
        if ($filters === []) {
            foreach ($annotated as $index => $row) {
                unset($annotated[$index]['payload']['filters']);
            }
        }

        return [
            'filters' => $filters,
            'skus' => array_values(array_map(static fn (array $row): array => $row['payload'], $annotated)),
        ];
    }

    private static function familyOf(Product $product): string
    {
        $schema = is_array($product->purchase_schema) ? $product->purchase_schema : [];
        $type = trim((string) ($schema['type'] ?? ''));
        if ($type !== '') {
            return $type;
        }

        return trim((string) $product->fulfillment_type);
    }

    /**
     * @return array<string, string>
     */
    private static function dimensionsFor(string $family, Product $product): array
    {
        if ($product->gift_proxy) {
            return ['region' => 'ریجن', 'edition' => 'نسخه'];
        }

        return match ($family) {
            FazerCardsIds::GIFTCARD => ['region' => 'ریجن', 'denomination' => 'مبلغ'],
            FazerCardsIds::TOPUP => ['region' => 'ریجن', 'denomination' => 'بسته'],
            FazerCardsIds::GAMEKEY => ['region' => 'ریجن', 'edition' => 'نسخه'],
            FazerCardsIds::STEAM_GIFT => ['region' => 'ریجن', 'edition' => 'نسخه'],
            FazerCardsIds::TELEGRAM_PREMIUM => ['duration' => 'مدت'],
            FazerCardsIds::TELEGRAM_STARS => ['denomination' => 'بسته'],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private static function valuesFor(string $family, Product $product, Sku $sku): array
    {
        $out = [];
        foreach (array_keys(self::dimensionsFor($family, $product)) as $key) {
            $value = match ($key) {
                'region' => self::regionOf($sku),
                'edition' => self::editionOf($product, $sku),
                'denomination' => self::denominationKey($sku),
                'duration' => self::durationKey($sku),
                default => '',
            };
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{values: array<string, string>, payload: array<string, mixed>}>  $annotated
     * @return list<array{value: string, label: string}>
     */
    private static function optionsOf(array $annotated, string $key): array
    {
        $seen = [];
        foreach ($annotated as $row) {
            $value = trim((string) ($row['values'][$key] ?? ''));
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = [
                'value' => $value,
                'label' => self::optionLabel($key, $value, $row['payload']),
            ];
        }
        $options = array_values($seen);
        usort($options, function (array $a, array $b) use ($key): int {
            if (in_array($key, ['denomination', 'duration'], true)) {
                return self::numericSortKey($a['value']) <=> self::numericSortKey($b['value']);
            }

            return strcasecmp((string) $a['label'], (string) $b['label']);
        });

        return $options;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function optionLabel(string $key, string $value, array $payload): string
    {
        if ($key === 'duration') {
            $months = (int) self::numericSortKey($value);

            return $months > 0 ? $months.' ماه' : $value;
        }
        if ($key === 'edition' || $key === 'denomination') {
            $name = self::stripRegionSuffix(trim((string) ($payload['name'] ?? '')));

            return $name !== '' ? $name : $value;
        }

        return $value;
    }

    private static function regionOf(Sku $sku): string
    {
        $fromField = CatalogAttr::publicRegion($sku->region);
        if (is_string($fromField) && $fromField !== '') {
            return $fromField;
        }
        $name = (string) $sku->name;
        if (preg_match('/\(([A-Za-z]{2,7}|Global|EU|SEA|MENA|LATAM)\)\s*$/u', $name, $match) === 1) {
            $code = $match[1];
            if (strcasecmp($code, 'Global') === 0) {
                return 'Global';
            }

            return CatalogAttr::publicRegion($code) ?: strtoupper($code);
        }

        return '';
    }

    private static function editionOf(Product $product, Sku $sku): string
    {
        $name = self::stripRegionSuffix(trim((string) ($sku->name_en !== null && $sku->name_en !== '' ? $sku->name_en : $sku->name)));
        $title = trim((string) ($product->name_en ?: $product->title));
        if ($title !== '' && $name !== '' && mb_stripos($name, $title, 0, 'UTF-8') === 0) {
            $name = mb_substr($name, mb_strlen($title, 'UTF-8'), null, 'UTF-8');
        }

        return self::trimEditionEdges((string) $name);
    }

    /**
     * PHP trim() is byte-wise. A mask that includes «–» (UTF-8 E2 80 93) also
     * strips the leading E2 of ™/… and yields invalid UTF-8, which 500s JSON.
     */
    private static function trimEditionEdges(string $name): string
    {
        $cleaned = preg_replace('/^[\s\-–—:|™®©]+|[\s\-–—:|™®©]+$/u', '', $name);

        return trim(is_string($cleaned) ? $cleaned : $name);
    }

    private static function denominationKey(Sku $sku): string
    {
        $name = self::stripRegionSuffix(trim((string) $sku->name));
        $en = self::stripRegionSuffix(trim((string) ($sku->name_en ?: '')));
        $source = self::faDigits($en !== '' ? $en : $name);
        if (preg_match('/(\d+(?:\.\d+)?)\s*(usd|eur|gbp|try|uc|cp|diamonds?|stars?|gems?|coins?|robux)?/i', $source, $match) === 1) {
            $unit = strtolower((string) ($match[2] ?? ''));

            return $unit !== '' ? $match[1].'-'.$unit : $match[1];
        }
        $fa = self::faDigits($name);
        if (preg_match('/(\d+(?:\.\d+)?)\s*(دلار|لیر|یورو|یوسی|الماس|استارز)/u', $fa, $match) === 1) {
            return $match[1].'-'.$match[2];
        }

        return $name;
    }

    private static function durationKey(Sku $sku): string
    {
        $source = self::faDigits(self::stripRegionSuffix(trim((string) ($sku->name_en ?: $sku->name))));
        if (preg_match('/(\d+)\s*(ماه|months?)/iu', $source, $match) === 1) {
            return $match[1].'-month';
        }

        return '';
    }

    private static function stripRegionSuffix(string $name): string
    {
        return trim((string) preg_replace('/\s*\(([A-Za-z]{2,7}|Global|EU|SEA|MENA|LATAM)\)\s*$/iu', '', $name));
    }

    private static function faDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    private static function numericSortKey(string $value): float
    {
        if (preg_match('/(\d+(?:\.\d+)?)/', self::faDigits($value), $match) === 1) {
            return (float) $match[1];
        }

        return 0.0;
    }
}

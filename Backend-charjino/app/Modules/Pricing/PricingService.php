<?php

namespace App\Modules\Pricing;

use App\Models\Product;
use App\Models\Setting;
use App\Models\Sku;
use App\Modules\Catalog\StorefrontHomeCache;
use App\Support\Money;

class PricingService
{
    public const SOURCE_SKU = 'sku';

    public const SOURCE_PRODUCT = 'product';

    public const SOURCE_GLOBAL = 'global';

    public function marginPercent(): string
    {
        $value = trim((string) Setting::getValue('margin_percent', '5'));

        return is_numeric($value) ? self::formatMargin($value) ?? '5' : '5';
    }

    public function usdTomanRate(): string
    {
        $value = trim((string) Setting::getValue('usd_toman_rate', '0'));

        return is_numeric($value) ? $value : '0';
    }

    public static function formatMargin(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $raw = is_string($value) ? trim($value) : (string) $value;
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }
        $formatted = number_format((float) $raw, 4, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    public static function normalizeOverride(mixed $value): ?string
    {
        return self::formatMargin($value);
    }

    /**
     * SKU override > product override > global setting.
     */
    public function marginFor(?Sku $sku, ?Product $product = null): string
    {
        $fromSku = self::formatMargin($sku?->margin_percent);
        if ($fromSku !== null) {
            return $fromSku;
        }
        $product ??= $sku?->relationLoaded('product') ? $sku->product : $sku?->product()->first();
        $fromProduct = self::formatMargin($product?->margin_percent);
        if ($fromProduct !== null) {
            return $fromProduct;
        }

        return $this->marginPercent();
    }

    public function marginSource(?Sku $sku, ?Product $product = null): string
    {
        if (self::formatMargin($sku?->margin_percent) !== null) {
            return self::SOURCE_SKU;
        }
        $product ??= $sku?->relationLoaded('product') ? $sku->product : $sku?->product()->first();
        if (self::formatMargin($product?->margin_percent) !== null) {
            return self::SOURCE_PRODUCT;
        }

        return self::SOURCE_GLOBAL;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $rate = $this->usdTomanRate();
        $margin = $this->marginPercent();

        return [
            'usdTomanRate' => $rate,
            'marginPercent' => $margin,
            'fxMode' => Setting::getValue('fx_mode', 'auto') ?: 'auto',
            'fxSource' => Setting::getValue('usd_toman_source'),
            'fxUpdatedAt' => Setting::getValue('usd_toman_updated_at'),
            'sample' => [
                'usd' => '1',
                'toman' => $this->sellToman('1'),
            ],
        ];
    }

    public function sellToman(string $usd, ?string $marginPercent = null): int
    {
        $rate = (float) $this->usdTomanRate();
        $margin = $marginPercent !== null && $marginPercent !== ''
            ? (float) $marginPercent
            : (float) $this->marginPercent();
        $cost = (float) $usd;
        if ($rate <= 0 || $cost < 0) {
            return 0;
        }

        $raw = $cost * $rate * ((100 + $margin) / 100);

        return Money::roundToman((int) round($raw));
    }

    public function sellTomanFor(?Sku $sku, string $usd, ?Product $product = null): int
    {
        return $this->sellToman($usd, $this->marginFor($sku, $product));
    }

    public function costToman(string $usd): int
    {
        $rate = (float) $this->usdTomanRate();
        $cost = (float) $usd;
        if ($rate <= 0 || $cost <= 0) {
            return 0;
        }

        return (int) round($cost * $rate);
    }

    public function repriceSku(Sku $sku, ?Product $product = null): Sku
    {
        if ($sku->price_manual) {
            return $sku;
        }
        $usd = $sku->usdCost();
        if ((float) $usd > 0) {
            $sku->price = $this->sellTomanFor($sku, $usd, $product);
            $sku->save();
        }

        return $sku;
    }

    public function repriceProduct(Product $product): int
    {
        return StorefrontHomeCache::defer(function () use ($product) {
            $count = 0;
            $product->loadMissing('skus');
            foreach ($product->skus as $sku) {
                $sku->setRelation('product', $product);
                $this->repriceSku($sku, $product);
                $count++;
            }

            return $count;
        });
    }

    /**
     * @param  list<string>|null  $families
     */
    public function repriceAllSkus(?array $families = null): int
    {
        return StorefrontHomeCache::defer(function () use ($families) {
            $count = 0;
            $query = Sku::query()->with('product:id,margin_percent')->orderBy('id');
            if ($families !== null && $families !== []) {
                $query->whereIn('fulfillment_type', $families);
            }
            $query->chunkById(200, function ($skus) use (&$count) {
                foreach ($skus as $sku) {
                    $this->repriceSku($sku);
                    $count++;
                }
            });

            return $count;
        });
    }
}

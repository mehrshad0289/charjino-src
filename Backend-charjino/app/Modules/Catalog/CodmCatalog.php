<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use App\Modules\Codm\CodmRegionCheck;
use App\Modules\Supplier\FazerCards\FazerCardsIds;

final class CodmCatalog
{
    /** @var array<string, string> */
    public const VERSIONS = [
        'codm_activision_us' => 'Activision آمریکا',
        'codm_activision_ca' => 'Activision کانادا',
        'codm_activision_in' => 'Activision هند',
        'codm_activision_kz' => 'Activision قزاقستان',
        'codm_activision_sa' => 'Activision عربستان',
        'codm_garena_sgmy' => 'Garena سنگاپور/مالزی',
        'codm_garena_indonesia' => 'Garena اندونزی',
    ];

    public const HINT = 'حساب Activision و Garena جدا هستند. ریجن حساب باید با همین نسخه یکی باشد.';

    public static function categoryId(?string $stored): ?string
    {
        $id = FazerCardsIds::rawCategory($stored);
        if ($id === '' || ! isset(self::VERSIONS[$id])) {
            return null;
        }

        return $id;
    }

    public static function is(?string $stored): bool
    {
        return self::categoryId($stored) !== null;
    }

    public static function isProduct(?Product $product): bool
    {
        return $product !== null && self::is($product->fazercards_category_id);
    }

    public static function label(?string $stored): ?string
    {
        $id = self::categoryId($stored);

        return $id !== null ? self::VERSIONS[$id] : null;
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::VERSIONS);
    }

    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\s+/u', '', $value) ?? '';
    }

    public static function looksLikeId(string $value): bool
    {
        $clean = self::digitsOnly($value);

        return $clean !== '' && ctype_digit($clean);
    }

    /**
     * @return array{hint: string, versions: list<array{handle: string, label: string, selected: bool}>}|null
     */
    public static function payload(?Product $product): ?array
    {
        $current = self::categoryId($product?->fazercards_category_id);
        if ($current === null) {
            return null;
        }

        $rows = Product::query()
            ->where('status', 'active')
            ->where('visible', true)
            ->whereIn('fazercards_category_id', self::ids())
            ->notStorefrontExcluded()
            ->get(['handle', 'fazercards_category_id']);
        $byId = [];
        foreach ($rows as $row) {
            $id = self::categoryId($row->fazercards_category_id);
            if ($id !== null) {
                $byId[$id] = (string) $row->handle;
            }
        }

        $versions = [];
        foreach (self::VERSIONS as $id => $label) {
            $handle = $byId[$id] ?? null;
            if ($handle === null || $handle === '') {
                continue;
            }
            $versions[] = [
                'handle' => $handle,
                'label' => $label,
                'selected' => $id === $current,
            ];
        }

        $variant = CodmRegionCheck::variantForFazerCategory($current);

        return [
            'hint' => self::HINT,
            'versions' => $versions,
            'regionCheck' => $variant !== null,
            'variantCountry' => $variant,
            'guideHref' => '/blog/codm-region',
        ];
    }
}

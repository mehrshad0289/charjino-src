<?php

namespace App\Modules\Catalog;

use App\Modules\Codm\CodmRegionCheck;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsSteamTopupQuotes;
use App\Modules\Supplier\FazerCards\FazerCardsValidateId;

final class PurchaseSchema
{
    /**
     * @param  list<string|array<string, mixed>>  $inputs
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function build(string $family, array $inputs = [], array $context = []): array
    {
        $fields = self::fields($family, $inputs, $context);
        $qtyEnabled = in_array($family, [FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY], true);
        $amountEnabled = $family === FazerCardsIds::STEAM_TOPUP;
        $noteEn = trim((string) ($context['noteEn'] ?? ''));
        $name = trim((string) ($context['name'] ?? ''));
        $existingFa = trim((string) ($context['regionNote'] ?? ''));

        return [
            'type' => $family,
            'fields' => $fields,
            'quantity' => [
                'enabled' => $qtyEnabled,
                'min' => $qtyEnabled ? max(1, (int) ($context['minQty'] ?? 1)) : 1,
                'max' => $qtyEnabled ? max(1, min(CatalogAttr::SHOP_QTY_CAP, (int) ($context['maxQty'] ?? CatalogAttr::SHOP_QTY_CAP))) : 1,
            ],
            'amount' => [
                'enabled' => $amountEnabled,
                'currencies' => $amountEnabled ? FazerCardsSteamTopupQuotes::CURRENCIES : [],
                'minUsd' => FazerCardsSteamTopupQuotes::minUsd(),
                'maxUsd' => FazerCardsSteamTopupQuotes::maxUsd(),
                'decimals' => ['USD' => 2, '*' => 4],
            ],
            'validation' => [
                'mode' => self::validationMode($family, $context),
                'validateCategoryId' => $family === FazerCardsIds::TOPUP
                    ? (trim((string) ($context['validateCategoryId'] ?? '')) ?: null)
                    : null,
            ],
            'regionNote' => $existingFa !== '' ? $existingFa : RegionNote::fa($noteEn, $name),
            'regionNoteEn' => $noteEn !== '' ? $noteEn : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function validationMode(string $family, array $context = []): string
    {
        if ($family === FazerCardsIds::STEAM_TOPUP) {
            return 'steam_check';
        }
        if ($family !== FazerCardsIds::TOPUP) {
            return 'none';
        }
        $categoryId = trim((string) ($context['validateCategoryId'] ?? ''));
        if ($categoryId === '') {
            return 'ack';
        }
        if (CodmRegionCheck::variantForFazerCategory($categoryId) !== null) {
            return 'codm_store';
        }
        $supported = $context['validateSupportedIds'] ?? null;
        $ids = is_array($supported) ? array_values(array_filter(array_map('strval', $supported))) : FazerCardsValidateId::supportedIds();
        if ($ids === [] && $supported === null && ! FazerCardsValidateId::catalogLoaded()) {
            return 'none';
        }

        return FazerCardsValidateId::resolve($categoryId, $ids) ? 'fazer' : 'ack';
    }

    /**
     * @param  list<string|array<string, mixed>>  $inputs
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public static function fields(string $family, array $inputs, array $context = []): array
    {
        if (in_array($family, [FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY], true)) {
            return [];
        }
        if ($family === FazerCardsIds::STEAM_TOPUP) {
            return self::defaults(['steamLogin']);
        }
        if ($family === FazerCardsIds::STEAM_GIFT) {
            return self::defaults(['invite_url']);
        }
        if (in_array($family, [FazerCardsIds::TELEGRAM_STARS, FazerCardsIds::TELEGRAM_PREMIUM], true)) {
            return self::defaults(['telegram_username']);
        }

        $out = [];
        foreach ($inputs as $row) {
            $asRow = is_array($row) ? $row : ['key' => (string) $row];
            $enriched = TopupFieldCatalog::enrich($asRow);
            if ($enriched !== null) {
                $out[] = $enriched;
            }
        }

        $categoryId = isset($context['validateCategoryId']) ? (string) $context['validateCategoryId'] : null;
        if (CodmCatalog::is($categoryId)) {
            $activision = CodmRegionCheck::variantForFazerCategory($categoryId) !== null;
            foreach ($out as $i => $field) {
                $key = strtolower((string) ($field['key'] ?? ''));
                if ($activision && in_array($key, ['user_id', 'uid'], true)) {
                    $out[$i]['pattern'] = '^\d{5,25}$';
                    $out[$i]['help'] = 'UID عددی حدود ۲۰ رقم، زیر نیک‌نیم در Player Profile بازی';
                } else {
                    $out[$i]['pattern'] = '^\d+$';
                }
            }
        }

        return array_values($out);
    }

    /**
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private static function defaults(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $enriched = TopupFieldCatalog::enrich(['key' => $key, 'required' => true]);
            if ($enriched !== null) {
                $out[] = $enriched;
            }
        }

        return $out;
    }
}

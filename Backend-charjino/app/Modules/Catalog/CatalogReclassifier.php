<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use App\Models\Sku;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsSteamTopupQuotes;

final class CatalogReclassifier
{
    /**
     * @return array{products: int, hidden: int, schemas: int, hiddenSteamPacks: int}
     */
    public function run(): array
    {
        $touched = 0;
        $hidden = 0;
        $schemas = 0;
        Product::query()
            ->where(function ($query) {
                $query->whereNotNull('fazercards_category_id')->where('fazercards_category_id', '!=', '')
                    ->orWhereIn('fulfillment_type', FazerCardsIds::FAMILIES);
            })
            ->orderBy('id')
            ->chunkById(100, function ($products) use (&$touched, &$hidden, &$schemas): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }
                    $family = FazerCardsIds::familyOf($product->fulfillment_type, $product->fazercards_category_id);
                    $inputs = is_array($product->inputs) ? $product->inputs : [];
                    $reason = CatalogExclusion::reason(
                        $family,
                        trim($product->title.' '.$product->name_en),
                        $inputs,
                        [
                            'category_id' => $product->fazercards_category_id,
                            'id' => $product->fazercards_category_id,
                            'handle' => $product->handle,
                            'name' => $product->title,
                        ],
                    );
                    $dirty = false;
                    if ($reason !== null) {
                        if ($product->excluded_reason !== $reason || $product->visible) {
                            $product->excluded_reason = $reason;
                            $product->visible = false;
                            $dirty = true;
                            $hidden++;
                        }
                    }
                    if (in_array($family, [FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY], true) && $inputs !== []) {
                        $product->inputs = [];
                        $inputs = [];
                        $dirty = true;
                    }
                    $existing = is_array($product->purchase_schema) ? $product->purchase_schema : [];
                    $schema = PurchaseSchema::build($family, $inputs, [
                        'noteEn' => (string) ($existing['regionNoteEn'] ?? ''),
                        'name' => (string) $product->title,
                        'regionNote' => (string) ($existing['regionNote'] ?? ''),
                        'validateCategoryId' => FazerCardsIds::rawCategory($product->fazercards_category_id),
                    ]);
                    $product->purchase_schema = $schema;
                    $schemas++;
                    $dirty = true;
                    if ($dirty) {
                        $product->save();
                        $touched++;
                    }
                }
            });

        $hiddenSteamPacks = Sku::query()
            ->where('fulfillment_type', FazerCardsIds::STEAM_TOPUP)
            ->where('fazercards_offer_id', '!=', FazerCardsSteamTopupQuotes::FREE_OFFER_ID)
            ->update([
                'status' => 'hidden',
                'visible' => false,
            ]);

        Sku::query()
            ->whereIn('fulfillment_type', [
                FazerCardsIds::TOPUP,
                FazerCardsIds::STEAM_TOPUP,
                FazerCardsIds::STEAM_GIFT,
                FazerCardsIds::TELEGRAM_STARS,
                FazerCardsIds::TELEGRAM_PREMIUM,
            ])
            ->update([
                'min_order_quantity' => 1,
                'max_order_quantity' => 1,
            ]);

        return [
            'products' => $touched,
            'hidden' => $hidden,
            'schemas' => $schemas,
            'hiddenSteamPacks' => $hiddenSteamPacks,
        ];
    }
}

<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Support\Facades\Schema;

final class ProductNormalizedKey
{
    private static bool $hasColumn = false;

    public const KIND_TOPUP = 'topup';

    public const KIND_GAMEKEY = 'gamekey';

    public const KIND_GIFT = 'gift';

    /** @var list<string> */
    private const ARTICLES = ['the', 'a', 'an'];

    /** @var list<string> */
    private const REGION_TOKENS = [
        'global', 'worldwide', 'ww', 'cis', 'eu', 'europe', 'us', 'usa', 'uk',
        'tr', 'turkey', 'asia', 'latam', 'ru', 'row', 'mena', 'sea',
        'br', 'ar', 'de', 'fr', 'it', 'es', 'pl', 'nz', 'th', 'se',
    ];

    /**
     * @return array{key: string, kind: string, region: string, unique: string}
     */
    public static function fromHandle(string $handle, ?string $family = null, ?string $region = null, bool $stripTrailingId = false): array
    {
        $handle = strtolower(trim($handle));
        $slug = $stripTrailingId ? self::stripTrailingId($handle) : $handle;
        $kind = self::kindFromFamily($family, $handle);
        $regionKey = self::regionKey($region, $handle);
        $key = self::fingerprint($slug);

        return [
            'key' => $key,
            'kind' => $kind,
            'region' => $regionKey,
            'unique' => $kind.'|'.$regionKey.'|'.$key,
        ];
    }

    /**
     * @return array{key: string, kind: string, region: string, unique: string}
     */
    public static function partsFor(Product $product): array
    {
        $family = FazerCardsIds::familyOf($product->fulfillment_type, $product->fazercards_category_id);

        return self::fromHandle(
            (string) $product->handle,
            $family,
            $product->region,
            filled($product->shop2_category_id),
        );
    }

    public static function fingerprint(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = trim($slug, '-');
        $count = 0;
        do {
            $slug = preg_replace('/(\d)-(\d)/', '$1$2', $slug, -1, $count) ?? $slug;
        } while ($count > 0);

        $tokens = array_values(array_filter(
            explode('-', $slug),
            static fn (string $token): bool => $token !== '' && ! in_array($token, self::ARTICLES, true)
        ));

        return implode('', $tokens);
    }

    public static function kindFromFamily(?string $family, string $handle = ''): string
    {
        $family = strtolower(trim((string) $family));
        $handle = strtolower(trim($handle));
        if (
            in_array($family, [FazerCardsIds::TOPUP, FazerCardsIds::STEAM_TOPUP], true)
            || str_starts_with($handle, 'topup-')
            || str_starts_with($handle, 'steam-topup-')
        ) {
            return self::KIND_TOPUP;
        }
        if ($family === FazerCardsIds::GAMEKEY || str_starts_with($handle, 'gamekey-')) {
            return self::KIND_GAMEKEY;
        }

        return self::KIND_GIFT;
    }

    public static function regionKey(?string $region, string $handle = ''): string
    {
        $public = CatalogAttr::publicRegion($region);
        if (is_string($public) && $public !== '') {
            return strtolower($public);
        }
        $tokens = explode('-', strtolower(trim($handle)));
        $last = (string) ($tokens[array_key_last($tokens)] ?? '');
        if ($last !== '' && in_array($last, self::REGION_TOKENS, true)) {
            return in_array($last, ['worldwide', 'ww'], true) ? 'global' : $last;
        }

        return '';
    }

    public static function apply(Product $product): void
    {
        if (! self::$hasColumn) {
            if (! Schema::hasColumn($product->getTable(), 'normalized_key')) {
                return;
            }
            self::$hasColumn = true;
        }
        $parts = self::partsFor($product);
        $product->normalized_key = $parts['key'];
        $product->normalized_kind = $parts['kind'];
        $product->normalized_region = $parts['region'];
        $live = $product->status === 'active'
            && (bool) $product->visible
            && ! filled($product->excluded_reason)
            && $product->substitute_product_id === null;
        if ($live) {
            $taken = Product::query()
                ->where('normalized_unique', $parts['unique'])
                ->when($product->id, fn ($query) => $query->where('id', '!=', $product->id))
                ->exists();
            $product->normalized_unique = $taken
                ? 'id:'.($product->id ?: ('new-'.$parts['key']))
                : $parts['unique'];

            return;
        }
        $product->normalized_unique = $product->id
            ? 'id:'.$product->id
            : 'new:'.$parts['unique'];
    }

    private static function stripTrailingId(string $handle): string
    {
        return preg_replace('/-\d+$/', '', $handle) ?: $handle;
    }
}

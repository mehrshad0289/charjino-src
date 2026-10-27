import { schemaType, sellPriceToman } from '@/lib/buyer-input-fields';
import { formatTomanNumber, tomanPrice } from '@/lib/price';
import type { CatalogProduct, CatalogSku } from '@/types/catalog';

export type OrderableProduct = {
  isOrderable?: boolean;
  available?: boolean;
  temporarilyUnavailable?: boolean;
};

export function isOrderable(product: OrderableProduct): boolean {
  if (typeof product.isOrderable === 'boolean') {
    return product.isOrderable;
  }
  if (product.available === false || product.temporarilyUnavailable) {
    return false;
  }
  return true;
}

/** Last storefront Toman prices still on the record — same numbers for UI and JSON-LD. */
export function catalogOfferPricesToman(product: CatalogProduct, skus: CatalogSku[] = product.skus ?? []): number[] {
  if (schemaType(product) === 'steam-topup') {
    const shown = tomanPrice(product.minPrice);
    return shown > 0 ? [shown] : [];
  }
  const fromSkus = skus.map((sku) => sellPriceToman(sku)).filter((price) => price > 0);
  if (fromSkus.length > 0) {
    return fromSkus;
  }
  const fallback = tomanPrice(product.minPrice);
  return fallback > 0 ? [fallback] : [];
}

export function lastKnownFromPriceLabel(product: CatalogProduct, skus: CatalogSku[] = product.skus ?? []): string | null {
  if (isOrderable(product)) {
    return null;
  }
  const prices = catalogOfferPricesToman(product, skus);
  if (prices.length === 0) {
    return null;
  }
  return `آخرین قیمت: از ${formatTomanNumber(Math.min(...prices))} تومان — فعلاً ناموجود`;
}

import type { CatalogProduct, CatalogSku } from '@/types/catalog';

export type LiveCatalogPrices = {
  minPrice: number | null;
  skus: Array<{
    id: number;
    price: number;
    originalPrice: number | null;
    saleEndsAt: string | null;
  }>;
};

export async function fetchLiveCatalogPrices(handle: string): Promise<LiveCatalogPrices | null> {
  try {
    const response = await fetch(`/api/catalog/prices/${encodeURIComponent(handle)}`, { cache: 'no-store' });
    if (!response.ok) return null;
    const payload = (await response.json()) as { data?: LiveCatalogPrices } & LiveCatalogPrices;
    const data = payload.data ?? payload;
    if (!data || !Array.isArray(data.skus)) return null;
    return {
      minPrice: data.minPrice ?? null,
      skus: data.skus,
    };
  } catch {
    return null;
  }
}

export async function mergeLiveCatalogPrices(
  handle: string,
  product: CatalogProduct,
  skus: CatalogSku[],
): Promise<{ product: CatalogProduct; skus: CatalogSku[] }> {
  const live = await fetchLiveCatalogPrices(handle);
  if (!live) {
    return { product, skus };
  }
  const byId = new Map(live.skus.map((row) => [row.id, row]));
  return {
    product: { ...product, minPrice: live.minPrice ?? product.minPrice },
    skus: skus.map((sku) => {
      const row = byId.get(sku.id);
      return row
        ? { ...sku, price: row.price, originalPrice: row.originalPrice, saleEndsAt: row.saleEndsAt }
        : sku;
    }),
  };
}

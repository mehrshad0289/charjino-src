import { catalogRegionLabel } from '@/lib/catalog-region';
import type { CatalogProduct, CatalogSku, PackFilter, PackFilterOption } from '@/types/catalog';

const ALL = '';

export function packFiltersOf(product: CatalogProduct | null | undefined): PackFilter[] {
  const rows = product?.packFilters;
  return Array.isArray(rows) ? rows.filter((row) => row.options.length > 1) : [];
}

export function optionLabel(filter: PackFilter, option: PackFilterOption) {
  if (filter.key === 'region') {
    return catalogRegionLabel(option.label || option.value) || option.label;
  }
  return option.label || option.value;
}

export function skuMatchesFacets(sku: CatalogSku, facets: Record<string, string>) {
  const values = sku.filters ?? {};
  for (const [key, selected] of Object.entries(facets)) {
    const want = selected.trim();
    if (!want) continue;
    const got = String(values[key] ?? '').trim();
    if (!got || got.toLowerCase() !== want.toLowerCase()) {
      return false;
    }
  }
  return true;
}

export function skuMatchesQuery(sku: CatalogSku, query: string) {
  const needle = query.trim().toLowerCase();
  if (!needle) return true;
  const hay = `${sku.name ?? ''} ${sku.nameEn ?? ''} ${sku.region ?? ''}`.toLowerCase();
  return hay.includes(needle);
}

export function visibleSkus(skus: CatalogSku[], facets: Record<string, string>, query = '') {
  return skus.filter((sku) => skuMatchesFacets(sku, facets) && skuMatchesQuery(sku, query));
}

export function initialFacets(filters: PackFilter[], hinted: Record<string, string | undefined> = {}) {
  const out: Record<string, string> = {};
  for (const filter of filters) {
    const raw = (hinted[filter.key] ?? '').trim();
    if (!raw) {
      out[filter.key] = ALL;
      continue;
    }
    const match = filter.options.find((option) => option.value.toLowerCase() === raw.toLowerCase());
    out[filter.key] = match?.value ?? ALL;
  }
  return out;
}

export type CatalogOriginFilter = 'all' | 'manual' | 'supplier';

/** جداکننده ساخته‌شده و تأمین‌کننده. */

export function productOrigin(
  product: { source?: string | null; fazercardsCategoryId?: string | null; shop2CategoryId?: number | null }
): 'manual' | 'shop2' | 'fazercards' {
  if (product.source === 'fazercards' || product.source === 'shop2' || product.source === 'manual') {
    return product.source;
  }
  if (product.fazercardsCategoryId) return 'fazercards';
  if (product.shop2CategoryId) return 'shop2';
  return 'manual';
}

export function originLabel(source?: string | null): string {
  if (source === 'fazercards') return 'FazerCards';
  if (source === 'shop2') return 'Shop2';
  if (source === 'supplier') return 'تأمین‌کننده';
  return 'ساخته‌شده';
}

export function originBadgeColor(source?: string | null): 'info' | 'warning' | 'light' {
  if (source === 'fazercards') return 'info';
  if (source === 'shop2' || source === 'supplier') return 'warning';
  return 'light';
}

export const ORIGIN_FILTER_OPTIONS = [
  { value: 'all', label: 'همه' },
  { value: 'manual', label: 'ساخته‌شده' },
  { value: 'supplier', label: 'از تأمین‌کننده' }
];

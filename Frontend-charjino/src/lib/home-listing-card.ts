import type { FlashSaleItem, PublicProduct } from '@/types/catalog';

/** Compact cards on production `GET /catalog/home` (flashSale, popular). */
type HomeListingCardFields = {
  slug?: string;
  name?: string;
  image?: string;
  price?: number;
  badge?:
    | {
        originalPrice?: number;
        variant?: string;
        platform?: string;
        region?: string;
        publisher?: string;
      }
    | unknown[];
};

type ListingBadge = Exclude<HomeListingCardFields['badge'], unknown[] | undefined>;

function listingBadge(item: HomeListingCardFields): ListingBadge | null {
  const badge = item.badge;
  if (!badge || Array.isArray(badge)) return null;
  return badge;
}

export function storefrontFlashSaleItem(item: FlashSaleItem): FlashSaleItem {
  const row = item as FlashSaleItem & HomeListingCardFields;
  if (row.productName?.trim()) {
    return item;
  }

  const badge = listingBadge(row);
  const price = row.price ?? row.originalPrice ?? 0;
  const originalPrice =
    typeof badge?.originalPrice === 'number' ? badge.originalPrice : row.originalPrice ?? price;

  return {
    ...item,
    image: (row.image ?? item.image ?? '').trim(),
    originalPrice,
    price,
    productCode: item.productCode ?? '',
    productHandle: (row.slug ?? row.productHandle ?? '').trim(),
    productName: (row.name ?? row.productName ?? '').trim(),
    productVariantCode: item.productVariantCode ?? '',
    productVariantName: (badge?.variant ?? row.productVariantName ?? '').trim(),
  };
}

export function storefrontHomeListingProduct(product: PublicProduct): PublicProduct {
  const item = product as PublicProduct & HomeListingCardFields;
  if (item.title?.trim() && item.handle?.trim()) {
    return product;
  }

  const badge = listingBadge(item);

  return {
    ...product,
    handle: (item.handle || item.slug || '').trim(),
    title: (item.title || item.name || '').trim(),
    thumbnail: (item.thumbnail || item.image || '').trim(),
    minPrice: item.minPrice ?? item.price ?? null,
    publisher:
      item.publisher ||
      (typeof badge?.publisher === 'string' ? badge.publisher : '') ||
      '',
    platform:
      item.platform ??
      (typeof badge?.platform === 'string' ? badge.platform : item.platform),
    region:
      item.region ?? (typeof badge?.region === 'string' ? badge.region : item.region),
  };
}

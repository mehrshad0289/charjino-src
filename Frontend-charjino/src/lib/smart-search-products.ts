import type { PageMeta, PublicProduct } from '@/types/catalog';
import type { SmartSearchProduct, SmartSearchResponse } from '@/types/smart-search';
import { isSmartSearchProduct } from '@/types/smart-search';

export function toPublicProductFromSmartSearch(item: SmartSearchProduct): PublicProduct {
  return {
    id: item.id ?? 0,
    handle: item.handle,
    title: item.title,
    titleEn: item.title_en ?? null,
    thumbnail: item.thumbnail || '/image/catalog/pubg-mobile.svg',
    categoryName: item.group || item.categoryName || '',
    code: item.handle,
    inputs: [],
    isPopular: false,
    publisher: '',
    subtitle: item.matched_package_label ?? '',
    description: null,
    minPrice: item.minPrice ?? null,
    platform: item.platform ?? null,
  };
}

export function productsFromSmartSearch(items: SmartSearchResponse['items']): PublicProduct[] {
  return items.filter(isSmartSearchProduct).map(toPublicProductFromSmartSearch);
}

export function pageMetaFromSmartSearch(
  pagination: SmartSearchResponse['pagination'],
  pageItems: number,
  perPage: number,
): PageMeta {
  const page = Math.max(1, pagination.current_page);
  const totalPages = Math.max(1, pagination.last_page);
  const total = pagination.has_more_pages
    ? Math.max(page * perPage, (totalPages - 1) * perPage + 1)
    : (page - 1) * perPage + pageItems;

  return {
    page,
    perPage,
    total,
    totalPages,
    platforms: [],
    regions: [],
  };
}

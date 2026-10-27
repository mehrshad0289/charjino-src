export const CATALOG_HOME_REVALIDATE = 3600;
export const CATALOG_LIST_REVALIDATE = 3600;
export const CATALOG_PRODUCT_REVALIDATE = 3600;

export const CATALOG_HOME_TAG = 'catalog-home';
export const CATALOG_LIST_TAG = 'catalog-list';
export const SITEMAP_TAG = 'sitemap';

export const PRERENDER_PRODUCT_LIMIT = 200;

export function productCacheTag(handle: string) {
  return `product:${handle.trim()}`;
}

export function isProductCacheTag(tag: string) {
  return /^product:[a-z0-9]+(?:-[a-z0-9]+)*$/i.test(tag);
}

export function buyGuideCategoryTag(category: string) {
  return `buy-guide:cat:${category.trim()}`;
}

export function buyGuideSubcategoryTag(category: string, subcategory: string) {
  return `buy-guide:sub:${category.trim()}:${subcategory.trim()}`;
}

export function isBuyGuideCacheTag(tag: string) {
  return /^buy-guide:(?:cat:[a-z0-9-]+|sub:[a-z0-9-]+:[a-z0-9-]+)$/i.test(tag);
}

export function isBlogCacheTag(tag: string) {
  return tag === 'blog' || /^blog:(?:post|category|tag|author):[^\s]+$/i.test(tag);
}

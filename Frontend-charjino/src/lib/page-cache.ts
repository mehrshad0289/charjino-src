export const PAGE_REVALIDATE = 3600;
export const PAGE_CACHE_CONTROL = 'public, s-maxage=3600, stale-while-revalidate=86400';

export function isPublicPageCachePath(path: string) {
  return path === '/' || path.startsWith('/catalog/');
}

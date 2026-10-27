import { canonicalPath } from '@/lib/site';

export type CatalogBrowseSort = 'listed' | 'new' | 'price';

export type CatalogBrowseQuery = {
  category?: string;
  q?: string;
  platform?: string;
  region?: string;
  page?: string;
  sort?: string;
};

export const BROWSE_SORT_OPTIONS: { value: CatalogBrowseSort; label: string }[] = [
  { value: 'listed', label: 'پرفروش' },
  { value: 'new', label: 'جدید' },
  { value: 'price', label: 'ارزان‌ترین' },
];

export function parseBrowseQuery(query: CatalogBrowseQuery) {
  const sortRaw = query.sort?.trim();
  const sort: CatalogBrowseSort = sortRaw === 'new' || sortRaw === 'price' ? sortRaw : 'listed';
  return {
    category: query.category?.trim() ?? '',
    q: query.q?.trim() ?? '',
    platform: query.platform?.trim() ?? '',
    region: query.region?.trim() ?? '',
    page: Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1),
    sort,
  };
}

export type ParsedBrowseQuery = ReturnType<typeof parseBrowseQuery>;

export function isFacetedBrowse(query: ParsedBrowseQuery) {
  return Boolean(query.q);
}

/** sort / platform / region are variants of the same listing; canonical is the clean URL. */
export function hasBrowseFilterParams(query: ParsedBrowseQuery) {
  return Boolean(query.platform || query.region || query.sort !== 'listed');
}

export function isBrowsePagination(query: ParsedBrowseQuery) {
  return query.page > 1;
}

export function browseShouldNoindex(query: ParsedBrowseQuery) {
  return isBrowsePagination(query) || isFacetedBrowse(query) || hasBrowseFilterParams(query);
}

/** Canonical is always the clean category/hub path: no page, no filter params. */
export function browseCanonicalPath(pathname: string, _query?: ParsedBrowseQuery) {
  return canonicalPath(pathname);
}

export function isBrowsePageOutOfRange(page: number, totalPages: number) {
  return page > Math.max(1, totalPages);
}

export function categoryFromBrowsePath(pathname: string) {
  const match = /^\/catalog\/category(?:\/([^/]+))?$/.exec(pathname);
  if (!match) return '';
  return match[1] ? decodeURIComponent(match[1]) : '';
}

export function browseQueryEquals(
  a: Pick<ParsedBrowseQuery, 'q' | 'platform' | 'region' | 'sort' | 'page'>,
  b: Pick<ParsedBrowseQuery, 'q' | 'platform' | 'region' | 'sort' | 'page'>,
) {
  return (
    a.q === b.q &&
    a.platform === b.platform &&
    a.region === b.region &&
    a.sort === b.sort &&
    a.page === b.page
  );
}

export function browseSortParam(sort: CatalogBrowseSort) {
  return sort === 'listed' ? '' : sort;
}

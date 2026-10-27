'use server';

import { cache } from 'react';
import { apiPaths } from '@/lib/api-paths';
import {
  CATALOG_HOME_REVALIDATE,
  CATALOG_HOME_TAG,
  CATALOG_LIST_REVALIDATE,
  CATALOG_LIST_TAG,
  CATALOG_PRODUCT_REVALIDATE,
  buyGuideCategoryTag,
  productCacheTag,
} from '@/lib/catalog-cache';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import type {
  CatalogHome,
  CatalogProduct,
  PageMeta,
  PublicCategory,
  PublicCategoryPage,
  PublicProduct,
  PublicProductPage,
} from '@/types/catalog';
import { pageMetaFromSmartSearch, productsFromSmartSearch } from '@/lib/smart-search-products';
import type { SmartSearchResponse } from '@/types/smart-search';

function toActionMessage(error: unknown) {
  if (error instanceof ApiError && error.message) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return 'درخواست انجام نشد';
}

function redirectTo(error: unknown): string | null {
  if (!(error instanceof ApiError) || (error.statusCode !== 301 && error.statusCode !== 308)) {
    return null;
  }
  const data = error.data;
  if (data && typeof data === 'object' && 'to' in data && typeof data.to === 'string' && data.to.startsWith('/')) {
    return data.to;
  }
  return null;
}

export const getCatalogHome = cache(async function getCatalogHome() {
  try {
    const data = await serverFetch<CatalogHome>(apiPaths.catalogHome, {
      auth: false,
      next: { revalidate: CATALOG_HOME_REVALIDATE, tags: [CATALOG_HOME_TAG] },
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
});

export const getCatalogCategories = cache(async function getCatalogCategories() {
  try {
    const items = await serverFetch<PublicCategory[]>(apiPaths.catalogCategories, {
      auth: false,
      next: { revalidate: CATALOG_LIST_REVALIDATE, tags: [CATALOG_LIST_TAG] },
    });
    return { ok: true as const, items: Array.isArray(items) ? items : [] };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error), items: [] as PublicCategory[] };
  }
});

export const getCatalogCategory = cache(async function getCatalogCategory(slug: string, page = 1) {
  const normalized = slug.trim();
  if (!normalized) {
    return { ok: false as const, message: 'شناسه دسته نامعتبر است', notFound: true as const };
  }

  try {
    const data = await serverFetch<PublicCategoryPage>(apiPaths.catalogCategory(normalized, page), {
      auth: false,
      next: { revalidate: CATALOG_LIST_REVALIDATE, tags: [CATALOG_LIST_TAG] },
    });
    return { ok: true as const, data };
  } catch (error) {
    const status = error instanceof ApiError ? error.statusCode : 0;
    return {
      ok: false as const,
      message: toActionMessage(error),
      notFound: status === 404,
    };
  }
});

export const getCatalogProduct = cache(async function getCatalogProduct(handle: string) {
  const normalized = handle.trim();
  if (!normalized) {
    return { ok: false as const, message: 'شناسه محصول نامعتبر است', notFound: true as const };
  }

  try {
    const data = await serverFetch<CatalogProduct>(apiPaths.catalogProduct(normalized), {
      auth: false,
      next: {
        revalidate: CATALOG_PRODUCT_REVALIDATE,
        tags: [
          CATALOG_LIST_TAG,
          productCacheTag(normalized),
          buyGuideCategoryTag('game-topup'),
          buyGuideCategoryTag('gift-card'),
          buyGuideCategoryTag('game-keys'),
          buyGuideCategoryTag('telegram'),
          buyGuideCategoryTag('steam-wallet'),
          buyGuideCategoryTag('steam-gifts'),
        ],
      },
    });
    return { ok: true as const, data };
  } catch (error) {
    const to = redirectTo(error);
    if (to) {
      return { ok: false as const, message: toActionMessage(error), redirectTo: to };
    }
    const status = error instanceof ApiError ? error.statusCode : 0;
    return {
      ok: false as const,
      message: toActionMessage(error),
      notFound: status === 404,
    };
  }
});

export async function getCatalogProducts() {
  try {
    const items = await serverFetch<PublicProduct[]>(apiPaths.catalogProducts, {
      auth: false,
      next: { revalidate: CATALOG_LIST_REVALIDATE, tags: [CATALOG_LIST_TAG] },
    });
    return { ok: true as const, items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

type CatalogProductListQuery = {
  q?: string;
  category?: string;
  page?: number;
  perPage?: number;
  platform?: string;
  region?: string;
  sort?: string;
  popular?: boolean;
};

function fallbackMeta(page: number, perPage: number, total: number): PageMeta {
  return {
    page,
    perPage,
    total,
    totalPages: Math.max(1, Math.ceil(total / Math.max(1, perPage))),
  };
}

function asProductPage(
  data: PublicProduct[] | PublicProductPage,
  page: number,
  perPage: number,
): PublicProductPage {
  if (Array.isArray(data)) {
    return { items: data, meta: fallbackMeta(1, data.length || perPage, data.length) };
  }
  return {
    items: Array.isArray(data.items) ? data.items : [],
    meta: data.meta ?? fallbackMeta(page, perPage, 0),
  };
}

export async function listShopBrowseProducts(query: CatalogProductListQuery = {}) {
  const needle = (query.q ?? '').trim().slice(0, 100);
  if (needle.length >= 2) {
    const page = Math.max(1, query.page ?? 1);
    const perPage = Math.min(100, Math.max(1, query.perPage ?? 24));
    const search = await getSmartSearchResults({
      q: needle,
      page,
      perPage,
      group: query.category,
      platform: query.platform,
      region: query.region,
    });
    if (!search.ok) return search;
    const items = productsFromSmartSearch(search.data.items);
    return {
      ok: true as const,
      data: {
        items,
        meta: pageMetaFromSmartSearch(search.data.pagination, items.length, perPage),
      },
    };
  }

  return listCatalogProducts({
    category: query.category,
    page: query.page,
    perPage: query.perPage,
    platform: query.platform,
    region: query.region,
    sort: query.sort,
    popular: query.popular,
  });
}

function catalogListCacheKey(query: CatalogProductListQuery) {
  return JSON.stringify({
    q: query.q ?? '',
    category: query.category ?? '',
    page: Math.max(1, query.page ?? 1),
    perPage: Math.min(100, Math.max(1, query.perPage ?? 20)),
    popular: Boolean(query.popular),
    platform: query.platform ?? '',
    region: query.region ?? '',
    sort: query.sort ?? '',
  });
}

export async function listCatalogProducts(query: CatalogProductListQuery = {}) {
  return listCatalogProductsCached(catalogListCacheKey(query));
}

const listCatalogProductsCached = cache(async function listCatalogProductsCached(key: string) {
  const query = JSON.parse(key) as Required<Pick<CatalogProductListQuery, 'page' | 'perPage'>> & CatalogProductListQuery;
  const page = query.page;
  const perPage = query.perPage;
  try {
    const data = await serverFetch<PublicProduct[] | PublicProductPage>(
      apiPaths.catalogProductsQuery({
        q: query.q || undefined,
        category: query.category || undefined,
        page,
        perPage,
        popular: query.popular || undefined,
        platform: query.platform || undefined,
        region: query.region || undefined,
        sort: query.sort || undefined,
      }),
      {
        auth: false,
        next: { revalidate: CATALOG_LIST_REVALIDATE, tags: [CATALOG_LIST_TAG] },
      },
    );
    return { ok: true as const, data: asProductPage(data, page, perPage) };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
});

export async function searchCatalogProducts(query: string) {
  const q = query.trim();
  try {
    const items = await serverFetch<PublicProduct[]>(
      apiPaths.catalogProductsQuery(q ? { q } : { popular: true }),
      { auth: false },
    );
    return { ok: true as const, items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getSmartSearchResults(params: {
  q: string;
  page?: number;
  perPage?: number;
  group?: string;
  platform?: string;
  region?: string;
}) {
  const q = params.q.trim().slice(0, 100);
  if (q.length < 2) {
    return {
      ok: true as const,
      data: {
        mode: 'empty' as const,
        query: q,
        items: [],
        pagination: { current_page: 1, last_page: 1, has_more_pages: false },
      },
    };
  }
  try {
    const data = await serverFetch<SmartSearchResponse>(
      apiPaths.catalogSmartSearch({
        q,
        page: params.page,
        perPage: params.perPage ?? 24,
        mode: 'products',
        group: params.group,
        platform: params.platform,
        region: params.region,
      }),
      { auth: false },
    );
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

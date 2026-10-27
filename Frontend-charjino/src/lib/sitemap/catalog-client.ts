import { getBackendUrl } from '@/lib/env';
import { STEAM_GIFTS_CATEGORY } from '@/lib/seo-rules';
import type { PageMeta, PublicCategory, PublicProduct } from '@/types/catalog';
import {
  SITEMAP_API_CONCURRENCY,
  SITEMAP_API_PER_PAGE,
  SITEMAP_API_RETRIES,
  SITEMAP_API_TIMEOUT_MS,
  SITEMAP_PRODUCT_SORT,
} from './config';

type ProductPage = {
  items: PublicProduct[];
  meta: PageMeta;
};

export type SitemapProductQuery = {
  category?: string;
  excludeCategory?: string;
  indexable?: boolean;
};

function sleep(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function productQueryString(page: number, query: SitemapProductQuery = {}) {
  const params = new URLSearchParams({
    page: String(page),
    perPage: String(SITEMAP_API_PER_PAGE),
    sort: SITEMAP_PRODUCT_SORT,
  });
  if (query.category) {
    params.set('category', query.category);
  }
  if (query.excludeCategory) {
    params.set('excludeCategory', query.excludeCategory);
  }
  if (query.indexable === true) {
    params.set('indexable', '1');
  }
  if (query.indexable === false) {
    params.set('indexable', '0');
  }
  return params.toString();
}

async function catalogGet<T>(path: string): Promise<T> {
  const url = `${getBackendUrl()}/${path.replace(/^\//, '')}`;
  let lastError: unknown;
  for (let attempt = 0; attempt <= SITEMAP_API_RETRIES; attempt++) {
    try {
      const response = await fetch(url, {
        cache: 'no-store',
        signal: AbortSignal.timeout(SITEMAP_API_TIMEOUT_MS),
        headers: { Accept: 'application/json' },
      });
      const payload = (await response.json()) as { success?: boolean; data?: T; message?: string };
      if (!response.ok || payload.success === false) {
        throw new Error(payload.message || `catalog ${response.status}`);
      }
      return (payload.data ?? payload) as T;
    } catch (error) {
      lastError = error;
      if (attempt < SITEMAP_API_RETRIES) {
        await sleep(300 * (attempt + 1) * (attempt + 1));
      }
    }
  }
  throw lastError instanceof Error ? lastError : new Error('catalog request failed');
}

export async function fetchCategories(): Promise<PublicCategory[]> {
  const data = await catalogGet<PublicCategory[]>('catalog/categories');
  return Array.isArray(data) ? data : [];
}

export async function fetchBlogSitemap(): Promise<{
  categories: { slug: string }[];
  posts: { slug: string; publishedAt?: string | null }[];
}> {
  try {
    const [categoriesPayload, first] = await Promise.all([
      catalogGet<{ items?: { slug?: string }[] }>('blog/categories'),
      catalogGet<{
        items?: { slug?: string; publishedAt?: string | null }[];
        meta?: { totalPages?: number };
      }>('blog/posts?page=1&perPage=100'),
    ]);
    const categories = (categoriesPayload.items ?? [])
      .map((item) => item.slug?.trim())
      .filter((slug): slug is string => Boolean(slug))
      .map((slug) => ({ slug }));
    const posts = [...(first.items ?? [])];
    const lastPage = Math.min(20, Math.max(1, first.meta?.totalPages ?? 1));
    for (let page = 2; page <= lastPage; page += 1) {
      const next = await catalogGet<{ items?: { slug?: string; publishedAt?: string | null }[] }>(
        `blog/posts?page=${page}&perPage=100`,
      );
      posts.push(...(next.items ?? []));
    }
    return {
      categories,
      posts: posts
        .map((item) => ({ slug: item.slug?.trim() ?? '', publishedAt: item.publishedAt }))
        .filter((item) => item.slug !== ''),
    };
  } catch {
    return { categories: [], posts: [] };
  }
}

export async function fetchProductPage(page: number, query: SitemapProductQuery = {}): Promise<ProductPage> {
  const data = await catalogGet<PublicProduct[] | ProductPage>(`catalog/products?${productQueryString(page, query)}`);
  if (Array.isArray(data)) {
    return {
      items: data,
      meta: {
        page: 1,
        perPage: data.length || SITEMAP_API_PER_PAGE,
        total: data.length,
        totalPages: 1,
      },
    };
  }
  return {
    items: Array.isArray(data.items) ? data.items : [],
    meta: data.meta ?? {
      page,
      perPage: SITEMAP_API_PER_PAGE,
      total: 0,
      totalPages: 1,
    },
  };
}

export async function fetchProductTotal(query: SitemapProductQuery = {}): Promise<number> {
  const first = await fetchProductPage(1, query);
  return Math.max(0, first.meta.total);
}

export function steamGiftsQuery(): SitemapProductQuery {
  return { category: STEAM_GIFTS_CATEGORY, indexable: true };
}

export function mainProductsQuery(): SitemapProductQuery {
  return { excludeCategory: STEAM_GIFTS_CATEGORY };
}

async function mapPool<T, R>(items: T[], fn: (item: T) => Promise<R>): Promise<R[]> {
  const out: R[] = new Array(items.length);
  let cursor = 0;
  async function worker() {
    while (cursor < items.length) {
      const index = cursor;
      cursor += 1;
      out[index] = await fn(items[index]);
    }
  }
  const workers = Math.min(SITEMAP_API_CONCURRENCY, items.length);
  await Promise.all(Array.from({ length: workers }, () => worker()));
  return out;
}

export async function fetchProductPages(
  fromPage: number,
  toPage: number,
  query: SitemapProductQuery = {},
): Promise<PublicProduct[]> {
  if (toPage < fromPage) {
    return [];
  }
  const pages = Array.from({ length: toPage - fromPage + 1 }, (_, i) => fromPage + i);
  const batches = await mapPool(pages, async (page) => {
    const result = await fetchProductPage(page, query);
    await sleep(20);
    return result.items;
  });
  return batches.flat();
}

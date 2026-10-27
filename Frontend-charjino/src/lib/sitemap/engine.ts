import { ROUTES } from '@/lib/routes';
import { isIndexableProduct, isSteamGiftProduct } from '@/lib/seo-rules';
import { absoluteUrl } from '@/lib/site';
import { SITEMAP_API_PER_PAGE, SITEMAP_CHUNK_SIZE, SITEMAP_FRESH_MS, SITEMAP_REVALIDATE_DEBOUNCE_MS } from './config';
import {
  fetchCategories,
  fetchProductPages,
  fetchProductTotal,
  mainProductsQuery,
  steamGiftsQuery,
  type SitemapProductQuery,
} from './catalog-client';
import { markStale, readChunk, writeChunk, type CachedChunk } from './file-cache';
import { parseSitemapFileId, productFileIds, sitemapFileId, type SitemapKind } from './ids';
import { chunkPublicUrl, sitemapIndexXml, sitemapLastmod, urlsetXml } from './xml';

const inflight = new Map<string, Promise<CachedChunk>>();
let warmupStarted = false;
let revalidateTimer: ReturnType<typeof setTimeout> | null = null;
let revalidateQueued: { handle?: string; action?: string } = {};

const STATIC_PATHS = [
  ROUTES.home,
  ROUTES.categories,
  ROUTES.about,
  ROUTES.contact,
  ROUTES.terms,
  ROUTES.privacy,
];

function chunkKey(id: string) {
  return `chunk-${id}`;
}

function indexKey() {
  return 'index';
}

function isFresh(chunk: CachedChunk | null) {
  return Boolean(chunk && chunk.generatedAt > 0 && Date.now() - chunk.generatedAt < SITEMAP_FRESH_MS);
}

function isProductionBuild() {
  return process.env.NEXT_PHASE === 'phase-production-build';
}

function latestLastmod(values: Array<string | null | undefined>): string | null {
  const dates = values.filter((value): value is string => Boolean(value));
  if (dates.length === 0) {
    return null;
  }
  return dates.sort().at(-1) ?? null;
}

async function buildCategoriesChunk(): Promise<CachedChunk> {
  const locs: string[] = STATIC_PATHS.map((path) => absoluteUrl(path));
  const lastmods: Array<string | null> = STATIC_PATHS.map(() => null);
  const categories = await fetchCategories();
  const categoryLastmods: string[] = [];
  let excluded = 0;
  for (const category of categories) {
    const slug = category.slug?.trim();
    if (!slug || !category.title?.trim()) {
      excluded += 1;
      continue;
    }
    const lastmod = sitemapLastmod(category.updatedAt) ?? null;
    locs.push(absoluteUrl(ROUTES.category(slug)));
    lastmods.push(lastmod);
    if (lastmod) {
      categoryLastmods.push(lastmod);
    }
  }
  const hubLastmod = latestLastmod(categoryLastmods);
  const hubIndex = STATIC_PATHS.indexOf(ROUTES.categories);
  if (hubIndex >= 0 && hubLastmod) {
    lastmods[hubIndex] = hubLastmod;
  }
  return { generatedAt: Date.now(), locs, lastmods, excluded, lastError: null };
}

async function buildProductKindChunk(kind: 'products' | 'steam-gifts', chunk: number, total: number): Promise<CachedChunk> {
  const query: SitemapProductQuery = kind === 'steam-gifts' ? steamGiftsQuery() : mainProductsQuery();
  const fromOffset = (chunk - 1) * SITEMAP_CHUNK_SIZE;
  const fromPage = Math.floor(fromOffset / SITEMAP_API_PER_PAGE) + 1;
  const toOffset = Math.min(total, fromOffset + SITEMAP_CHUNK_SIZE);
  const toPage = Math.ceil(toOffset / SITEMAP_API_PER_PAGE);
  const products = await fetchProductPages(fromPage, toPage, query);
  const slice = products.slice(0, SITEMAP_CHUNK_SIZE);
  const locs: string[] = [];
  const lastmods: Array<string | null> = [];
  let excluded = 0;
  for (const product of slice) {
    const steamGift = isSteamGiftProduct(product);
    if ((kind === 'steam-gifts' && !steamGift) || (kind === 'products' && steamGift) || !isIndexableProduct(product)) {
      excluded += 1;
      continue;
    }
    locs.push(absoluteUrl(ROUTES.catalog(product.handle)));
    lastmods.push(sitemapLastmod(product.updatedAt) ?? null);
  }
  return { generatedAt: Date.now(), locs, lastmods, excluded, lastError: null };
}

async function rebuild(key: string, builder: () => Promise<CachedChunk>): Promise<CachedChunk> {
  const running = inflight.get(key);
  if (running) {
    return running;
  }
  const work = builder()
    .then((chunk) => {
      writeChunk(key, chunk);
      return chunk;
    })
    .catch((error) => {
      console.error('sitemap rebuild failed', key, error);
      const previous = readChunk(key);
      const lastError = error instanceof Error ? error.message : 'rebuild failed';
      const retryAt = Date.now() - SITEMAP_FRESH_MS + 30_000;
      if (previous && previous.locs.length > 0) {
        writeChunk(key, {
          ...previous,
          generatedAt: retryAt,
          lastError,
        });
        return previous;
      }
      const empty: CachedChunk = {
        generatedAt: retryAt,
        locs: key === chunkKey('categories') ? STATIC_PATHS.map((path) => absoluteUrl(path)) : [],
        excluded: 0,
        lastError,
      };
      writeChunk(key, empty);
      return empty;
    })
    .finally(() => {
      inflight.delete(key);
    });
  inflight.set(key, work);
  return work;
}

async function refreshIndex(): Promise<CachedChunk> {
  const [productsTotal, steamGiftsTotal] = await Promise.all([
    fetchProductTotal(mainProductsQuery()),
    fetchProductTotal(steamGiftsQuery()),
  ]);
  const files = [
    sitemapFileId('categories'),
    ...productFileIds('products', productsTotal, SITEMAP_CHUNK_SIZE),
    ...productFileIds('steam-gifts', steamGiftsTotal, SITEMAP_CHUNK_SIZE),
  ];
  return {
    generatedAt: Date.now(),
    locs: files,
    excluded: 0,
    lastError: null,
    meta: { productsTotal, steamGiftsTotal },
  };
}

function indexFileIds(chunk: CachedChunk | null): string[] | null {
  if (!chunk?.locs.length) {
    return null;
  }
  const parsed = chunk.locs.map((id) => parseSitemapFileId(id)).filter(Boolean);
  if (parsed.length !== chunk.locs.length) {
    return null;
  }
  return chunk.locs;
}

export async function listSitemapIds(): Promise<string[]> {
  if (isProductionBuild()) {
    return [sitemapFileId('categories')];
  }
  try {
    const cached = readChunk(indexKey());
    const cachedIds = indexFileIds(cached);
    if (isFresh(cached) && cachedIds) {
      return cachedIds;
    }
    if (cached && cachedIds) {
      void rebuild(indexKey(), refreshIndex);
      return cachedIds;
    }
    const next = await rebuild(indexKey(), refreshIndex);
    return indexFileIds(next) ?? [sitemapFileId('categories')];
  } catch {
    const cached = readChunk(indexKey());
    return indexFileIds(cached) ?? [sitemapFileId('categories')];
  }
}

export async function generateSitemaps() {
  const ids = await listSitemapIds();
  return ids.map((id) => ({ id }));
}

function productTotalFor(kind: 'products' | 'steam-gifts', index: CachedChunk | null): number {
  if (kind === 'steam-gifts') {
    return index?.meta?.steamGiftsTotal ?? 0;
  }
  return index?.meta?.productsTotal ?? 0;
}

export async function getChunk(id: string): Promise<CachedChunk> {
  const parsed = parseSitemapFileId(id);
  if (!parsed) {
    return { generatedAt: Date.now(), locs: [], excluded: 0, lastError: 'unknown sitemap' };
  }
  const fileId = sitemapFileId(parsed.kind, parsed.chunk);
  const key = chunkKey(fileId);
  const cached = readChunk(key);
  if (isFresh(cached) && cached) {
    return cached;
  }
  const builder = async () => {
    if (parsed.kind === 'categories') {
      return buildCategoriesChunk();
    }
    const index = readChunk(indexKey());
    const total = productTotalFor(parsed.kind, index) || (await fetchProductTotal(parsed.kind === 'steam-gifts' ? steamGiftsQuery() : mainProductsQuery()));
    return buildProductKindChunk(parsed.kind, parsed.chunk, total);
  };
  if (cached && cached.locs.length > 0) {
    void rebuild(key, builder);
    return cached;
  }
  return rebuild(key, builder);
}

export async function sitemapXmlForId(id: string): Promise<string> {
  const chunk = await getChunk(id);
  return urlsetXml(
    chunk.locs.map((loc, index) => ({
      loc,
      lastmod: chunk.lastmods?.[index] || undefined,
    })),
  );
}

export function fallbackSitemapIndexXml(): string {
  const cached = readChunk(indexKey());
  const ids = indexFileIds(cached);
  if (ids && ids.length > 0) {
    return sitemapIndexXml([
      ...ids.map((id) => ({ loc: chunkPublicUrl(id) })),
      { loc: absoluteUrl('/blog/sitemap.xml') },
    ]);
  }
  return sitemapIndexXml([
    { loc: chunkPublicUrl(sitemapFileId('categories')) },
    { loc: absoluteUrl('/blog/sitemap.xml') },
  ]);
}

export async function sitemapIndexDocument(): Promise<string> {
  try {
    const ids = await listSitemapIds();
    return sitemapIndexXml([
      ...ids.map((id) => {
        const chunk = readChunk(chunkKey(id));
        return {
          loc: chunkPublicUrl(id),
          lastmod: latestLastmod(chunk?.lastmods ?? []) ?? undefined,
        };
      }),
      { loc: absoluteUrl('/blog/sitemap.xml') },
    ]);
  } catch (error) {
    console.error('sitemap index document failed; using last-good copy', error);
    return fallbackSitemapIndexXml();
  }
}

export type SitemapStatus = {
  chunks: Array<{
    id: string;
    kind: SitemapKind | null;
    urls: number;
    excluded: number;
    generatedAt: number | null;
    ageMs: number | null;
    lastError: string | null;
  }>;
  productTotalCached: number | null;
  steamGiftsTotalCached: number | null;
  productChunks: number;
};

export async function sitemapStatus(): Promise<SitemapStatus> {
  const ids = await listSitemapIds();
  const index = readChunk(indexKey());
  const chunks = ids.map((id) => {
    const chunk = readChunk(chunkKey(id));
    return {
      id,
      kind: parseSitemapFileId(id)?.kind ?? null,
      urls: chunk?.locs.length ?? 0,
      excluded: chunk?.excluded ?? 0,
      generatedAt: chunk?.generatedAt && chunk.generatedAt > 0 ? chunk.generatedAt : null,
      ageMs: chunk?.generatedAt && chunk.generatedAt > 0 ? Date.now() - chunk.generatedAt : null,
      lastError: chunk?.lastError ?? null,
    };
  });
  return {
    chunks,
    productTotalCached: index?.meta?.productsTotal ?? null,
    steamGiftsTotalCached: index?.meta?.steamGiftsTotal ?? null,
    productChunks: ids.filter((id) => parseSitemapFileId(id)?.kind !== 'categories').length,
  };
}

function staleKeysFor(handle?: string, action?: string) {
  const keys = [indexKey(), chunkKey(sitemapFileId('categories'))];
  const index = readChunk(indexKey());
  const ids = indexFileIds(index) ?? [];
  if (action === 'create' || action === 'created' || !handle) {
    for (const id of ids) {
      keys.push(chunkKey(id));
    }
    return Array.from(new Set(keys));
  }
  const needle = absoluteUrl(ROUTES.catalog(handle));
  let found = false;
  for (const id of ids) {
    const chunk = readChunk(chunkKey(id));
    if (chunk?.locs.includes(needle)) {
      keys.push(chunkKey(id));
      found = true;
    }
  }
  if (!found) {
    const kind: SitemapKind = handle.startsWith('steam-gift-') ? 'steam-gifts' : 'products';
    const kindIds = ids.filter((id) => parseSitemapFileId(id)?.kind === kind);
    keys.push(chunkKey(kindIds.at(-1) || sitemapFileId(kind)));
  }
  return Array.from(new Set(keys));
}

export function requestSitemapRevalidate(input: { handle?: string; action?: string } = {}) {
  revalidateQueued = { ...revalidateQueued, ...input };
  if (revalidateTimer) {
    clearTimeout(revalidateTimer);
  }
  revalidateTimer = setTimeout(() => {
    const queued = revalidateQueued;
    revalidateQueued = {};
    revalidateTimer = null;
    for (const key of staleKeysFor(queued.handle, queued.action)) {
      markStale(key);
    }
    void listSitemapIds()
      .then(async (ids) => {
        await getChunk(sitemapFileId('categories'));
        const last = ids.at(-1);
        if (last && last !== sitemapFileId('categories')) {
          await getChunk(last);
        }
      })
      .catch(() => undefined);
  }, SITEMAP_REVALIDATE_DEBOUNCE_MS);
}

export function startSitemapWarmup() {
  if (warmupStarted || isProductionBuild() || process.env.NEXT_RUNTIME === 'edge') {
    return;
  }
  warmupStarted = true;
  void (async () => {
    await new Promise((resolve) => setTimeout(resolve, 2000));
    try {
      const ids = await listSitemapIds();
      for (const id of ids) {
        await getChunk(id);
        await new Promise((resolve) => setTimeout(resolve, 400));
      }
    } catch {
      /* keep serving last good copies */
    }
  })();
}

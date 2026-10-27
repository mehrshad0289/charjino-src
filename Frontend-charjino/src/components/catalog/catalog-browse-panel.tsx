'use client';

import { useCallback, useEffect, useLayoutEffect, useRef, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { listShopBrowseProducts } from '@/actions/catalog.actions';
import { CatalogBrowseSkeleton, catalogBrowseGridClass } from '@/components/catalog/catalog-browse-skeleton';
import { CatalogBrowseToolbar } from '@/components/catalog/catalog-browse-toolbar';
import { CatalogPagination } from '@/components/catalog/catalog-pagination';
import { CatalogProductCard } from '@/components/catalog/catalog-product-card';
import { Button } from '@/components/ui';
import {
  browseQueryEquals,
  categoryFromBrowsePath,
  parseBrowseQuery,
  type CatalogBrowseSort,
} from '@/lib/catalog-browse';
import {
  BROWSE_PER_PAGE,
  catalogSubcategoryLabel,
  gameKeyPlatformLabel,
  resolveGameKeyBrowsePlatform,
} from '@/lib/catalog-family';
import { catalogRegionLabel } from '@/lib/catalog-region';
import { catalogBrowseHref } from '@/lib/routes';
import type { PageMeta, PublicCategory, PublicProduct } from '@/types/catalog';

type CatalogBrowsePanelProps = {
  categories: PublicCategory[];
  category: string;
  q: string;
  platform: string;
  region: string;
  sort: CatalogBrowseSort;
  page: number;
  items: PublicProduct[];
  meta: PageMeta;
  listOk: boolean;
  listMessage?: string;
};

function isSameBrowseHref(href: string) {
  if (typeof window === 'undefined') return false;
  const next = new URL(href, window.location.origin);
  return next.pathname === window.location.pathname && next.search === window.location.search;
}

function browseFromLocation(category: string) {
  const params = new URLSearchParams(window.location.search);
  return parseBrowseQuery({
    category,
    q: params.get('q') ?? '',
    platform: params.get('platform') ?? '',
    region: params.get('region') ?? '',
    page: params.get('page') ?? '1',
    sort: params.get('sort') ?? '',
  });
}

function isDefaultBrowse(query: ReturnType<typeof parseBrowseQuery>) {
  return !query.q && !query.platform && !query.region && query.page <= 1 && query.sort === 'listed';
}

export function CatalogBrowsePanel({
  categories,
  category,
  q,
  platform,
  region,
  sort,
  page,
  items,
  meta,
  listOk,
  listMessage,
}: CatalogBrowsePanelProps) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [filtering, setFiltering] = useState(false);
  const [extra, setExtra] = useState<PublicProduct[]>([]);
  const [loadingMore, setLoadingMore] = useState(false);
  const [holdHeight, setHoldHeight] = useState(0);
  const [liveItems, setLiveItems] = useState(items);
  const [liveMeta, setLiveMeta] = useState(meta);
  const [liveOk, setLiveOk] = useState(listOk);
  const [liveMessage, setLiveMessage] = useState(listMessage);
  const [liveQ, setLiveQ] = useState(q);
  const [livePlatform, setLivePlatform] = useState(platform);
  const [liveRegion, setLiveRegion] = useState(region);
  const [liveSort, setLiveSort] = useState(sort);
  const [livePage, setLivePage] = useState(page);
  const resultsRef = useRef<HTMLDivElement>(null);
  const listPlatform = resolveGameKeyBrowsePlatform(category, livePlatform);
  const shown = extra.length > 0 ? [...liveItems, ...extra] : liveItems;
  const loadedPages =
    livePage + (extra.length > 0 ? Math.ceil(extra.length / Math.max(1, liveMeta.perPage || BROWSE_PER_PAGE)) : 0);
  const canLoadMore = liveOk && loadedPages < liveMeta.totalPages;
  const resetHref = catalogBrowseHref({ category });
  const busy = pending || filtering;

  const applyBrowse = useCallback(
    async (href: string) => {
      const url = new URL(href, window.location.origin);
      const nextCategory = categoryFromBrowsePath(url.pathname) || category;
      const parsed = parseBrowseQuery({
        category: nextCategory,
        q: url.searchParams.get('q') ?? '',
        platform: url.searchParams.get('platform') ?? '',
        region: url.searchParams.get('region') ?? '',
        page: url.searchParams.get('page') ?? '1',
        sort: url.searchParams.get('sort') ?? '',
      });
      const nextPlatform = resolveGameKeyBrowsePlatform(nextCategory, parsed.platform);
      const result = await listShopBrowseProducts({
        category: nextCategory,
        q: parsed.q,
        page: parsed.page,
        perPage: BROWSE_PER_PAGE,
        platform: nextPlatform || undefined,
        region: parsed.region || undefined,
        sort: parsed.sort,
      });
      setLiveQ(parsed.q);
      setLivePlatform(parsed.platform);
      setLiveRegion(parsed.region);
      setLiveSort(parsed.sort);
      setLivePage(parsed.page);
      setExtra([]);
      if (result.ok) {
        setLiveOk(true);
        setLiveMessage(undefined);
        setLiveItems(result.data.items);
        setLiveMeta(result.data.meta);
        return;
      }
      setLiveOk(false);
      setLiveMessage(result.message);
      setLiveItems([]);
    },
    [category],
  );

  const navigate = useCallback(
    (href: string, options: { history: 'push' | 'replace'; scroll: boolean }) => {
      if (isSameBrowseHref(href)) return;
      setFiltering(true);
      const url = new URL(href, window.location.origin);
      const samePath = url.pathname === window.location.pathname;
      if (samePath && options.history === 'replace') {
        window.history.replaceState(window.history.state, '', href);
      } else {
        startTransition(() => {
          if (options.history === 'push') {
            router.push(href, { scroll: options.scroll });
          } else {
            router.replace(href, { scroll: options.scroll });
          }
        });
      }
      void applyBrowse(href).finally(() => setFiltering(false));
    },
    [applyBrowse, router],
  );

  useEffect(() => {
    if (typeof window !== 'undefined') {
      const locationCategory = categoryFromBrowsePath(window.location.pathname);
      const parsed = browseFromLocation(locationCategory || category);
      if (locationCategory !== category) {
        return;
      }
      if (
        !browseQueryEquals(parsed, {
          q,
          platform,
          region,
          sort,
          page,
        })
      ) {
        return;
      }
    }
    setLiveItems(items);
    setLiveMeta(meta);
    setLiveOk(listOk);
    setLiveMessage(listMessage);
    setLiveQ(q);
    setLivePlatform(platform);
    setLiveRegion(region);
    setLiveSort(sort);
    setLivePage(page);
    setExtra([]);
  }, [category, q, platform, region, sort, page, items, meta, listOk, listMessage]);

  useEffect(() => {
    const parsed = browseFromLocation(categoryFromBrowsePath(window.location.pathname) || category);
    if (isDefaultBrowse(parsed)) {
      return;
    }
    setFiltering(true);
    void applyBrowse(`${window.location.pathname}${window.location.search}`).finally(() => setFiltering(false));
  }, [applyBrowse, category]);

  useEffect(() => {
    const onPopState = () => {
      setFiltering(true);
      void applyBrowse(`${window.location.pathname}${window.location.search}`).finally(() => setFiltering(false));
    };
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, [applyBrowse]);

  useLayoutEffect(() => {
    if (busy) return;
    const height = resultsRef.current?.offsetHeight ?? 0;
    if (height > 0) setHoldHeight(height);
  }, [busy, shown.length, liveOk, liveMessage]);

  useEffect(() => {
    if (!liveOk || !liveRegion) return;
    const available = liveMeta.regions ?? [];
    if (available.length === 0) return;
    if (available.some((item) => item.toLowerCase() === liveRegion.toLowerCase())) return;
    navigate(catalogBrowseHref({ category, q: liveQ, platform: livePlatform, sort: liveSort }), {
      history: 'replace',
      scroll: false,
    });
  }, [liveOk, liveRegion, liveMeta.regions, category, liveQ, livePlatform, liveSort, navigate]);

  async function loadMore() {
    if (loadingMore || !canLoadMore) return;
    setLoadingMore(true);
    const result = await listShopBrowseProducts({
      category,
      q: liveQ,
      platform: listPlatform || undefined,
      region: liveRegion || undefined,
      sort: liveSort,
      page: loadedPages + 1,
      perPage: BROWSE_PER_PAGE,
    });
    setLoadingMore(false);
    if (result.ok) {
      setExtra((current) => [...current, ...result.data.items]);
    }
  }

  return (
    <>
      <CatalogBrowseToolbar
        categories={categories}
        category={category}
        q={liveQ}
        platform={livePlatform}
        region={liveRegion}
        sort={liveSort}
        platforms={liveMeta.platforms ?? []}
        regions={liveMeta.regions ?? []}
        pending={busy}
        navigate={navigate}
      />
      <div
        ref={resultsRef}
        className="relative"
        aria-busy={busy}
        aria-live="polite"
        style={busy && holdHeight > 0 ? { minHeight: holdHeight } : undefined}
      >
        {busy ? (
          <div className="flex flex-col gap-3">
            <p className="sr-only">در حال به‌روزرسانی فهرست</p>
            <CatalogBrowseSkeleton count={12} />
          </div>
        ) : !liveOk ? (
          <p className="rounded-2xl bg-card px-4 py-8 text-center text-sm text-text/60 ring-1 ring-border">
            {liveMessage}
          </p>
        ) : shown.length === 0 ? (
          <div className="flex flex-col items-center gap-3 rounded-2xl bg-card px-4 py-10 text-center ring-1 ring-border">
            <p className="text-sm text-text/70">
              {liveQ
                ? `برای «${liveQ}» محصولی پیدا نشد.`
                : listPlatform
                  ? `در ${catalogSubcategoryLabel(category)} «${gameKeyPlatformLabel(listPlatform)}» محصولی نیست.`
                  : liveRegion
                    ? `در ریجن ${catalogRegionLabel(liveRegion)} محصولی پیدا نشد.`
                    : 'محصولی پیدا نشد.'}
            </p>
            <Button
              size="M"
              variant="outline"
              leftIcon={false}
              rightIcon={false}
              onClick={() => navigate(resetHref, { history: 'replace', scroll: false })}
            >
              پاک کردن فیلترها
            </Button>
          </div>
        ) : (
          <div id="catalog-browse-grid" className={`scroll-mt-[calc(var(--shop-header-pad,0px)+3.75rem)] lg:scroll-mt-6 ${catalogBrowseGridClass()}`}>
            {shown.map((product, index) => (
              <CatalogProductCard key={product.id} product={product} priority={index < 2} />
            ))}
          </div>
        )}
      </div>
      {!busy && liveOk && shown.length > 0 ? (
        <>
          {canLoadMore ? (
            <div className="flex justify-center lg:hidden">
              <Button
                size="L"
                variant="outline"
                leftIcon={false}
                rightIcon={false}
                loading={loadingMore}
                onClick={() => void loadMore()}
              >
                نمایش بیشتر
              </Button>
            </div>
          ) : null}
          <CatalogPagination
            page={liveMeta.page}
            totalPages={liveMeta.totalPages}
            hrefForPage={(nextPage) =>
              catalogBrowseHref({
                category,
                q: liveQ,
                platform: livePlatform,
                region: liveRegion,
                sort: liveSort,
                page: nextPage,
              })
            }
            onPage={(href) => navigate(href, { history: 'push', scroll: true })}
          />
        </>
      ) : null}
    </>
  );
}

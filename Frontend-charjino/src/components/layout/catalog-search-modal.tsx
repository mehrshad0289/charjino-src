'use client';

import { useCallback, useEffect, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { searchCatalogProducts } from '@/actions/catalog.actions';
import { hotjarSuppress } from '@/lib/hotjar';
import { CatalogCover } from '@/components/catalog/catalog-cover';
import { Chip, Modal } from '@/components/ui';
import {
  CloseIcon,
  ClockIcon,
  GameIcon,
  MagicpenIcon,
  SearchNormalIcon,
} from '@/components/ui/icon';
import { productImageAlt } from '@/lib/image-alt';
import { ROUTES, catalogBrowseHref } from '@/lib/routes';
import { notify } from '@/lib/toast';
import type { PublicProduct } from '@/types/catalog';
import {
  isSmartSearchProduct,
  isSuggestMode,
  type SmartSearchItem,
  type SmartSearchResponse,
} from '@/types/smart-search';

type CatalogSearchModalProps = {
  open: boolean;
  onClose: () => void;
};

const SHELL =
  'pointer-events-none fixed inset-0 z-[71] flex items-start justify-center px-3 pt-[4.75rem] pb-3 sm:px-4 sm:pt-20 sm:pb-6';

const PANEL =
  'pointer-events-auto flex h-[calc(100dvh-4.75rem-0.75rem)] w-full max-w-2xl max-h-none flex-col overflow-hidden rounded-xl border border-border bg-card shadow-modal transition-[opacity,transform] duration-300 ease-out motion-reduce:transition-none sm:h-[calc(100dvh-5rem-1.5rem)]';

const HISTORY_KEY = 'charjino_smart_search_history';
const HISTORY_MAX = 8;
const MIN_CHARS = 2;
const DEBOUNCE_MS = 300;
const LISTBOX_ID = 'catalog-search-listbox';

type HistoryEntry = {
  title: string;
  href: string;
};

function readHistory(): HistoryEntry[] {
  if (typeof window === 'undefined') return [];
  try {
    const raw = window.localStorage.getItem(HISTORY_KEY);
    const parsed = raw ? (JSON.parse(raw) as unknown) : [];
    if (!Array.isArray(parsed)) return [];
    return parsed
      .filter((row): row is HistoryEntry => {
        return (
          Boolean(row) &&
          typeof row === 'object' &&
          typeof (row as HistoryEntry).title === 'string' &&
          typeof (row as HistoryEntry).href === 'string'
        );
      })
      .slice(0, HISTORY_MAX);
  } catch {
    return [];
  }
}

function writeHistory(entry: HistoryEntry) {
  const next = [entry, ...readHistory().filter((row) => row.href !== entry.href)].slice(
    0,
    HISTORY_MAX,
  );
  window.localStorage.setItem(HISTORY_KEY, JSON.stringify(next));
}

function removeHistory(href: string) {
  window.localStorage.setItem(
    HISTORY_KEY,
    JSON.stringify(readHistory().filter((row) => row.href !== href)),
  );
}

function entityKindLabel(type: SmartSearchItem['result_type']) {
  if (type === 'category' || type === 'group') return 'دسته';
  if (type === 'platform') return 'پلتفرم';
  if (type === 'brand') return 'برند';
  if (type === 'tag') return 'برچسب';
  return 'محصول';
}

function trustedHref(item: SmartSearchItem): string {
  if (isSmartSearchProduct(item) && item.handle) {
    return ROUTES.catalog(item.handle);
  }
  if ((item.result_type === 'group' || item.result_type === 'category') && item.slug) {
    return ROUTES.category(item.slug);
  }
  if (item.result_type === 'platform' && item.slug) {
    return catalogBrowseHref({ category: 'game-keys', platform: item.slug });
  }
  if (item.result_type === 'brand' && item.slug) {
    return catalogBrowseHref({ category: item.group || undefined, q: item.name });
  }
  const q = 'name' in item ? item.name : '';
  return catalogBrowseHref({ q });
}

function optionId(index: number) {
  return `catalog-search-option-${index}`;
}

export function CatalogSearchModal({ open, onClose }: CatalogSearchModalProps) {
  const router = useRouter();
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLDivElement>(null);
  const abortRef = useRef<AbortController | null>(null);
  const seqRef = useRef(0);
  const [query, setQuery] = useState('');
  const [popular, setPopular] = useState<PublicProduct[]>([]);
  const [items, setItems] = useState<SmartSearchItem[]>([]);
  const [mode, setMode] = useState<SmartSearchResponse['mode']>('empty');
  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [activeIndex, setActiveIndex] = useState(-1);

  useEffect(() => {
    if (!open) {
      setQuery('');
      setItems([]);
      setMessage(null);
      setMode('empty');
      setPage(1);
      setHasMore(false);
      setActiveIndex(-1);
      abortRef.current?.abort();
      return;
    }
    setHistory(readHistory());
    const focus = window.setTimeout(() => inputRef.current?.focus(), 40);
    return () => window.clearTimeout(focus);
  }, [open]);

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    void searchCatalogProducts('').then((result) => {
      if (cancelled || !result.ok) return;
      setPopular(result.items);
    });
    return () => {
      cancelled = true;
    };
  }, [open]);

  const fetchPage = useCallback(async (q: string, nextPage: number, seq: number) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    const search = new URLSearchParams({
      q,
      page: String(nextPage),
      per_page: '10',
      mode: 'auto',
    });
    const response = await fetch(`/api/search?${search}`, {
      cache: 'no-store',
      signal: controller.signal,
    });
    const payload = (await response.json()) as {
      success?: boolean;
      message?: string;
      data?: SmartSearchResponse;
    };
    if (seq !== seqRef.current) {
      return null;
    }
    if (response.status === 429) {
      throw new Error(payload.message || 'لطفاً کمی صبر کنید و دوباره جستجو کنید.');
    }
    if (!response.ok || payload.success === false || !payload.data) {
      throw new Error(payload.message || 'جستجو انجام نشد');
    }
    return payload.data;
  }, []);

  useEffect(() => {
    if (!open) return;
    const q = query.trim();
    if (q.length < MIN_CHARS) {
      setItems([]);
      setMode('empty');
      setMessage(null);
      setLoading(false);
      setHasMore(false);
      setPage(1);
      setActiveIndex(-1);
      return;
    }

    const handle = window.setTimeout(() => {
      const seq = ++seqRef.current;
      setLoading(true);
      void fetchPage(q, 1, seq)
        .then((data) => {
          if (!data || seq !== seqRef.current) return;
          setLoading(false);
          setMessage(null);
          setMode(data.mode);
          setItems(data.items);
          setPage(data.pagination.current_page);
          setHasMore(Boolean(data.pagination.has_more_pages) && data.mode === 'products');
          setActiveIndex(data.items.length > 0 ? 0 : -1);
        })
        .catch((error: unknown) => {
          if (seq !== seqRef.current) return;
          if (error instanceof DOMException && error.name === 'AbortError') return;
          setLoading(false);
          setItems([]);
          const text = error instanceof Error ? error.message : 'جستجو انجام نشد';
          setMessage(text);
          notify.error(text);
        });
    }, DEBOUNCE_MS);

    return () => {
      window.clearTimeout(handle);
    };
  }, [open, query, fetchPage]);

  useEffect(() => {
    if (!open || !hasMore || loading || loadingMore || mode !== 'products') return;
    const root = listRef.current;
    const sentinel = root?.querySelector('[data-search-sentinel]');
    if (!root || !sentinel) return;
    const observer = new IntersectionObserver(
      (entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        const seq = seqRef.current;
        setLoadingMore(true);
        void fetchPage(query.trim(), page + 1, seq)
          .then((data) => {
            if (!data || seq !== seqRef.current) return;
            setLoadingMore(false);
            setItems((current) => {
              const seen = new Set(
                current.filter(isSmartSearchProduct).map((item) => item.handle),
              );
              const extra = data.items.filter((item) => {
                if (!isSmartSearchProduct(item)) return true;
                if (seen.has(item.handle)) return false;
                seen.add(item.handle);
                return true;
              });
              return [...current, ...extra];
            });
            setPage(data.pagination.current_page);
            setHasMore(Boolean(data.pagination.has_more_pages));
          })
          .catch(() => {
            setLoadingMore(false);
          });
      },
      { root, rootMargin: '80px' },
    );
    observer.observe(sentinel);
    return () => observer.disconnect();
  }, [open, hasMore, loading, loadingMore, mode, page, query, fetchPage]);

  function rememberAndClose(entry: HistoryEntry) {
    writeHistory(entry);
    setHistory(readHistory());
    onClose();
  }

  function goToSearchPage() {
    const q = query.trim();
    if (q.length < MIN_CHARS) return;
    rememberAndClose({ title: q, href: `${ROUTES.search}?q=${encodeURIComponent(q)}` });
    router.push(`${ROUTES.search}?q=${encodeURIComponent(q)}`);
  }

  function activateItem(item: SmartSearchItem) {
    const href = trustedHref(item);
    const title = isSmartSearchProduct(item) ? item.title : item.name;
    rememberAndClose({ title, href });
    router.push(href);
  }

  function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'Escape') {
      event.preventDefault();
      onClose();
      return;
    }
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      if (items.length === 0) return;
      setActiveIndex((current) => (current + 1) % items.length);
      return;
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault();
      if (items.length === 0) return;
      setActiveIndex((current) => (current <= 0 ? items.length - 1 : current - 1));
      return;
    }
    if (event.key === 'Home') {
      event.preventDefault();
      if (items.length > 0) setActiveIndex(0);
      return;
    }
    if (event.key === 'End') {
      event.preventDefault();
      if (items.length > 0) setActiveIndex(items.length - 1);
      return;
    }
    if (event.key === 'Enter') {
      event.preventDefault();
      const active = activeIndex >= 0 ? items[activeIndex] : undefined;
      if (active) {
        activateItem(active);
        return;
      }
      goToSearchPage();
    }
  }

  const trimmed = query.trim();
  const showSuggestions = trimmed.length >= MIN_CHARS;
  const expanded = open && showSuggestions && (loading || items.length > 0 || Boolean(message));

  const searchField = (
    <form
      className="flex shrink-0 items-center border-b border-border/50"
      onSubmit={(event) => {
        event.preventDefault();
        const active = activeIndex >= 0 ? items[activeIndex] : undefined;
        if (active) {
          activateItem(active);
          return;
        }
        goToSearchPage();
      }}
    >
      <div className="ps-4 pe-2 text-text/70">
        <SearchNormalIcon size={20} />
      </div>
      <input
        {...hotjarSuppress}
        ref={inputRef}
        type="text"
        role="combobox"
        aria-expanded={expanded}
        aria-controls={LISTBOX_ID}
        aria-activedescendant={activeIndex >= 0 ? optionId(activeIndex) : undefined}
        aria-autocomplete="list"
        enterKeyHint="search"
        placeholder="جستجو در کل فروشگاه..."
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        onKeyDown={onKeyDown}
        className="h-12 min-w-0 flex-1 bg-transparent py-2 text-base text-text placeholder:text-text/55 focus:outline-none sm:text-sm"
        aria-label="جستجو"
        autoComplete="off"
      />
      {query ? (
        <button
          type="button"
          aria-label="پاک کردن جستجو"
          onClick={() => {
            setQuery('');
            inputRef.current?.focus();
          }}
          className="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-text/55 hover:bg-text/5 hover:text-text"
        >
          <CloseIcon size={16} />
        </button>
      ) : null}
      <button
        type="button"
        aria-label="بستن"
        onClick={onClose}
        className="me-2 inline-flex size-8 shrink-0 items-center justify-center rounded-md text-text/70 hover:bg-text/5 hover:text-text"
      >
        <CloseIcon size={18} />
      </button>
    </form>
  );

  let body: ReactNode;
  if (loading) {
    body = (
      <div className="flex flex-1 flex-col gap-2 px-3 py-4" aria-busy="true">
        {Array.from({ length: 4 }, (_, index) => (
          <div key={index} className="flex items-center gap-3 rounded-xl px-3 py-2">
            <div className="size-10 shrink-0 animate-pulse rounded-xl bg-card-muted" />
            <div className="h-3 w-2/3 animate-pulse rounded bg-card-muted" />
          </div>
        ))}
      </div>
    );
  } else if (message) {
    body = (
      <div className="flex flex-1 flex-col items-center justify-center gap-3 px-6 py-14 text-center text-sm text-text/70">
        <p>{message}</p>
        <button
          type="button"
          className="rounded-lg bg-primary-600 px-3 py-1.5 text-xs text-white"
          onClick={() => {
            seqRef.current += 1;
            setQuery((current) => current.trim());
          }}
        >
          تلاش دوباره
        </button>
      </div>
    );
  } else if (showSuggestions && items.length === 0) {
    body = (
      <div className="flex flex-1 items-center justify-center px-6 py-14 text-center text-sm text-text/70">
        چیزی با این عبارت پیدا نشد. عبارت دیگری امتحان کنید.
      </div>
    );
  } else if (showSuggestions) {
    const references = items.filter((item) => !isSmartSearchProduct(item));
    const products = items.filter(isSmartSearchProduct);
    const ordered = isSuggestMode(mode) ? [...references, ...products] : items;
    body = (
      <>
        <h2 className="px-3 pt-3 text-xs font-semibold text-text/70">
          {isSuggestMode(mode) ? 'پیشنهاد' : 'نتیجه جستجو'}
        </h2>
        <ul id={LISTBOX_ID} role="listbox" className="pt-2">
          {ordered.map((item, index) => {
            const href = trustedHref(item);
            const active = index === activeIndex;
            const itemClass = `flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors ${
              active ? 'bg-text/10' : 'hover:bg-text/5'
            }`;
            if (isSmartSearchProduct(item)) {
              return (
                <li key={`product-${item.handle}`} id={optionId(index)} role="option" aria-selected={active}>
                  <Link
                    href={href}
                    className={itemClass}
                    onMouseEnter={() => setActiveIndex(index)}
                    onClick={() => rememberAndClose({ title: item.title, href })}
                  >
                    <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-card-muted">
                      <CatalogCover
                        src={item.thumbnail ?? ''}
                        alt={productImageAlt(item.title)}
                        height={40}
                        width={40}
                        fit="contain"
                        className="h-full w-full"
                      />
                    </div>
                    <div className="min-w-0">
                      <div className="truncate">{item.title}</div>
                      <div className="mt-1 truncate text-xs text-text/55">
                        {[item.group || item.categoryName, item.platform].filter(Boolean).join(' · ')}
                      </div>
                      {item.matched_package_label ? (
                        <Chip
                          appearance="filter"
                          label={item.matched_package_label}
                          decorative
                          className="mt-1 h-6 min-h-6 px-2 text-[10px]"
                        />
                      ) : null}
                    </div>
                  </Link>
                </li>
              );
            }
            return (
              <li key={`${item.result_type}-${item.slug}-${href}`} id={optionId(index)} role="option" aria-selected={active}>
                <Link
                  href={href}
                  className={itemClass}
                  onMouseEnter={() => setActiveIndex(index)}
                  onClick={() => rememberAndClose({ title: item.name, href })}
                >
                  <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-card-muted text-text/70">
                    {item.result_type === 'tag' ? <MagicpenIcon size={18} /> : <GameIcon size={18} />}
                  </div>
                  <div className="min-w-0">
                    <div className="truncate">{item.name}</div>
                    <div className="mt-1 text-xs text-text/55">{entityKindLabel(item.result_type)}</div>
                  </div>
                </Link>
              </li>
            );
          })}
        </ul>
        {hasMore ? <div data-search-sentinel className="h-8" /> : null}
        {loadingMore ? <div className="px-3 py-3 text-center text-xs text-text/55">موارد بعدی...</div> : null}
      </>
    );
  } else {
    body = (
      <>
        {history.length > 0 ? (
          <>
            <h2 className="px-3 pt-3 text-xs font-semibold text-text/70">جستجوهای اخیر</h2>
            <ul className="pt-2">
              {history.map((entry) => (
                <li key={entry.href} className="flex items-center">
                  <Link
                    href={entry.href}
                    className="flex min-w-0 flex-1 items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors hover:bg-text/5"
                    onClick={onClose}
                  >
                    <span className="text-text/55">
                      <ClockIcon size={16} />
                    </span>
                    <span className="min-w-0 truncate">{entry.title}</span>
                  </Link>
                  <button
                    type="button"
                    aria-label="حذف از تاریخچه"
                    className="me-2 inline-flex size-8 items-center justify-center rounded-md text-text/45 hover:bg-text/5 hover:text-text"
                    onClick={() => {
                      removeHistory(entry.href);
                      setHistory(readHistory());
                    }}
                  >
                    <CloseIcon size={14} />
                  </button>
                </li>
              ))}
            </ul>
          </>
        ) : null}
        {trimmed.length === 1 ? (
          <p className="px-3 pt-3 text-xs text-text/55">برای پیشنهاد زنده حداقل دو حرف بنویسید.</p>
        ) : null}
        <h2 className="px-3 pt-3 text-xs font-semibold text-text/70">محبوب</h2>
        {popular.length === 0 ? (
          <div className="px-6 py-10 text-center text-sm text-text/70">هنوز محصول محبوبی نیست.</div>
        ) : (
          <ul className="pt-2">
            {popular.map((product) => (
              <li key={product.id}>
                <Link
                  href={ROUTES.catalog(product.handle)}
                  className="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors hover:bg-text/5"
                  onClick={() =>
                    rememberAndClose({ title: product.title, href: ROUTES.catalog(product.handle) })
                  }
                >
                  <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-card-muted">
                    <CatalogCover
                      src={product.thumbnail}
                      alt={productImageAlt(product.title)}
                      height={40}
                      width={40}
                      fit="contain"
                      className="h-full w-full"
                    />
                  </div>
                  <div className="min-w-0">
                    <div className="truncate">{product.title}</div>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </>
    );
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="جستجو"
      close={<span className="sr-only">بستن</span>}
      headerClassName="sr-only"
      titleClassName="sr-only"
      shellClassName={SHELL}
      panelClassName={PANEL}
      toolbar={searchField}
      bodyClassName="flex min-h-0 flex-1 flex-col overflow-hidden p-2"
    >
      <div ref={listRef} className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
        {body}
      </div>
    </Modal>
  );
}

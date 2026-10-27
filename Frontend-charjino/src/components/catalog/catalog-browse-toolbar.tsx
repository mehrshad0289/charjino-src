'use client';

import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { Chip } from '@/components/ui';
import { hotjarSuppress } from '@/lib/hotjar';
import { SelectField } from '@/components/ui/field';
import { lockPageScroll } from '@/components/ui/drawer/lock-page-scroll';
import {
  CloseIcon,
  GameIcon,
  LocationIcon,
  MonitorIcon,
  SearchNormalIcon,
  Setting4Icon,
  SortIcon,
} from '@/components/ui/icon';
import { BROWSE_SORT_OPTIONS, type CatalogBrowseSort } from '@/lib/catalog-browse';
import { PlatformIcon } from './platform-icon';
import {
  GAME_KEY_PLATFORM_ALL,
  GAME_KEYS_SLUG,
  GIFT_CARD_SLUG,
  catalogSubcategoryLabel,
  gameKeyPlatformLabel,
} from '@/lib/catalog-family';
import { catalogRegionLabel } from '@/lib/catalog-region';
import { toFaDigits } from '@/lib/fa';
import { catalogBrowseHref } from '@/lib/routes';
import type { PublicCategory } from '@/types/catalog';

export type BrowseNavigate = (
  href: string,
  options: { history: 'push' | 'replace'; scroll: boolean },
) => void;

type CatalogBrowseToolbarProps = {
  categories: PublicCategory[];
  category?: string;
  q?: string;
  platform?: string;
  region?: string;
  sort?: CatalogBrowseSort;
  platforms?: string[];
  regions?: string[];
  pending?: boolean;
  navigate: BrowseNavigate;
};

const fieldClass =
  'h-11 w-full rounded-sm border border-border bg-card px-3 text-sm text-text shadow-card outline-none placeholder:text-text/45 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20';

const SEARCH_DEBOUNCE_MS = 450;

function FiltersSheet({
  open,
  title,
  onClose,
  children,
  footer,
}: {
  open: boolean;
  title: string;
  onClose: () => void;
  children: ReactNode;
  footer?: ReactNode;
}) {
  const titleId = useId();
  const [ready, setReady] = useState(false);
  const [rendered, setRendered] = useState(false);
  const [entered, setEntered] = useState(false);

  useEffect(() => {
    setReady(true);
  }, []);

  useEffect(() => {
    if (open) {
      setRendered(true);
      const frame = window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => setEntered(true));
      });
      return () => window.cancelAnimationFrame(frame);
    }
    setEntered(false);
    const timer = window.setTimeout(() => setRendered(false), 280);
    return () => window.clearTimeout(timer);
  }, [open]);

  const shouldLock = open || rendered;

  useLayoutEffect(() => {
    if (!shouldLock) return;
    return lockPageScroll();
  }, [shouldLock]);

  useEffect(() => {
    if (!shouldLock) return;
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') onClose();
    }
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [shouldLock, onClose]);

  if (!ready || !rendered) return null;

  return createPortal(
    <>
      <button
        type="button"
        aria-label="بستن فیلترها"
        className={`fixed inset-0 z-[100000] border-0 bg-black/50 p-0 transition-opacity duration-300 ${entered ? 'opacity-100' : 'opacity-0'}`}
        onClick={onClose}
      />
      <aside
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className={`fixed inset-x-0 bottom-0 z-[100001] flex max-h-[85svh] flex-col rounded-t-2xl border-t border-border bg-card shadow-modal transition-transform duration-300 ${entered ? 'translate-y-0' : 'translate-y-full'}`}
      >
        <header className="flex shrink-0 items-center justify-between border-b border-border px-4 py-3">
          <h2 id={titleId} className="text-base font-bold text-text">
            {title}
          </h2>
          <button
            type="button"
            aria-label="بستن"
            className="inline-flex size-8 items-center justify-center rounded-md text-text hover:bg-text/8"
            onClick={onClose}
          >
            <CloseIcon size={20} />
          </button>
        </header>
        <div className="relative z-10 min-h-0 flex-1 overflow-visible p-4">{children}</div>
        {footer ? (
          <div className="shrink-0 border-t border-border p-4 pe-16 pb-[max(1rem,env(safe-area-inset-bottom))] ps-4">
            {footer}
          </div>
        ) : null}
      </aside>
    </>,
    document.body,
  );
}

function FilterFields({
  category,
  categoryOptions,
  showSubcategory,
  subcategoryLabel,
  subcategoryOptions,
  subcategoryValue,
  subcategoryIcons,
  showRegion,
  regionOptions,
  region,
  sort,
  onCategory,
  onPlatform,
  onRegion,
  onSort,
  className,
  stacked = false,
}: {
  category: string;
  categoryOptions: { value: string; label: string }[];
  showSubcategory: boolean;
  subcategoryLabel: string;
  subcategoryOptions: { value: string; label: string; icon?: ReactNode }[];
  subcategoryValue: string;
  subcategoryIcons?: boolean;
  showRegion: boolean;
  regionOptions: { value: string; label: string }[];
  region: string;
  sort: CatalogBrowseSort;
  onCategory: (next: string) => void;
  onPlatform: (next: string) => void;
  onRegion: (next: string) => void;
  onSort: (next: CatalogBrowseSort) => void;
  className: string;
  stacked?: boolean;
  showSort?: boolean;
}) {
  const cell = stacked ? 'w-full' : 'w-[13rem] shrink-0';
  const regionCell = stacked ? 'w-full' : 'w-[11rem] shrink-0';
  const sortCell = stacked ? 'w-full' : 'w-[10.5rem] shrink-0';
  return (
    <div className={className}>
      <div className={cell}>
        <SelectField
          variant="shop"
          placement={stacked ? 'local' : 'fixed'}
          compact
          searchable
          label="دسته"
          placeholder="همه"
          searchPlaceholder="جستجوی دسته"
          icon={<GameIcon size={18} />}
          options={categoryOptions}
          value={category}
          onChange={onCategory}
        />
      </div>
      {showSubcategory ? (
        <div className={cell}>
          <SelectField
            variant="shop"
            placement={stacked ? 'local' : 'fixed'}
            compact
            searchable
            label={subcategoryLabel}
            placeholder="همه"
            searchPlaceholder={`جستجوی ${subcategoryLabel}`}
            icon={subcategoryIcons ? undefined : <MonitorIcon size={18} />}
            showItemIcon={!subcategoryIcons}
            options={subcategoryOptions}
            value={subcategoryValue}
            onChange={onPlatform}
          />
        </div>
      ) : null}
      {showRegion ? (
        <div className={regionCell}>
          <SelectField
            variant="shop"
            placement={stacked ? 'local' : 'fixed'}
            compact
            searchable
            label="ریجن"
            placeholder="همه"
            searchPlaceholder="جستجوی ریجن"
            icon={<LocationIcon size={18} />}
            options={regionOptions}
            value={region}
            onChange={onRegion}
          />
        </div>
      ) : null}
      <div className={sortCell}>
        <SelectField
          variant="shop"
          placement={stacked ? 'local' : 'fixed'}
          compact
          label="مرتب‌سازی"
          icon={<SortIcon size={18} />}
          searchable={false}
          showItemIcon={false}
          options={BROWSE_SORT_OPTIONS.map((item) => ({ value: item.value, label: item.label }))}
          value={sort}
          onChange={(next) => onSort(next as CatalogBrowseSort)}
        />
      </div>
    </div>
  );
}

export function CatalogBrowseToolbar({
  categories,
  category = '',
  q = '',
  platform = '',
  region = '',
  sort = 'listed',
  platforms = [],
  regions = [],
  pending = false,
  navigate,
}: CatalogBrowseToolbarProps) {
  const [text, setText] = useState(q);
  const searchFocusedRef = useRef(false);
  const [scrollCompact, setScrollCompact] = useState(false);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [draft, setDraft] = useState({ category, platform, region, sort });
  const isGameKeys = draft.category === GAME_KEYS_SLUG;
  const isGiftCard = draft.category === GIFT_CARD_SLUG;
  const showSubcategory = isGameKeys || platforms.length > 1 || Boolean(draft.platform && draft.platform !== GAME_KEY_PLATFORM_ALL);
  const showRegion = isGameKeys || regions.length > 0 || Boolean(draft.region);
  const subcategoryLabel = catalogSubcategoryLabel(draft.category);
  const subcategoryValue =
    !draft.platform || draft.platform === GAME_KEY_PLATFORM_ALL
      ? GAME_KEY_PLATFORM_ALL
      : draft.platform;
  const filters = useRef({ category: draft.category, text, platform: draft.platform, region: draft.region, sort: draft.sort });
  filters.current = { category: draft.category, text, platform: draft.platform, region: draft.region, sort: draft.sort };

  const categoryOptions = useMemo(
    () => [
      { value: '', label: 'همه' },
      ...categories.map((item) => ({ value: item.slug, label: item.title })),
    ],
    [categories],
  );
  const subcategoryOptions = useMemo(() => {
    const extra =
      draft.platform && draft.platform !== GAME_KEY_PLATFORM_ALL && !platforms.includes(draft.platform)
        ? [{ value: draft.platform, label: gameKeyPlatformLabel(draft.platform) }]
        : [];
    const rows = [
      { value: GAME_KEY_PLATFORM_ALL, label: 'همه' },
      ...extra,
      ...platforms.map((item) => ({ value: item, label: gameKeyPlatformLabel(item) })),
    ];
    if (!isGameKeys) return rows;
    return rows.map((row) => ({
      ...row,
      icon: <PlatformIcon platform={row.value} size={22} />,
    }));
  }, [draft.platform, isGameKeys, platforms]);
  const regionOptions = useMemo(() => {
    const extra =
      draft.region && !regions.includes(draft.region) ? [{ value: draft.region, label: catalogRegionLabel(draft.region) }] : [];
    return [
      { value: '', label: 'همه' },
      ...extra,
      ...regions.map((item) => ({ value: item, label: catalogRegionLabel(item) })),
    ];
  }, [draft.region, regions]);
  const quickPlatforms = useMemo(() => {
    if (!isGiftCard && !isGameKeys) return [];
    const extra =
      draft.platform && draft.platform !== GAME_KEY_PLATFORM_ALL && !platforms.includes(draft.platform)
        ? [draft.platform]
        : [];
    const all = [...extra, ...platforms];
    if (all.length === 0) return [];
    if (all.length > 8) return [];
    return all;
  }, [isGiftCard, isGameKeys, draft.platform, platforms]);

  const activePlatform = Boolean(draft.platform && draft.platform !== GAME_KEY_PLATFORM_ALL);
  const filterCount = Number(activePlatform) + Number(Boolean(draft.region));

  useEffect(() => {
    function onScroll() {
      setScrollCompact(window.scrollY > 48);
    }
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    if (searchFocusedRef.current) return;
    setText(q);
  }, [q]);

  useEffect(() => {
    if (pending) return;
    setDraft({ category, platform, region, sort });
  }, [pending, category, platform, region, sort]);

  function go(next: {
    category?: string;
    q?: string;
    platform?: string;
    region?: string;
    sort?: CatalogBrowseSort;
  }) {
    const current = filters.current;
    const nextCategory = next.category ?? current.category;
    const categoryChanged = nextCategory !== current.category;
    let nextPlatform = '';
    let nextRegion = '';
    if (categoryChanged) {
      nextPlatform = '';
    } else {
      const rawPlatform = next.platform ?? current.platform;
      nextPlatform = rawPlatform === GAME_KEY_PLATFORM_ALL ? '' : rawPlatform;
      nextRegion = next.region ?? current.region;
    }
    const nextSort = next.sort ?? current.sort;
    setDraft({
      category: nextCategory,
      platform: nextPlatform,
      region: nextRegion,
      sort: nextSort,
    });
    navigate(
      catalogBrowseHref({
        category: nextCategory,
        q: next.q ?? current.text,
        platform: nextPlatform,
        region: nextRegion,
        sort: nextSort,
      }),
      { history: categoryChanged ? 'push' : 'replace', scroll: categoryChanged },
    );
  }

  function clearFilters() {
    setText('');
    go({
      q: '',
      platform: '',
      region: '',
      sort: 'listed',
    });
  }

  useEffect(() => {
    const next = text.trim();
    if (next === q.trim()) return;
    const handle = window.setTimeout(() => go({ q: next }), next ? SEARCH_DEBOUNCE_MS : 0);
    return () => window.clearTimeout(handle);
  }, [text, q]);

  const fieldProps = {
    category: draft.category,
    categoryOptions,
    showSubcategory,
    subcategoryLabel,
    subcategoryOptions,
    subcategoryValue,
    subcategoryIcons: isGameKeys,
    showRegion,
    regionOptions,
    region: draft.region,
    sort: draft.sort,
    onCategory: (next: string) => {
      setSheetOpen(false);
      go({ category: next });
    },
    onPlatform: (next: string) => go({ platform: next }),
    onRegion: (next: string) => go({ region: next }),
    onSort: (next: CatalogBrowseSort) => go({ sort: next }),
    showSort: text.trim().length < 2,
  };

  const showMobileExtras = !scrollCompact;

  return (
    <div
      className={`relative z-20 flex flex-col pb-1 max-lg:sticky max-lg:top-0 max-lg:z-30 max-lg:-mx-4 max-lg:border-b max-lg:border-border/80 max-lg:bg-card/5 max-lg:px-4 max-lg:pt-[var(--shop-header-pad,0px)] max-lg:shadow-soft max-lg:backdrop-blur ${showMobileExtras ? 'gap-3 max-lg:pb-3' : 'gap-0 max-lg:pb-2'}`}
    >
      {pending ? (
        <div className="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 overflow-hidden rounded-full bg-primary-600/15">
          <div className="h-full w-1/3 rounded-full bg-primary-600 motion-safe:animate-pulse" />
        </div>
      ) : null}

      <div className="flex items-end gap-2 lg:hidden">
        <label className="flex min-w-0 flex-1 flex-col gap-2">
          <span className={`relative flex ${fieldClass} ${text ? 'pe-10' : ''} items-center gap-2`}>
            <SearchNormalIcon size={18} />
            <input
              {...hotjarSuppress}
              value={text}
              placeholder="جستجو در کل فروشگاه..."
              aria-label="جستجو در کل فروشگاه"
              onFocus={() => {
                searchFocusedRef.current = true;
              }}
              onBlur={() => {
                searchFocusedRef.current = false;
              }}
              onChange={(event) => setText(event.target.value)}
              className="h-full min-w-0 flex-1 border-0 bg-transparent text-sm text-text outline-none placeholder:text-text/45"
            />
            {text ? (
              <button
                type="button"
                aria-label="پاک کردن جستجو"
                onClick={() => setText('')}
                className="absolute end-2 top-1/2 inline-flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-text/55 hover:bg-text/5 hover:text-text"
              >
                <CloseIcon size={16} />
              </button>
            ) : null}
          </span>
        </label>
        <button
          type="button"
          className="relative inline-flex h-11 shrink-0 items-center gap-2 rounded-sm border border-border bg-card px-3 text-sm text-text shadow-card"
          onClick={() => setSheetOpen(true)}
        >
          <Setting4Icon size={18} />
          {filterCount > 0 ? (
            <span className="inline-flex min-w-5 items-center justify-center rounded-full bg-primary-600 px-1.5 text-[10px] font-bold text-white">
              {toFaDigits(filterCount)}
            </span>
          ) : null}
        </button>
      </div>

      <div className="hidden w-full flex-wrap items-end gap-3 lg:flex">
        <FilterFields
          {...fieldProps}
          stacked={false}
          className="flex min-w-0 flex-wrap items-end gap-3"
        />
        <label className="flex min-w-[18rem] flex-1 flex-col gap-2">
          <span className="text-xs font-medium text-text/70">جستجو در کل فروشگاه</span>
          <span className={`relative flex ${fieldClass} ${text ? 'pe-10' : ''} items-center gap-2`}>
            <SearchNormalIcon size={18} />
            <input
              {...hotjarSuppress}
              value={text}
              placeholder="جستجو در کل فروشگاه..."
              aria-label="جستجو در کل فروشگاه"
              onFocus={() => {
                searchFocusedRef.current = true;
              }}
              onBlur={() => {
                searchFocusedRef.current = false;
              }}
              onChange={(event) => setText(event.target.value)}
              className="h-full min-w-0 flex-1 border-0 bg-transparent text-sm text-text outline-none placeholder:text-text/45"
            />
            {text ? (
              <button
                type="button"
                aria-label="پاک کردن جستجو"
                onClick={() => setText('')}
                className="absolute end-2 top-1/2 inline-flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-text/55 hover:bg-text/5 hover:text-text"
              >
                <CloseIcon size={16} />
              </button>
            ) : null}
          </span>
        </label>
      </div>

      {showMobileExtras && quickPlatforms.length > 0 ? (
        <div className="flex min-w-0 flex-wrap items-center gap-2 lg:hidden">
          {quickPlatforms.map((item) => (
            <Chip
              key={item}
              appearance="filter"
              label={gameKeyPlatformLabel(item)}
              selected={draft.platform === item}
              onClick={() => go({ platform: draft.platform === item ? '' : item })}
            />
          ))}
        </div>
      ) : null}

      {showMobileExtras && (q || activePlatform || draft.region) ? (
        <div className="flex min-w-0 flex-wrap items-center gap-2">
          {q ? (
            <Chip
              appearance="filter"
              selected
              label={`جستجو: ${q}`}
              endIcon={<CloseIcon size={14} />}
              onClick={() => {
                setText('');
                go({ q: '' });
              }}
            />
          ) : null}
          {activePlatform ? (
            <Chip
              appearance="filter"
              selected
              label={gameKeyPlatformLabel(draft.platform)}
              endIcon={<CloseIcon size={14} />}
              onClick={() => go({ platform: '' })}
            />
          ) : null}
          {draft.region ? (
            <Chip
              appearance="filter"
              selected
              label={catalogRegionLabel(draft.region)}
              endIcon={<CloseIcon size={14} />}
              onClick={() => go({ region: '' })}
            />
          ) : null}
          <button
            type="button"
            className="text-xs font-medium text-primary-600 hover:text-primary-600/80"
            onClick={clearFilters}
          >
            پاک کردن همه
          </button>
        </div>
      ) : null}

      <FiltersSheet
        open={sheetOpen}
        title="فیلترها"
        onClose={() => setSheetOpen(false)}
        footer={
          <div className="flex gap-2">
            <button
              type="button"
              className="inline-flex h-11 flex-1 items-center justify-center rounded-sm border border-border bg-card text-sm font-medium text-text"
              onClick={clearFilters}
            >
              پاک کردن
            </button>
            <button
              type="button"
              className="inline-flex h-11 flex-1 items-center justify-center rounded-sm bg-primary-600 text-sm font-medium text-white"
              onClick={() => setSheetOpen(false)}
            >
              بستن
            </button>
          </div>
        }
      >
        <FilterFields {...fieldProps} stacked className="flex flex-col gap-3" />
      </FiltersSheet>
    </div>
  );
}

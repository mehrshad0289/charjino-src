import { gameKeyCardPlatformLabel } from '@/lib/catalog-family';
import { catalogRegionLabel } from '@/lib/catalog-region';
import type { CatalogHome, CatalogProduct, FlashSaleItem, HomeBanner, HomeGroup, PublicProduct } from '@/types/catalog';

export type HomeCardBadge = {
  platform?: string;
  region?: string;
  publisher?: string;
  originalPrice?: number;
  variant?: string;
};

export type HomeCard = {
  id: number;
  slug: string;
  name: string;
  image: string;
  price: number;
  badge: HomeCardBadge;
};

export type HomeGroupCard = {
  id?: number | null;
  slug?: string | null;
  name: string;
};

export type HomeBannerCard = Pick<HomeBanner, 'id' | 'src' | 'href' | 'title'> & {
  mediaType?: HomeBanner['mediaType'];
};

export type HomeCatalogView = {
  banners: HomeBannerCard[];
  groups: HomeGroupCard[];
  popular: HomeCard[];
  flashSale: HomeCard[];
  flashSaleTitle?: string;
  flashSaleSubtitle?: string;
  flashSaleEndsAt?: string | null;
  popularTitle?: string;
  popularSubtitle?: string;
  introHtml?: string | null;
};

function compact(value?: string | null) {
  return (value ?? '').replace(/\s+/g, ' ').trim();
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function asString(value: unknown) {
  return typeof value === 'string' ? value : '';
}

function asNumber(value: unknown) {
  const n = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(n) ? n : 0;
}

function badgeOf(raw: Record<string, unknown>, fallback: HomeCardBadge = {}): HomeCardBadge {
  const badge = asRecord(raw.badge);
  const platform = compact(asString(badge.platform) || fallback.platform);
  const region = compact(asString(badge.region) || fallback.region);
  const publisher = compact(asString(badge.publisher) || fallback.publisher);
  const variant = compact(asString(badge.variant) || fallback.variant);
  const originalPrice = asNumber(badge.originalPrice ?? fallback.originalPrice);
  return {
    ...(platform ? { platform } : {}),
    ...(region ? { region } : {}),
    ...(publisher ? { publisher } : {}),
    ...(variant ? { variant } : {}),
    ...(originalPrice > 0 ? { originalPrice } : {}),
  };
}

export function selectHomeProduct(product: PublicProduct | CatalogProduct | HomeCard | Record<string, unknown>): HomeCard {
  const raw = asRecord(product);
  const slug = compact(asString(raw.slug) || asString(raw.handle));
  const name = compact(asString(raw.name) || asString(raw.title));
  const region = catalogRegionLabel(asString(raw.region) || asString(asRecord(raw.badge).region));
  const platform = gameKeyCardPlatformLabel(asString(raw.platform) || asString(asRecord(raw.badge).platform))
    || compact(asString(raw.platform) || asString(asRecord(raw.badge).platform));

  return {
    id: asNumber(raw.id),
    slug,
    name,
    image: asString(raw.image) || asString(raw.thumbnail),
    price: asNumber(raw.price ?? raw.minPrice),
    badge: badgeOf(raw, { platform, region, publisher: compact(asString(raw.publisher)) }),
  };
}

export function selectHomeFlashItem(item: FlashSaleItem | HomeCard | Record<string, unknown>): HomeCard {
  const raw = asRecord(item);
  if (asString(raw.slug) || asString(raw.name)) {
    return selectHomeProduct(raw);
  }
  return {
    id: asNumber(raw.id),
    slug: compact(asString(raw.productHandle)),
    name: compact(asString(raw.productName)),
    image: asString(raw.image),
    price: asNumber(raw.price),
    badge: badgeOf(raw, {
      originalPrice: asNumber(raw.originalPrice),
      variant: compact(asString(raw.productVariantName)),
    }),
  };
}

export function selectHomeGroup(group: HomeGroup | HomeGroupCard): HomeGroupCard {
  return {
    id: group.id ?? null,
    slug: group.slug ?? null,
    name: compact(group.name),
  };
}

export function selectHomeBanner(banner: HomeBanner): HomeBannerCard {
  return {
    id: banner.id,
    src: banner.src,
    href: banner.href,
    title: banner.title,
    mediaType: banner.mediaType,
  };
}

export function selectHomeCatalog(catalog: CatalogHome): HomeCatalogView {
  return {
    banners: (catalog.banners ?? []).map(selectHomeBanner),
    groups: (catalog.groups ?? []).map(selectHomeGroup),
    popular: (catalog.popular ?? []).map(selectHomeProduct),
    flashSale: (catalog.flashSale ?? []).map(selectHomeFlashItem),
    flashSaleTitle: catalog.flashSaleTitle,
    flashSaleSubtitle: catalog.flashSaleSubtitle,
    flashSaleEndsAt: catalog.flashSaleEndsAt,
    popularTitle: catalog.popularTitle,
    popularSubtitle: catalog.popularSubtitle,
    introHtml: compact((catalog as CatalogHome & { introHtml?: string | null }).introHtml ?? '') || null,
  };
}

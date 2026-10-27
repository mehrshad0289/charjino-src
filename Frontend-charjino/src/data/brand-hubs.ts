export type BrandHubDef = {
  slug: string;
  fa: string;
  en: string;
  /** Existing category template URL: /catalog/category/{slug} */
  kind: 'category' | 'product';
  /** Product handle when the keyword hub is a product URL. */
  handle?: string;
  articleSlugs: string[];
};

/**
 * One keyword → one URL. Products are selected by brand/handle rules on the API.
 * Layout stays the current category browse template (except product-URL hubs).
 */
export const BRAND_HUBS: BrandHubDef[] = [
  {
    slug: 'steam-wallet',
    fa: 'گیفت کارت استیم',
    en: 'Steam Wallet',
    kind: 'category',
    articleSlugs: ['steam-wallet-vs-gift', 'steam-gift-redeem', 'game-topup-iran'],
  },
  {
    slug: 'google-play',
    fa: 'گیفت کارت گوگل پلی',
    en: 'Google Play',
    kind: 'category',
    articleSlugs: ['game-topup-iran', 'steam-wallet-vs-gift', 'buy-telegram-premium'],
  },
  {
    slug: 'call-of-duty-mobile',
    fa: 'سی‌پی کالاف دیوتی موبایل',
    en: 'Call of Duty Mobile',
    kind: 'category',
    articleSlugs: ['buy-cod-cp', 'codm-region', 'game-topup-iran'],
  },
  {
    slug: 'free-fire',
    fa: 'فری فایر',
    en: 'Free Fire',
    kind: 'category',
    articleSlugs: ['buy-freefire-diamonds', 'game-topup-iran', 'buy-pubg-uc'],
  },
  {
    slug: 'apple-itunes',
    fa: 'اپل آیتونز',
    en: 'Apple iTunes',
    kind: 'category',
    articleSlugs: ['game-topup-iran', 'steam-wallet-vs-gift', 'buy-telegram-premium'],
  },
  {
    slug: 'playstation',
    fa: 'پلی استیشن',
    en: 'PlayStation',
    kind: 'category',
    articleSlugs: ['game-topup-iran', 'steam-wallet-vs-gift', 'buy-telegram-premium'],
  },
  {
    slug: 'xbox',
    fa: 'ایکس باکس',
    en: 'Xbox',
    kind: 'category',
    articleSlugs: ['game-topup-iran', 'steam-wallet-vs-gift', 'buy-telegram-premium'],
  },
  {
    slug: 'valorant',
    fa: 'ولورانت پوینت',
    en: 'Valorant',
    kind: 'category',
    articleSlugs: ['buy-valorant-points', 'game-topup-iran', 'steam-wallet-vs-gift'],
  },
  {
    slug: 'telegram',
    fa: 'پرمیوم و استارز تلگرام',
    en: 'Telegram',
    kind: 'category',
    articleSlugs: ['buy-telegram-premium', 'game-topup-iran', 'steam-wallet-vs-gift'],
  },
  {
    slug: 'pubg-mobile',
    fa: 'یوسی پابجی',
    en: 'PUBG Mobile',
    kind: 'product',
    handle: 'pubg-mobile',
    articleSlugs: ['buy-pubg-uc', 'game-topup-iran', 'buy-cod-cp'],
  },
];

export function brandHubBySlug(slug?: string | null): BrandHubDef | null {
  const id = slug?.trim();
  if (!id) return null;
  return BRAND_HUBS.find((item) => item.slug === id) ?? null;
}

export function brandHubByHandle(handle?: string | null): BrandHubDef | null {
  const id = handle?.trim();
  if (!id) return null;
  return BRAND_HUBS.find((item) => item.kind === 'product' && item.handle === id) ?? null;
}

export function brandHubPath(hub: BrandHubDef): string {
  if (hub.kind === 'product' && hub.handle) {
    return `/catalog/${hub.handle}`;
  }
  return `/catalog/category/${hub.slug}`;
}

import {
  gameKeyPlatformLabel,
  gameKeyStorefrontId,
  isGameKeyProduct,
} from '@/lib/catalog-family';
import { catalogOfferPricesToman, isOrderable } from '@/lib/catalog-orderable';
import { irrFromToman } from '@/lib/price';
import { ROUTES, catalogBrowseHref } from '@/lib/routes';
import { isAbsoluteHttpUrl, productDescription } from '@/lib/seo';
import { isSteamGiftProduct } from '@/lib/seo-rules';
import { matchSeoBrand } from '@/lib/seo-title';
import { BRAND_EN, BRAND_FA, SOCIAL_LINKS, absoluteUrl } from '@/lib/site';
import type { CatalogProduct, CatalogSku } from '@/types/catalog';

export type BreadcrumbItem = {
  name: string;
  path?: string | null;
};

function jsonLdImage(src?: string | null): string | undefined {
  const raw = src?.trim();
  if (!raw) {
    return undefined;
  }
  if (raw.startsWith('/')) {
    return absoluteUrl(raw);
  }
  return isAbsoluteHttpUrl(raw) ? raw : undefined;
}

export function organizationWebsiteJsonLd(): Record<string, unknown> {
  const url = absoluteUrl('/');
  return {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'Organization',
        name: BRAND_FA,
        alternateName: BRAND_EN,
        url,
        logo: absoluteUrl('/icon-512.png'),
        sameAs: [SOCIAL_LINKS.instagram, SOCIAL_LINKS.telegram],
      },
      {
        '@type': 'WebSite',
        name: BRAND_FA,
        url,
        inLanguage: 'fa-IR',
        potentialAction: {
          '@type': 'SearchAction',
          target: `${absoluteUrl('/catalog/category')}?q={search_term_string}`,
          'query-input': 'required name=search_term_string',
        },
      },
    ],
  };
}

export function breadcrumbListJsonLd(items: BreadcrumbItem[]): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => {
      const entry: Record<string, unknown> = {
        '@type': 'ListItem',
        position: index + 1,
        name: item.name,
      };
      const path = item.path?.trim();
      if (path) {
        entry.item = absoluteUrl(path);
      }
      return entry;
    }),
  };
}

const IN_STOCK = 'https://schema.org/InStock';
const OUT_OF_STOCK = 'https://schema.org/OutOfStock';
const GOOGLE_PRODUCT_TAXONOMY = 'https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt';
const GPC_VIDEO_GAME_SOFTWARE = '1279';
const GPC_VIRTUAL_CURRENCY = '5935';
const GPC_GIFT_CARDS = '53';

/** JSON-LD Brand.name: English publisher/platform, never Persian shop labels. */
const JSON_LD_BRAND_BY_SEO_ID: Record<string, string> = {
  steam: 'Steam',
  'free-fire': 'Garena Free Fire',
  telegram: 'Telegram',
  'pubg-mobile': 'PUBG Mobile',
  'cod-mobile': 'Call of Duty Mobile',
  'mobile-legends': 'Mobile Legends',
  'genshin-impact': 'Genshin Impact',
  valorant: 'Valorant',
  roblox: 'Roblox',
  fortnite: 'Fortnite',
  playstation: 'PlayStation',
  xbox: 'Xbox',
  nintendo: 'Nintendo',
  'google-play': 'Google Play',
  itunes: 'App Store',
  discord: 'Discord',
  netflix: 'Netflix',
  amazon: 'Amazon',
  'razer-gold': 'Razer Gold',
  'league-of-legends': 'League of Legends',
  'wild-rift': 'Wild Rift',
  'apex-legends': 'Apex Legends',
  pubg: 'PUBG',
  ea: 'EA',
  battlenet: 'Battle.net',
  epic: 'Epic Games',
  twitch: 'Twitch',
  tiktok: 'TikTok',
};

const JSON_LD_BRAND_BY_PLATFORM: Record<string, string> = {
  steam: 'Steam',
  playstation: 'PlayStation',
  xbox: 'Xbox',
  nintendo: 'Nintendo',
  telegram: 'Telegram',
  'pubg-mobile': 'PUBG Mobile',
  'free-fire': 'Garena Free Fire',
  'google-play': 'Google Play',
  itunes: 'App Store',
  epic: 'Epic Games',
  ea: 'EA',
  ubisoft: 'Ubisoft',
  rockstar: 'Rockstar',
  battlenet: 'Battle.net',
  discord: 'Discord',
  netflix: 'Netflix',
  amazon: 'Amazon',
  roblox: 'Roblox',
  fortnite: 'Fortnite',
  valorant: 'Valorant',
  'razer-gold': 'Razer Gold',
  'league-of-legends': 'League of Legends',
  'wild-rift': 'Wild Rift',
  'apex-legends': 'Apex Legends',
  pubg: 'PUBG',
  garena: 'Garena',
};

function fulfillmentFamily(product: CatalogProduct): string {
  const type = product.fulfillmentType?.trim() ?? '';
  if (type) {
    return type;
  }
  const slug = product.category?.slug?.trim() ?? '';
  if (slug === 'gift-card') return 'giftcard';
  if (slug === 'game-keys') return 'gamekey';
  if (slug === 'steam-wallet') return 'steam-topup';
  if (slug === 'steam-gifts') return 'steam-gift';
  if (slug === 'game-topup') return 'topup';
  if (slug === 'telegram') return 'telegram-premium';
  return '';
}

export function jsonLdBrandName(product: CatalogProduct): string {
  const family = fulfillmentFamily(product);
  if (family === 'steam-gift' || family === 'steam-topup' || isSteamGiftProduct(product)) {
    return 'Steam';
  }
  if (family.startsWith('telegram')) {
    return 'Telegram';
  }
  const seo = matchSeoBrand(product);
  if (seo) {
    return JSON_LD_BRAND_BY_SEO_ID[seo.id] ?? seo.en;
  }
  const publisher = product.publisher?.trim();
  if (publisher) {
    return publisher;
  }
  const platform = (product.platform ?? '').trim().toLowerCase();
  if (platform && JSON_LD_BRAND_BY_PLATFORM[platform]) {
    return JSON_LD_BRAND_BY_PLATFORM[platform];
  }
  const titleEn = product.titleEn?.trim();
  if (titleEn) {
    return titleEn;
  }
  return BRAND_EN;
}

function googleProductCategoryCode(codeValue: string): Record<string, unknown> {
  return {
    '@type': 'CategoryCode',
    inCodeSet: GOOGLE_PRODUCT_TAXONOMY,
    codeValue,
  };
}

export function jsonLdGoogleProductCategory(product: CatalogProduct): Record<string, unknown> {
  const family = fulfillmentFamily(product);
  if (family === 'giftcard') {
    return googleProductCategoryCode(GPC_GIFT_CARDS);
  }
  if (family === 'topup' || family === 'steam-topup' || family.startsWith('telegram')) {
    return googleProductCategoryCode(GPC_VIRTUAL_CURRENCY);
  }
  return googleProductCategoryCode(GPC_VIDEO_GAME_SOFTWARE);
}

function zeroDayQuantity(): Record<string, unknown> {
  return {
    '@type': 'QuantitativeValue',
    minValue: 0,
    maxValue: 0,
    unitCode: 'DAY',
  };
}

/** Digital delivery: no physical shipping, instant to Iran. */
export function digitalShippingDetailsJsonLd(): Record<string, unknown> {
  return {
    '@type': 'OfferShippingDetails',
    shippingRate: {
      '@type': 'MonetaryAmount',
      value: 0,
      currency: 'IRR',
    },
    deliveryTime: {
      '@type': 'ShippingDeliveryTime',
      handlingTime: zeroDayQuantity(),
      transitTime: zeroDayQuantity(),
    },
    shippingDestination: {
      '@type': 'DefinedRegion',
      addressCountry: 'IR',
    },
  };
}

/**
 * Site terms: digital goods only; wrong player ID is not refundable; no return of delivered charge.
 * Matches /terms → MerchantReturnNotPermitted for IR.
 */
export function merchantReturnPolicyJsonLd(): Record<string, unknown> {
  return {
    '@type': 'MerchantReturnPolicy',
    applicableCountry: 'IR',
    returnPolicyCategory: 'https://schema.org/MerchantReturnNotPermitted',
    url: absoluteUrl(ROUTES.terms),
    merchantReturnLink: absoluteUrl(ROUTES.terms),
  };
}

function withOfferMerchantFields(offer: Record<string, unknown>): Record<string, unknown> {
  return {
    ...offer,
    shippingDetails: digitalShippingDetailsJsonLd(),
    hasMerchantReturnPolicy: merchantReturnPolicyJsonLd(),
  };
}

function productOffersJsonLd(
  product: CatalogProduct,
  skus: CatalogSku[],
): Record<string, unknown> | Record<string, unknown>[] | null {
  const prices = [...new Set(catalogOfferPricesToman(product, skus))].sort((left, right) => left - right);
  if (prices.length === 0) {
    return null;
  }
  const url = absoluteUrl(ROUTES.catalog(product.handle));
  const availability = isOrderable(product) ? IN_STOCK : OUT_OF_STOCK;
  const offers = prices.map((toman) =>
    withOfferMerchantFields({
      '@type': 'Offer',
      priceCurrency: 'IRR',
      price: irrFromToman(toman),
      availability,
      url,
      itemCondition: 'https://schema.org/NewCondition',
    }),
  );
  return offers.length === 1 ? offers[0] : offers;
}

export function productJsonLd(product: CatalogProduct, skus: CatalogSku[] = product.skus ?? []): Record<string, unknown> {
  const url = absoluteUrl(ROUTES.catalog(product.handle));
  const data: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.title,
    description: productDescription(product),
    url,
    inLanguage: 'fa-IR',
    brand: { '@type': 'Brand', name: jsonLdBrandName(product) },
    category: jsonLdGoogleProductCategory(product),
  };
  const image = jsonLdImage(product.thumbnail);
  if (image) {
    data.image = image;
  }
  const sku = product.code?.trim();
  if (sku) {
    data.sku = sku;
  }
  const keywords = (product.searchTags ?? []).map((tag) => tag.trim()).filter(Boolean);
  if (keywords.length > 0) {
    data.keywords = keywords.join(', ');
  }
  const offers = productOffersJsonLd(product, skus);
  if (offers) {
    data.offers = offers;
  }
  return data;
}

export function productBreadcrumbItems(product: {
  title: string;
  handle: string;
  platform?: string | null;
  fulfillmentType?: string | null;
  category?: { slug?: string | null; title?: string | null } | null;
  categoryName?: string | null;
}): BreadcrumbItem[] {
  const items: BreadcrumbItem[] = [
    { name: 'خانه', path: ROUTES.home },
    { name: 'فروشگاه', path: ROUTES.categories },
  ];
  const category = product.category;
  if (category?.slug && category.title) {
    items.push({ name: category.title, path: ROUTES.category(category.slug) });
    if (isGameKeyProduct(product) && product.platform) {
      const storefront = gameKeyStorefrontId(product.platform) || product.platform;
      items.push({
        name: gameKeyPlatformLabel(storefront) || storefront,
        path: catalogBrowseHref({ category: category.slug, platform: storefront }),
      });
    }
  } else if (product.categoryName?.trim()) {
    items.push({ name: product.categoryName.trim() });
  }
  items.push({ name: product.title, path: ROUTES.catalog(product.handle) });
  return items;
}

export function browseBreadcrumbItems(input: {
  categoryTitle?: string | null;
  categorySlug?: string | null;
  heading: string;
  path: string;
}): BreadcrumbItem[] {
  const items: BreadcrumbItem[] = [
    { name: 'خانه', path: ROUTES.home },
    { name: 'فروشگاه', path: ROUTES.categories },
  ];
  if (input.heading.startsWith('نتایج')) {
    items.push({ name: input.heading, path: input.path });
  } else if (input.categorySlug && input.categoryTitle) {
    items.push({
      name: input.categoryTitle,
      path: ROUTES.category(input.categorySlug),
    });
  } else if (input.heading && input.heading !== 'فروشگاه') {
    items.push({ name: input.heading, path: input.path });
  }
  return items;
}

export function howToJsonLd(input: {
  name: string;
  description?: string | null;
  steps: { text: string }[];
}): Record<string, unknown> | null {
  const steps = input.steps.map((step) => step.text.trim()).filter(Boolean);
  if (steps.length === 0) {
    return null;
  }
  return {
    '@context': 'https://schema.org',
    '@type': 'HowTo',
    name: input.name,
    description: input.description || undefined,
    inLanguage: 'fa-IR',
    step: steps.map((text, index) => ({
      '@type': 'HowToStep',
      position: index + 1,
      name: `مرحله ${index + 1}`,
      text,
    })),
  };
}

export function collectionPageJsonLd(input: {
  name: string;
  description?: string;
  path: string;
  items: Array<{ name: string; path: string }>;
}): Record<string, unknown>[] {
  const url = absoluteUrl(input.path);
  return [
    {
      '@context': 'https://schema.org',
      '@type': 'CollectionPage',
      name: input.name,
      description: input.description || undefined,
      url,
      inLanguage: 'fa-IR',
    },
    {
      '@context': 'https://schema.org',
      '@type': 'ItemList',
      itemListElement: input.items.map((item, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        url: absoluteUrl(item.path),
        name: item.name,
      })),
    },
  ];
}

export function faqPageJsonLd(items: { name: string; text: string }[]): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: items.map((item) => ({
      '@type': 'Question',
      name: item.name,
      acceptedAnswer: {
        '@type': 'Answer',
        text: item.text,
      },
    })),
  };
}

export function contactOrganizationJsonLd(telephone: string): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: BRAND_FA,
    url: absoluteUrl('/'),
    contactPoint: {
      '@type': 'ContactPoint',
      telephone,
      contactType: 'customer support',
      areaServed: 'IR',
      availableLanguage: 'fa',
    },
  };
}

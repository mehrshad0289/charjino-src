import type { Metadata } from 'next';
import { brandHubBySlug } from '@/data/brand-hubs';
import {
  buildCategorySeoDescription,
  buildCategorySeoHeading,
  buildCategorySeoTitle,
  buildProductSeoDescription,
  buildProductSeoHeading,
  buildProductDocumentTitle,
  fillMetaDescription,
  headingFromSeoTitle,
  type SeoTitleProduct,
} from '@/lib/seo-title';
import { BRAND_EN, BRAND_FA, SITE_URL } from '@/lib/site';

export const HOME_H1 = 'شارژینو؛ خرید شارژ بازی و گیفت کارت با تومان';
export const HOME_TITLE = 'خرید شارژ بازی و گیفت کارت ریالی | شارژینو';
export const SHOP_LISTING_TITLE = 'خرید شارژ بازی و گیفت کارت ارزان | تحویل فوری';
export const SHOP_LISTING_HEADING = 'خرید شارژ بازی و گیفت کارت';
export const OG_IMAGE = '/og-image.png';
export const OG_IMAGE_ALT = 'شارژینو؛ خرید شارژ بازی و گیفت کارت';
const TWITTER_IMAGE = '/twitter-image.png';

const SHARE_IMAGE = {
  width: 1200,
  height: 630,
  alt: OG_IMAGE_ALT,
} as const;

export type BuildMetadataInput = {
  title: string;
  description: string;
  path: string;
  image?: string | null;
  noindex?: boolean;
  follow?: boolean;
};

export function isAbsoluteHttpUrl(value?: string | null): value is string {
  if (!value) {
    return false;
  }
  try {
    const parsed = new URL(value);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch {
    return false;
  }
}

export function clipDescription(text: string): string {
  return fillMetaDescription(collapseRepeatedTitleVerb(text));
}

function collapseRepeatedTitleVerb(text: string) {
  return text.replace(/(^|[\s؛،.])(شارژ|خرید)\s+\2\s+/gu, '$1$2 ');
}

export type ProductSeoSource = SeoTitleProduct & {
  description?: string | null;
  categoryName?: string | null;
  seoTitle?: string | null;
  seoDescription?: string | null;
  minPrice?: number | null;
};

export function productDocumentTitle(product: ProductSeoSource): string {
  return buildProductDocumentTitle(product);
}

export function productHeading(product: ProductSeoSource): string {
  return buildProductSeoHeading(product);
}

export function productDescription(product: ProductSeoSource): string {
  return buildProductSeoDescription(product);
}

export const PAGE_DESCRIPTIONS = {
  home: 'خرید شارژ بازی و گیفت کارت ریالی با تومان از شارژینو. یوسی پابجی، سی‌پی کالاف و الماس فری فایر؛ تحویل فوری و بدون رمز اکانت.',
  shop: 'خرید شارژ بازی، گیفت کارت و کلید بازی با تومان و تحویل فوری از شارژینو. پشتیبانی پاسخگوست؛ پرداخت ریالی بدون رمز اکانت بازی.',
  about:
    'شارژینو فروشگاه شارژ بازی و گیفت کارت با تومان است؛ خرید با Player ID، تحویل فوری، بدون رمز اکانت و پشتیبانی پاسخگو برای گیمرهای ایرانی.',
  contact:
    'تماس با پشتیبانی شارژینو؛ تلفن ۰۹۳۶۱۲۴۰۲۸۹، اینستاگرام و تلگرام @charjino_24 برای پیگیری سفارش شارژ بازی و گیفت کارت با تومان.',
  terms:
    'قوانین خرید شارژ بازی و گیفت کارت در شارژینو؛ پرداخت تومانی، تحویل دیجیتال، بدون رمز اکانت و شرایط پیگیری سفارش از خود سایت.',
  privacy:
    'حریم خصوصی شارژینو: دادهٔ ورود پیامکی، سفارش و پرداخت زیبال؛ بدون رمز اکانت بازی. مشاهده، اصلاح یا حذف از پشتیبانی.',
  uncredited:
    'اگر خرید را تا درگاه جلو برده‌ای و پول کم شده ولی شارژ، گیفت یا کلید نرسیده، سفارش گم نشده؛ با شماره سفارش به پشتیبانی شارژینو پیام بده.',
  auth: 'ورود و عضویت در شارژینو با پیامک. رمز حساب بازی گرفته نمی‌شود و سفارش از خود سایت پیگیری می‌شود.',
  leaderboard:
    'جدول برترین‌های شارژینو به‌زودی می‌آید. این بخش هنوز به API وصل نیست و فهرست ساختگی نمایش داده نمی‌شود.',
  invoices: 'پیگیری سفارش شارژینو؛ شماره سفارش را ببین و اگر پرداخت شده ولی شارژ نرسیده به پشتیبانی پیام بده.',
  invoice: 'وضعیت سفارش در شارژینو؛ پیگیری پرداخت و تحویل با شماره سفارش از همین صفحه.',
  profile: 'حساب من در شارژینو؛ سفارش‌ها، تیکت پشتیبانی، علاقه‌مندی‌ها و نام نمایشی.',
  profileOrders: 'فهرست سفارش‌های حساب شارژینو؛ وضعیت، فاکتور و پرداخت.',
  profileOrder: 'جزئیات سفارش حساب شارژینو؛ محصول، Player ID، پرداخت و وضعیت تحویل.',
  profileTickets: 'تیکت پشتیبانی شارژینو؛ ثبت درخواست و پیگیری پاسخ.',
  profileFavorites: 'علاقه‌مندی‌های شارژینو؛ بازی‌ها و گیفت‌کارت‌های ذخیره‌شده.',
  profileAccount: 'اطلاعات حساب شارژینو؛ شماره موبایل و نام نمایشی.',
  profileWallet: 'کیف پول شارژینو فقط برای خرید در همین فروشگاه قابل استفاده است.',
  zibal: 'بازگشت از درگاه پرداخت شارژینو برای تأیید وضعیت سفارش. اگر پرداخت قطعی شده و کالا نرسیده، از پیگیری سفارش ادامه بده.',
  zarinpal: 'بازگشت از درگاه پرداخت شارژینو برای تأیید وضعیت سفارش. اگر پرداخت قطعی شده و کالا نرسیده، از پیگیری سفارش ادامه بده.',
  figma: 'کاتالوگ توکن‌های رنگ، دکمه و چیپ مطابق طرح تیره فروشگاه شارژینو است.',
  notFound: 'این مسیر در شارژینو پیدا نشد. از خانه، فروشگاه یا دسته‌ها شارژ بازی و گیفت کارت را ادامه بده.',
  blog: 'آموزش خرید شارژ بازی و گیفت کارت در وبلاگ شارژینو؛ یوسی، سی‌پی و گیفت کارت با تومان، تحویل فوری، بدون رمز اکانت و پشتیبانی پاسخگو.',
} as const;

export const BLOG_HUB_LEDE = 'آموزش و راهنمای خرید شارژ بازی و گیفت کارت در وبلاگ شارژینو؛ بدون رمز اکانت.';
export const AUTHOR_DOCUMENT_TITLE = 'شارژینو؛ نویسندهٔ وبلاگ شارژینو';

export const NOT_FOUND_TITLE = 'صفحه پیدا نشد';
export const PRODUCT_NOT_FOUND_TITLE = 'محصول پیدا نشد';
export const CATEGORY_NOT_FOUND_TITLE = 'دسته پیدا نشد';

export function notFoundMetadata(title = NOT_FOUND_TITLE): Metadata {
  const description = clipDescription(PAGE_DESCRIPTIONS.notFound);
  return {
    title,
    description,
    robots: { index: false, follow: true },
    openGraph: {
      type: 'website',
      title,
      description,
      siteName: `${BRAND_FA} | ${BRAND_EN}`,
      locale: 'fa_IR',
    },
    twitter: {
      card: 'summary',
      title,
      description,
    },
  };
}

export function categoryDocumentTitle(
  categoryTitle?: string | null,
  slug?: string | null,
  seoTitle?: string | null,
) {
  return buildCategorySeoTitle(categoryTitle, slug, seoTitle);
}

export function categoryHeading(
  categoryTitle?: string | null,
  slug?: string | null,
  seoTitle?: string | null,
) {
  if (brandHubBySlug(slug)) {
    return buildCategorySeoHeading(categoryTitle, slug);
  }
  const stored = (seoTitle ?? '').trim();
  if (stored) {
    return headingFromSeoTitle(stored) || buildCategorySeoHeading(categoryTitle, slug);
  }
  return buildCategorySeoHeading(categoryTitle, slug);
}

export function categoryDescription(
  categoryTitle: string,
  facetLabel?: string,
  slug?: string | null,
  seoDescription?: string | null,
): string {
  if (facetLabel) {
    return clipDescription(
      `خرید ${facetLabel} در دسته ${categoryTitle} از شارژینو با تحویل فوری؛ پشتیبانی پاسخگوست و پرداخت ریالی است.`,
    );
  }
  return clipDescription(
    buildCategorySeoDescription({ title: categoryTitle, slug, seoDescription }),
  );
}

export function searchDescription(query: string): string {
  return clipDescription(
    `جستجوی «${query}» در شارژینو؛ شارژ بازی، گیفت‌کارت و کالای دیجیتال مجاز.`,
  );
}

function canonicalForPath(path: string) {
  const normalized = path.startsWith('/') ? path : `/${path}`;
  if (normalized === '/') {
    return SITE_URL;
  }
  return normalized;
}

export function buildMetadata({
  title,
  description,
  path,
  image,
  noindex,
  follow,
}: BuildMetadataInput): Metadata {
  const canonical = canonicalForPath(path);
  const desc = clipDescription(description);
  const absoluteImage = isAbsoluteHttpUrl(image) ? image : null;
  const ogImage = absoluteImage ?? OG_IMAGE;
  const twitterImage = absoluteImage ?? TWITTER_IMAGE;
  const imageAlt = absoluteImage ? title : OG_IMAGE_ALT;

  const metadata: Metadata = {
    title,
    description: desc,
    alternates: { canonical },
    openGraph: {
      type: 'website',
      url: canonical,
      title,
      description: desc,
      siteName: `${BRAND_FA} | ${BRAND_EN}`,
      locale: 'fa_IR',
      images: [{ url: ogImage, ...SHARE_IMAGE, alt: imageAlt }],
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description: desc,
      images: [{ url: twitterImage, ...SHARE_IMAGE, alt: imageAlt }],
    },
  };

  if (noindex) {
    metadata.robots = { index: false, follow: follow ?? true };
  }

  return metadata;
}

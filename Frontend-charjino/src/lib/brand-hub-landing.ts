import { brandHubBySlug, brandHubPath, type BrandHubDef } from '@/data/brand-hubs';
import type { CategoryFaq, CategoryLanding } from '@/data/category-landing';
import { catalogRegionLabel } from '@/lib/catalog-region';
import { toFaDigits } from '@/lib/fa';
import { formatTomanNumber, tomanPrice } from '@/lib/price';
import { ROUTES } from '@/lib/routes';
import { fillMetaDescription } from '@/lib/seo-title';
import type { CatalogProduct } from '@/types/catalog';

const GOOGLE_PLAY_AMOUNTS = [5, 10, 20, 50, 100, 200] as const;

function compact(value: string) {
  return value.replace(/\s+/g, ' ').trim();
}

function regionsOf(products: CatalogProduct[]) {
  const labels: string[] = [];
  const seen = new Set<string>();
  for (const product of products) {
    const label = catalogRegionLabel(product.region);
    if (!label || seen.has(label)) continue;
    seen.add(label);
    labels.push(label);
  }
  return labels;
}

function priceRange(products: CatalogProduct[]) {
  const prices = products.map((item) => tomanPrice(item.minPrice)).filter((value) => value > 0);
  if (prices.length === 0) return '';
  const min = Math.min(...prices);
  const max = Math.max(...prices);
  if (min === max) return `از ${formatTomanNumber(min)} تومان`;
  return `از ${formatTomanNumber(min)} تا ${formatTomanNumber(max)} تومان`;
}

function deliveryLine(products: CatalogProduct[]) {
  const types = new Set(products.map((item) => item.fulfillmentType || ''));
  if ([...types].every((type) => type === 'giftcard')) {
    return 'تحویل به‌صورت کد دیجیتال است؛ بعد از پرداخت تومان، کد را از صفحهٔ سفارش برمی‌دارید و فقط در فروشگاه رسمی همان برند ردیم می‌کنید. رمز اکانت فروشگاه گرفته نمی‌شود.';
  }
  if ([...types].every((type) => type === 'topup' || type.startsWith('telegram'))) {
    return 'تحویل شارژ مستقیم است؛ موجودی روی شناسه یا یوزرنیمی که در فرم نوشته‌اید می‌نشیند. رمز عبور بازی یا پیام‌رسان گرفته نمی‌شود.';
  }
  return 'بسته به محصول، تحویل یا کد دیجیتال است یا شارژ روی شناسه. قبل از پرداخت صفحهٔ همان کالا را باز کنید و نوع تحویل را بخوانید. رمز اکانت گرفته نمی‌شود.';
}

function articleLinks(hub: BrandHubDef) {
  return hub.articleSlugs
    .slice(0, 3)
    .map((slug) => `[[${ROUTES.blogPost(slug)}|مقالهٔ راهنما]]`)
    .join('، ');
}

function wordCount(text: string) {
  return compact(text).split(' ').filter(Boolean).length;
}

function padWords(text: string, min: number, max: number) {
  let next = compact(text);
  const pads = [
    ' سفارش فقط از خود سایت شارژینو ثبت می‌شود و مبلغ نهایی پیش از درگاه به تومان مشخص است.',
    ' اگر پرداخت تأیید شد و موجودی یا کد نرسید، همان شماره سفارش را از پیگیری سفارش به پشتیبانی بفرستید.',
    ' فهرست این صفحه از روی محصولات همان برند ساخته می‌شود و با موجودی روز فروشگاه یکی است.',
    ' قبل از خرید عنوان، ریجن و نوع تحویل را روی همان کارت کالا بخوانید تا بستهٔ نامربوط انتخاب نشود.',
    ' پشتیبانی از همین سایت پاسخ می‌دهد؛ رمز اکانت، کد ورود پیامکی بازی یا استیم گارد را در هیچ فرمی نگذارید.',
  ];
  for (const pad of pads) {
    if (wordCount(next) >= min) break;
    const candidate = compact(`${next}${pad}`);
    if (wordCount(candidate) <= max) next = candidate;
  }
  if (wordCount(next) > max) {
    next = compact(next.split(' ').slice(0, max).join(' '));
  }
  return next;
}

export function buildBrandHubSeoDescription(hub: BrandHubDef, products: CatalogProduct[]) {
  const regions = regionsOf(products);
  const prices = products.map((item) => tomanPrice(item.minPrice)).filter((value) => value > 0);
  const min = prices.length > 0 ? Math.min(...prices) : 0;
  const fromPrice = min > 0 ? `از ${formatTomanNumber(min)} تومان` : 'با تومان';
  const count = regions.length;
  const sample = regions.slice(0, 3).join('، ');
  const regionBit =
    count > 0 && sample
      ? `${toFaDigits(String(count))} منطقه شامل ${sample}`
      : 'چند منطقه و بستهٔ موجود';
  return fillMetaDescription(
    `خرید ${hub.fa} با تومان ${fromPrice}؛ ${regionBit}، تحویل فوری و بدون رمز اکانت در شارژینو.`,
  );
}

export function googlePlayAmountRows(products: CatalogProduct[]) {
  return GOOGLE_PLAY_AMOUNTS.map((amount) => {
    const match = products.find((product) => {
      const hay = `${product.handle} ${product.title} ${product.titleEn ?? ''} ${product.subtitle ?? ''}`;
      return new RegExp(`(?:^|[^0-9])${amount}(?:\\s*\\$?|(\\s*دلار)|$)`).test(hay) || hay.includes(`-${amount}`);
    });
    return {
      amount,
      label: `${amount} دلاری`,
      href: match?.handle ? ROUTES.catalog(match.handle) : null,
      title: match?.title ?? null,
    };
  });
}

export function buildBrandHubLanding(slug: string, products: CatalogProduct[]): CategoryLanding | null {
  const hub = brandHubBySlug(slug);
  if (!hub || hub.kind !== 'category') {
    return null;
  }
  const regions = regionsOf(products);
  const regionText = regions.length > 0 ? `مناطق موجود شامل ${regions.slice(0, 8).join('، ')} است` : 'ریجن هر کارت روی صفحهٔ همان محصول مشخص است';
  const prices = priceRange(products);
  const priceText = prices || 'قیمت هر بسته روی صفحهٔ محصول به تومان دیده می‌شود';
  const count = products.length;
  const path = brandHubPath(hub);
  const intro = padWords(
    `در شارژینو صفحهٔ ${hub.fa} (${hub.en}) فهرست همان برند است: ${count} محصول مرتبط از روی فیلد برند و شناسه به‌صورت خودکار اینجاست. ${regionText}. ${priceText}. ${deliveryLine(products)} خرید ${hub.fa} با پرداخت ریالی انجام می‌شود و ثبت سفارش از خود سایت است. برای جزئیات بیشتر ${articleLinks(hub)} را بخوانید. آدرس این هاب ${path} است و لینک‌های داخلی به همان محصولات واقعی می‌روند.`,
    150,
    300,
  );
  const faqs: CategoryFaq[] = [
    {
      question: `خرید ${hub.fa} در شارژینو چطور است؟`,
      answer: `محصول را از همین فهرست باز کنید، بسته و ریجن را با حساب‌تان یکی کنید، پرداخت تومان را تا درگاه همان سفارش ادامه دهید. رمز اکانت لازم نیست.`,
    },
    {
      question: 'تحویل چقدر طول می‌کشد؟',
      answer: 'سفارش‌های دیجیتال معمولاً بعد از تأیید پرداخت آماده می‌شوند. زمان دقیق روی صفحهٔ محصول و وضعیت سفارش دیده می‌شود؛ عدد ساختگی در این متن نیست.',
    },
    {
      question: 'اگر کد یا شارژ نرسید چه کار کنم؟',
      answer: 'شماره سفارش را از پیگیری سفارش بردارید و به پشتیبانی شارژینو بفرستید. سفارش دوم نسازید تا پرداخت تکرار نشود.',
    },
  ];
  return {
    seoTitle: `خرید ${hub.fa} (${hub.en}) با تومان | تحویل فوری`,
    seoDescription: buildBrandHubSeoDescription(hub, products),
    introTitle: `خرید ${hub.fa} با تومان`,
    paragraphs: [
      intro,
      `${regionText}. قبل از پرداخت برچسب منطقه را با کشور فروشگاه حساب‌تان یکی کنید. اگر چند نسخه می‌بینید، همان نسخه‌ای را باز کنید که با حساب می‌خواند.`,
      'مبلغ نهایی پیش از درگاه مشخص است. بعد از پرداخت، وضعیت را از [[/invoices|پیگیری سفارش]] ببینید. اگر پرداخت تأیید شده و کالا نرسیده، از [[/uncredited|پرداخت شده، شارژ نشد]] همان شماره را ادامه دهید.',
    ],
    faqs,
  };
}

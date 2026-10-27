import type { Metadata } from 'next';
import Link from 'next/link';
import { InfoPage } from '@/components/content/info-page';
import { InfoPoint } from '@/components/content/info-point';
import { GAME_KEYS_SLUG, GIFT_CARD_SLUG } from '@/lib/catalog-family';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const metadata: Metadata = buildMetadata({
  title: 'درباره فروشگاه شارژ بازی و گیفت کارت',
  description: PAGE_DESCRIPTIONS.about,
  path: ROUTES.about,
});

const shopTopup = ROUTES.category('game-topup');
const shopGift = ROUTES.category(GIFT_CARD_SLUG);
const shopKeys = ROUTES.category(GAME_KEYS_SLUG);

export default function AboutPage() {
  return (
    <InfoPage
      title="درباره شارژینو"
      lede="شارژینو فروشگاه آنلاین شارژ بازی، گیفت‌کارت و کلید بازی برای خرید با تومان است."
      image="/image/pages/about.jpg"
      imageAlt="درباره شارژینو؛ فروشگاه شارژ بازی و گیفت کارت"
      topicHref={ROUTES.about}
    >
      <p className="rounded-2xl bg-card px-4 py-3 text-xs leading-7 text-text/55 ring-1 ring-border">
        [پیش‌نویس — برای تأیید] متن معرفی فروشگاه زیر را پس از بازبینی شما ثابت می‌کنیم. تا آن زمان همین بند به‌عنوان
        محتوای قابل ایندکس روی صفحه درباره می‌ماند.
      </p>
      <InfoPoint title="معرفی فروشگاه" text="خرید دیجیتال با تومان">
        <p>
          شارژینو برای گیمرهایی است که می‌خواهند شارژ بازی، گیفت‌کارت و کلید را با ریال بخرند و مسیر سفارش را روی خود
          سایت ببینند. فهرست کالا از دسته{' '}
          <Link href={shopTopup}>شارژ بازی</Link>، <Link href={shopGift}>گیفت کارت</Link> و{' '}
          <Link href={shopKeys}>کلید بازی</Link> جدا شده تا یوسی، سی‌پی، کد فروشگاه و کلید لانچر قاطی نشود. قیمت نهایی
          قبل از درگاه به تومان مشخص است. پرداخت از مسیر فروشگاه انجام می‌شود؛ اسکریپت و سفارش خارج از سایت بخشی از
          این فروشگاه نیست.
        </p>
        <p>
          هدف صفحه درباره این است که قبل از خرید بدانید با چه مدلی طرف هستید: کالای دیجیتال، تحویل پس از تأیید پرداخت،
          و پیگیری با شماره سفارش. اگر دنبال ابزار عبور از محدودیت شبکه، رمز بازی یا جایزه شانسی هستید، آن کالا اینجا
          فروخته نمی‌شود.
        </p>
      </InfoPoint>
      <InfoPoint title="روش تحویل" text="دیجیتال، بعد از پرداخت">
        <p>
          شارژ مستقیم با شناسه بازیکن به همان اکانت بازی می‌نشیند. گیفت‌کارت کد است که در فروشگاه رسمی وارد می‌شود. کلید
          بازی متن فعال‌سازی برای لانچر رسمی است. گیفت استیم به کتابخانه استیم می‌رود. پرمیوم و استارز تلگرام با یوزرنیم
          عمومی کار می‌کنند. در هیچ‌کدام رمز اکانت، گارد یا کد ورود از شما گرفته نمی‌شود.
        </p>
        <p>
          زمان دقیق به موجودی همان کالا و تأیید درگاه بستگی دارد و روی صفحه محصول دیده می‌شود. اگر پرداخت قطعی شده و
          کالا در سفارش نیست، همان شماره را نگه دارید؛ سفارش دوم معمولاً مشکل را حل نمی‌کند.
        </p>
      </InfoPoint>
      <InfoPoint title="پشتیبانی" text="با شماره سفارش">
        <p>
          وضعیت را از <Link href={ROUTES.invoices}>پیگیری سفارش</Link> ببینید. اگر پول کم شده و شارژ نرسیده، از{' '}
          <Link href={ROUTES.uncredited}>پرداخت شده، شارژ نشد</Link> ادامه دهید. راه‌های گفتگو در{' '}
          <Link href={ROUTES.contact}>تماس با ما</Link> است: تلفن، تلگرام و اینستاگرام رسمی شارژینو. پشتیبانی از روی
          شماره سفارش پاسخ می‌دهد، نه از روی اسکرین بدون شماره.
        </p>
        <p>
          قوانین خرید در <Link href={ROUTES.terms}>قوانین و مقررات</Link> و نگهداری داده در{' '}
          <Link href={ROUTES.privacy}>حریم خصوصی</Link> آمده است. قبل از پرداخت، عنوان کالا، ریجن و فیلد شناسه را با
          حساب خودتان یکی کنید.
        </p>
      </InfoPoint>
    </InfoPage>
  );
}

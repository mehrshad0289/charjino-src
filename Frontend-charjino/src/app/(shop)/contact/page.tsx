import type { Metadata } from 'next';
import Link from 'next/link';
import { InfoPage } from '@/components/content/info-page';
import { InfoPoint } from '@/components/content/info-point';
import { PhoneContactCard } from '@/components/content/phone-contact-card';
import { SocialLinks } from '@/components/content/social-links';
import { JsonLd } from '@/components/seo/json-ld';
import { SUPPORT_PHONE_SCHEMA } from '@/lib/contact';
import { contactOrganizationJsonLd } from '@/lib/json-ld';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const metadata: Metadata = buildMetadata({
  title: 'تماس با پشتیبانی فروشگاه شارژ بازی',
  description: PAGE_DESCRIPTIONS.contact,
  path: ROUTES.contact,
});

export default function ContactPage() {
  return (
    <>
      <JsonLd data={contactOrganizationJsonLd(SUPPORT_PHONE_SCHEMA)} />
      <InfoPage
        title="تماس با ما"
        lede="برای سؤال قبل از خرید، پیگیری سفارش یا مشکل تحویل، از همین راه‌ها با پشتیبانی شارژینو حرف بزنید."
        image="/image/pages/contact.jpg"
        imageAlt="تماس با پشتیبانی شارژینو"
        topicHref={ROUTES.contact}
      >
        <InfoPoint title="چه کمکی می‌کنیم" text="خرید و تحویل دیجیتال">
          <p>
            پشتیبانی شارژینو درباره کالای داخل فروشگاه جواب می‌دهد: شارژ بازی با شناسه بازیکن، گیفت‌کارت، کلید بازی،
            گیفت استیم و پرمیوم تلگرام. قبل از خرید می‌توانید ریجن، فیلدهای لازم و مسیر تحویل همان صفحه را بپرسید. بعد
            از پرداخت، موضوع را با شماره سفارش بفرستید تا وضعیت همان تراکنش بررسی شود.
          </p>
          <p>
            رمز اکانت بازی، کد ورود، گارد استیم و ابزار عبور از محدودیت شبکه را از شما نمی‌خواهیم و از این مسیر هم
            تحویل نمی‌دهیم. اگر پیامک یا لینکی رمز خواست، آن را متعلق به شارژینو ندانید. سفارش فقط از خود سایت ثبت
            می‌شود.
          </p>
        </InfoPoint>
        <InfoPoint title="تلفن پشتیبانی" text="تماس مستقیم شارژینو">
          <PhoneContactCard />
          <p>
            هنگام تماس، عنوان کالا و اگر پرداخت کرده‌اید شماره سفارش را آماده داشته باشید. شناسه بازیکن را از روی
            پروفایل بازی بخوانید، نه از حافظه. ساعات پاسخ ممکن است با صف سفارش هم‌زمان باشد؛ اگر اشغال بود همان مورد را
            در تلگرام یا اینستاگرام رسمی هم می‌توانید بفرستید.
          </p>
        </InfoPoint>
        <InfoPoint title="شبکه‌های اجتماعی" text="اینستاگرام و تلگرام">
          <SocialLinks />
          <p>
            فقط همین حساب‌های لینک‌شده در صفحه تماس متعلق به فروشگاه است. از طریق همین مسیر می‌توانید وضعیت سفارش را با
            عکس رسید و شماره پیگیری بفرستید. لینک کالا را از نوار آدرس شارژینو کپی کنید تا صفحه اشتباه بررسی نشود.
          </p>
        </InfoPoint>
        <InfoPoint title="پیگیری سفارش" text="شماره سفارش شما">
          <p>
            شماره سفارش را از <Link href={ROUTES.invoices}>پیگیری سفارش</Link> بردارید. اگر پرداخت شده و شارژ، کد یا
            کلید نرسیده، از <Link href={ROUTES.uncredited}>پرداخت شده، شارژ نشد</Link> ادامه دهید. قوانین تحویل در{' '}
            <Link href={ROUTES.terms}>قوانین و مقررات</Link> و معرفی فروشگاه در <Link href={ROUTES.about}>درباره
            شارژینو</Link> آمده است.
          </p>
        </InfoPoint>
      </InfoPage>
    </>
  );
}

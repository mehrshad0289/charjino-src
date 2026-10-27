import type { Metadata } from 'next';
import Link from 'next/link';
import { InfoPage } from '@/components/content/info-page';
import { SocialLinks } from '@/components/content/social-links';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const metadata: Metadata = buildMetadata({
  title: 'پرداخت شده، شارژ نشد',
  description: PAGE_DESCRIPTIONS.uncredited,
  path: ROUTES.uncredited,
  noindex: true,
  follow: true,
});

export default function UncreditedPage() {
  return (
    <InfoPage
      title="پرداخت شده، شارژ نشد"
      lede="اگر خرید را تا درگاه جلو برده‌ای، پول از حسابت کم شده، ولی شارژ، گیفت یا کلید به اکانت نرسیده، سفارش گم نشده است. با شماره سفارش به پشتیبانی پیام بده."
      image="/image/pages/uncredited.svg"
      imageAlt="پرداخت شده ولی شارژ نشده"
    >
      <ol className="flex list-decimal flex-col gap-2 ps-5">
        <li>
          در{' '}
          <Link href={ROUTES.invoices} className="font-semibold text-primary-600">
            پیگیری سفارش
          </Link>{' '}
          شماره سفارش و وضعیت پرداخت را ببین.
        </li>
        <li>همان شماره را، همراه با ساعت پرداخت، در تلگرام یا اینستاگرام بفرست.</li>
        <li>تا وقتی رسید و شماره سفارش نرسیده، شارژ دوباره از روی حدس انجام نمی‌شود.</li>
      </ol>
      <SocialLinks />
      <p>
        راه‌های دیگر در{' '}
        <Link href={ROUTES.contact} className="font-semibold text-primary-600">
          تماس با ما
        </Link>{' '}
        است.
      </p>
    </InfoPage>
  );
}

import type { ReactNode } from 'react';
import Link from 'next/link';

import { JsonLd } from '@/components/seo/json-ld';
import { GIFT_CARD_SLUG } from '@/lib/catalog-family';
import { faqPageJsonLd } from '@/lib/json-ld';
import { ROUTES } from '@/lib/routes';

import {
  FaqAlertIcon,
  FaqBoltIcon,
  FaqChevronIcon,
  FaqGamepadIcon,
  FaqGiftIcon,
  FaqGridIcon,
  FaqLockIcon,
  FaqSearchIcon,
} from './home-faq-icons';

const GAME_TOPUP = ROUTES.category('game-topup');
const GIFT_CARDS = ROUTES.category(GIFT_CARD_SLUG);

const FAQ_ITEMS: {
  id: string;
  question: string;
  answerText: string;
  important?: boolean;
  icon: ReactNode;
  answer: ReactNode;
}[] = [
  {
    id: 'password',
    question: 'آیا برای شارژ، رمز اکانت لازم است؟',
    answerText:
      'خیر. برای محصولات مبتنی بر Player ID یا User ID فقط شناسهٔ بازیکن کافی است و رمز اکانت گرفته نمی‌شود. رمز، ایمیل ورود یا کد OTP را برای کسی نفرستید.',
    icon: <FaqLockIcon />,
    answer: (
      <>
        خیر. برای محصولات مبتنی بر Player ID یا User ID فقط شناسهٔ بازیکن کافی است و{' '}
        <strong className="font-semibold text-text">رمز اکانت گرفته نمی‌شود</strong>. رمز، ایمیل ورود یا کد OTP را برای
        کسی نفرستید.
      </>
    ),
  },
  {
    id: 'how',
    question: 'شارژ بازی چطور انجام می‌شود؟',
    answerText:
      'بازی و بسته را از کاتالوگ انتخاب کنید، Player ID (یا کد لازم) را وارد کنید، با تومان پرداخت کنید. پس از تأیید پرداخت، شارژ روی همان شناسه اعمال می‌شود و وضعیت را از پیگیری سفارش ببینید.',
    icon: <FaqBoltIcon />,
    answer: (
      <>
        بازی و بسته را از <Link href={GAME_TOPUP}>کاتالوگ</Link> انتخاب کنید، Player ID (یا کد لازم) را وارد کنید، با
        تومان پرداخت کنید. پس از تأیید پرداخت، شارژ روی همان شناسه اعمال می‌شود و وضعیت را از{' '}
        <Link href={ROUTES.invoices}>پیگیری سفارش</Link> ببینید.
      </>
    ),
  },
  {
    id: 'games',
    question: 'کدام بازی‌ها را می‌توان شارژ کرد؟',
    answerText:
      'بازی‌های منتخب موبایل و آنلاین در دسته‌بندی شارژ بازی؛ فهرست به‌روز است — قبل از خرید صفحهٔ همان بازی را چک کنید.',
    icon: <FaqGridIcon />,
    answer: (
      <>
        بازی‌های منتخب موبایل و آنلاین در <Link href={GAME_TOPUP}>دسته‌بندی شارژ بازی</Link>؛ فهرست به‌روز است — قبل
        از خرید صفحهٔ همان بازی را چک کنید.
      </>
    ),
  },
  {
    id: 'gift',
    question: 'چه گیفت‌کارت‌هایی موجود است؟',
    answerText:
      'گیفت‌کارت و کدهای دیجیتال مثل Steam، PlayStation، Xbox و Nintendo بر اساس موجودی روز. منطقهٔ کارت را با اکانت تطبیق دهید.',
    icon: <FaqGiftIcon />,
    answer: (
      <>
        گیفت‌کارت و کدهای دیجیتال مثل Steam، PlayStation، Xbox و Nintendo بر اساس موجودی روز در{' '}
        <Link href={GIFT_CARDS}>بخش گیفت‌کارت</Link>. منطقهٔ کارت را با اکانت تطبیق دهید.
      </>
    ),
  },
  {
    id: 'track',
    question: 'چطور وضعیت سفارش را ببینم؟',
    answerText:
      'وضعیت سفارش را از صفحهٔ پیگیری سفارش، با شماره سفارش یا اطلاعاتی که هنگام خرید وارد کرده‌اید، ببینید.',
    icon: <FaqSearchIcon />,
    answer: (
      <>
        وضعیت سفارش را از صفحهٔ <Link href={ROUTES.invoices}>پیگیری سفارش</Link>، با شماره سفارش یا اطلاعاتی که هنگام
        خرید وارد کرده‌اید، ببینید.
      </>
    ),
  },
  {
    id: 'uncredited',
    question: 'پرداخت کردم ولی شارژ نشد؛ چه کار کنم؟',
    important: true,
    answerText:
      'اول وضعیت را در صفحهٔ پیگیری سفارش بررسی کنید. اگر پرداخت تأیید شده و شارژ نیامده، در صفحهٔ پرداخت‌شده شارژ نشد درخواست ثبت کنید و Player ID را دوباره دقیق بررسی کنید.',
    icon: <FaqAlertIcon />,
    answer: (
      <>
        اول وضعیت را در صفحهٔ <Link href={ROUTES.invoices}>پیگیری سفارش</Link> بررسی کنید. اگر پرداخت تأیید شده و شارژ
        نیامده، در صفحهٔ <Link href={ROUTES.uncredited}>پرداخت شده، شارژ نشد</Link> درخواست ثبت کنید و Player ID را
        دوباره دقیق بررسی کنید.
      </>
    ),
  },
];

const faqItemClass =
  'group overflow-hidden rounded-2xl border-[1.5px] border-border bg-canvas/85 transition-[border-color,box-shadow,background] duration-200 open:border-primary-600/55 open:bg-card open:shadow-[0_0_0_3px_rgb(5_99_252/0.12),0_10px_28px_rgb(5_99_252/0.08)] dark:border-[#1C3571] dark:bg-[#0A2153]/40 dark:open:border-[#00e5ff]/50 dark:open:bg-[#0E3077]/50 dark:open:shadow-[0_0_0_1px_#00e5ff/30,0_0_28px_-8px_#00e5ff/35]';

const summaryClass =
  'flex min-h-12 cursor-pointer list-none items-center gap-3 px-3 py-3.5 text-sm font-bold leading-snug text-text select-none min-[521px]:px-4 min-[521px]:py-4 min-[521px]:text-[14.5px] [&::-webkit-details-marker]:hidden [&::marker]:content-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600';

const answerClass =
  'border-t border-dashed border-border/90 px-4 pb-4 pt-3 text-[13.5px] leading-[1.85] font-medium text-text-muted min-[521px]:ms-16 min-[521px]:me-4 [&_a]:font-bold [&_a]:text-primary-600 [&_a]:no-underline hover:[&_a]:underline dark:[&_a]:text-[#00e5ff]';

export function HomeFaq() {
  return (
    <section className="w-full" aria-labelledby="home-faq-heading">
      <div className="w-full px-4 lg:mx-auto lg:max-w-7xl">
        <div className="relative overflow-hidden lg:mx-auto lg:max-w-[760px] lg:rounded-3xl lg:border lg:border-border lg:bg-[radial-gradient(ellipse_80%_60%_at_100%_0%,rgb(5_99_252/0.1),transparent_55%),radial-gradient(ellipse_60%_50%_at_0%_100%,rgb(180_45_108/0.07),transparent_50%),var(--color-card)] lg:p-7 lg:pb-5 lg:shadow-[0_10px_36px_rgb(18_32_51/0.06)] dark:lg:border-[#1C3571] dark:lg:bg-[radial-gradient(ellipse_90%_70%_at_100%_0%,rgb(0_229_255/0.12),transparent_55%),radial-gradient(ellipse_70%_50%_at_0%_100%,rgb(5_99_252/0.15),transparent_50%),linear-gradient(180deg,#0E3077_0%,#07183F_100%)] dark:lg:shadow-[0_20px_48px_-16px_rgb(0_0_0/0.55),0_0_32px_-20px_rgb(0_229_255/0.25)]">
          <div
            className="pointer-events-none absolute inset-0 hidden opacity-45 lg:block dark:opacity-30"
            aria-hidden
            style={{
              backgroundImage: `url("data:image/svg+xml,%3Csvg width='56' height='50' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M28 2 L52 16 L52 40 L28 54 L4 40 L4 16 Z' fill='none' stroke='%230563FC' stroke-opacity='0.07' stroke-width='1'/%3E%3C/svg%3E")`,
              backgroundSize: '56px 50px',
            }}
          />

          <div className="relative z-10 mb-5 flex items-start gap-3.5 lg:mb-6">
            <div
              className="grid size-12 shrink-0 place-items-center rounded-[14px] text-white "
              aria-hidden
            >
              <FaqGamepadIcon />
            </div>
            <div>
              <h2 id="home-faq-heading" className="mt-0.5 mb-1 text-[clamp(22px,4vw,28px)] leading-snug font-bold text-text">
                سؤالات پرتکرار
              </h2>
              <p className="m-0 text-[13px] leading-normal font-medium text-text-muted">
                درباره شارژ بازی، گیفت‌کارت و پیگیری سفارش در شارژینو.
              </p>
            </div>
          </div>

          <div className="relative z-10 flex flex-col gap-2.5">
            {FAQ_ITEMS.map((item, index) => (
              <details key={item.id} className={faqItemClass} {...(index === 0 ? { open: true } : {})}>
                <summary className={summaryClass}>
                  <span className="grid size-9 shrink-0 place-items-center rounded-[10px] bg-primary-600/8 text-primary-600 transition-colors duration-200 group-open:bg-primary-600/14 dark:bg-[#00e5ff]/10 dark:text-[#00e5ff] dark:group-open:bg-[#00e5ff]/18">
                    {item.icon}
                  </span>
                  <span className="min-w-0 flex-1 text-start">
                    {item.question}
                    {item.important ? (
                      <span className="ms-1.5 inline-block rounded-md bg-sale/8 px-2 py-0.5 align-middle text-[11px] font-bold text-sale">
                        مهم
                      </span>
                    ) : null}
                  </span>
                  <span className="grid size-7 shrink-0 place-items-center rounded-lg border border-border bg-card text-text-muted transition-[transform,background,border-color,color] duration-200 group-open:rotate-180 group-open:border-primary-600 group-open:bg-primary-600 group-open:text-white dark:group-open:border-[#00e5ff] dark:group-open:bg-[#00e5ff] dark:group-open:text-[#07183F]">
                    <FaqChevronIcon />
                  </span>
                </summary>
                <div className={answerClass}>{item.answer}</div>
              </details>
            ))}
          </div>
        </div>
      </div>
      <JsonLd data={faqPageJsonLd(FAQ_ITEMS.map((item) => ({ name: item.question, text: item.answerText })))} />
    </section>
  );
}

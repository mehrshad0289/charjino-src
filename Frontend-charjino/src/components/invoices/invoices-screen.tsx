'use client';

import { useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { DryRunNotice } from '@/components/checkout/dry-run-notice';
import { Button, SearchNormalIcon, WaveDivider } from '@/components/ui';
import { formatFaDateTime, toFaDigits } from '@/lib/fa';
import { orderStatusLabel } from '@/lib/order-labels';
import { formatTomanNumber } from '@/lib/price';
import { hotjarSuppress } from '@/lib/hotjar';
import { ROUTES } from '@/lib/routes';
import { authHref } from '@/lib/safe-path';
import type { CustomerOrder } from '@/types/catalog';

type InvoicesScreenProps = {
  signedIn: boolean;
  orders: CustomerOrder[];
  page: number;
  totalPages: number;
  liveOrderEnabled?: boolean;
  listError?: string | null;
};

export function InvoicesScreen({
  signedIn,
  orders,
  page,
  totalPages,
  liveOrderEnabled = false,
  listError = null,
}: InvoicesScreenProps) {
  const [invoice, setInvoice] = useState('');
  const [loading, setLoading] = useState(false);
  const router = useRouter();
  const inputRef = useRef<HTMLInputElement>(null);

  function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const number = invoice.trim();
    if (number) {
      setLoading(true);
      router.push(ROUTES.invoice(number));
      return;
    }
    inputRef.current?.focus();
  }

  return (
    <>
      <form
        onSubmit={handleSubmit}
        className="container mx-auto flex flex-col items-center justify-center gap-5 px-4 py-12 xl:max-w-7xl"
      >
        <div className="flex w-full flex-col gap-3">
          <h1 className="text-3xl font-bold sm:text-4xl">سفارشت را پیدا کن</h1>
          <p className="text-sm text-text/60">
            با شماره فاکتور یا از فهرست سفارش‌های همین حساب، وضعیت واقعی را ببین.
          </p>
        </div>
        <DryRunNotice liveOrderEnabled={liveOrderEnabled} />
        <div className="flex w-full flex-col gap-2">
          <h2 className="text-xs font-medium">شماره فاکتور</h2>
          <input
            {...hotjarSuppress}
            type="text"
            onChange={(event) => setInvoice(event.target.value)}
            value={invoice}
            maxLength={20}
            id="invoice"
            name="invoice"
            ref={inputRef}
            placeholder="CHXXXXXXXXXXXXXX"
            className="max-w-[576px] rounded-md bg-gray-300 px-3 py-2 text-xs text-text placeholder:text-text/55"
          />
        </div>
        <div className="flex w-full justify-start text-sm font-medium">
          <Button type="submit" icon={<SearchNormalIcon size={20} />} loading={loading}>
            جستجوی تراکنش
          </Button>
        </div>

        {!signedIn ? (
          <div className="flex w-full flex-col gap-3 rounded-2xl bg-paper p-6 ring-1 ring-border">
            <p className="text-sm text-text/70">برای دیدن فهرست سفارش‌ها با پیامک وارد شو. بعد از ورود به حساب من می‌روی.</p>
            <Button href={authHref(ROUTES.profileOrders)} variant="outline">
              ورود به حساب من
            </Button>
          </div>
        ) : listError ? (
          <p className="w-full text-sm text-error-200">{listError}</p>
        ) : orders.length === 0 ? (
          <p className="w-full rounded-2xl bg-paper px-4 py-8 text-center text-sm text-text/60 ring-1 ring-border">
            هنوز سفارشی برای این حساب نیست.
          </p>
        ) : (
          <ul className="flex w-full flex-col gap-3">
            {orders.map((order) => (
              <li key={order.id}>
                <Link
                  href={ROUTES.invoice(order.number)}
                  className="flex flex-col gap-1 rounded-2xl bg-paper px-4 py-4 ring-1 ring-border transition-colors hover:ring-primary-600"
                >
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-semibold text-text">{order.number}</span>
                    <span className="text-sm text-primary-600">{orderStatusLabel(order.status)}</span>
                  </div>
                  <p className="text-sm text-text/60">
                    {order.productTitle ?? 'محصول'} {order.skuName ? `· ${order.skuName}` : ''}
                  </p>
                  <p className="text-xs text-text/45">
                    {formatTomanNumber(order.amountToman)} تومان · {formatFaDateTime(order.createdAt)}
                  </p>
                </Link>
              </li>
            ))}
          </ul>
        )}

        {signedIn && totalPages > 1 ? (
          <div className="flex items-center gap-4 text-sm">
            {page > 1 ? (
              <Link href={`${ROUTES.invoices}?page=${page - 1}`} className="text-primary-600">
                قبلی
              </Link>
            ) : null}
            <span className="text-text/60">
              صفحه {toFaDigits(page)} از {toFaDigits(totalPages)}
            </span>
            {page < totalPages ? (
              <Link href={`${ROUTES.invoices}?page=${page + 1}`} className="text-primary-600">
                بعدی
              </Link>
            ) : null}
          </div>
        ) : null}
      </form>
      <WaveDivider />
    </>
  );
}

'use client';

import { useState } from 'react';
import { revealOrderCodes } from '@/actions/checkout.actions';
import { Button } from '@/components/ui';
import { notify } from '@/lib/toast';
import { hotjarSuppress } from '@/lib/hotjar';
import { ROUTES } from '@/lib/routes';
import type { CustomerOrder } from '@/types/catalog';
import styles from './profile.module.css';

export function OrderDelivery({ order }: { order: CustomerOrder }) {
  const [codes, setCodes] = useState<string[] | null>(null);
  const [loading, setLoading] = useState(false);
  const delivery = order.delivery;
  const family = order.fulfillmentType || '';
  const status = order.status;
  const canReveal =
    (status === 'completed' || status === 'needs_review' || status === 'delivered' || status === 'partial') &&
    (delivery?.itemCount ?? 0) > 0;

  async function reveal() {
    setLoading(true);
    const result = await revealOrderCodes(order.number);
    setLoading(false);
    if (!result.ok) {
      notify.error(result.message);
      return;
    }
    setCodes(result.data.items);
  }

  async function copy(text: string) {
    try {
      await navigator.clipboard.writeText(text);
      notify.success('کپی شد');
    } catch {
      notify.error('کپی نشد');
    }
  }

  return (
    <div className={styles.panel} {...hotjarSuppress}>
      <h2 className="mb-4 text-sm font-semibold text-text">تحویل</h2>
      {delivery?.summary ? <p className="text-sm leading-7 text-text/80">{delivery.summary}</p> : null}
      {status === 'needs_review' || status === 'partial' ? (
        <p className="mt-3 text-sm text-text/80">
          در حال بررسی توسط پشتیبانی شارژینو.{' '}
          <a className="text-primary-600" href={ROUTES.profileTicketNew}>
            ثبت تیکت
          </a>
        </p>
      ) : null}
      {order.failMessageFa ? <p className="mt-3 text-sm text-error-200">{order.failMessageFa}</p> : null}
      {order.refund ? (
        <p className="mt-3 text-sm text-text/70">
          بازگشت وجه: {order.refund.method === 'wallet' ? 'کیف پول شارژینو' : 'دستی'} · {order.refund.status}
        </p>
      ) : null}
      {order.guide?.url ? (
        <p className="mt-3 text-sm">
          <a className="text-primary-600" href={order.guide.url}>
            {order.guide.title || 'راهنمای ردیم'}
          </a>
        </p>
      ) : null}
      {canReveal ? (
        <div className="mt-4 flex flex-wrap gap-2">
          <Button type="button" loading={loading} onClick={() => void reveal()}>
            {family === 'gamekey' ? 'نمایش کلید' : 'نمایش کد'}
          </Button>
          {codes && codes.length > 1 ? (
            <Button type="button" variant="outline" onClick={() => void copy(codes.join('\n'))}>
              کپی همه
            </Button>
          ) : null}
        </div>
      ) : null}
      {codes ? (
        <ul className="mt-4 space-y-2" {...hotjarSuppress}>
          {codes.map((item, index) => (
            <li key={`${index}-${item.slice(0, 4)}`} className="flex items-center justify-between gap-3 rounded-xl bg-card px-3 py-2 ring-1 ring-border" {...hotjarSuppress}>
              <code className="min-w-0 break-all text-sm text-text" dir="ltr" {...hotjarSuppress}>
                {item}
              </code>
              <Button type="button" variant="outline" size="M" onClick={() => void copy(item)}>
                کپی
              </Button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}

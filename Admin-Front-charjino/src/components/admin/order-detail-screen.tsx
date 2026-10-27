'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';

import {
  abandonFazerOrder,
  cancelOrder,
  linkFazerOrder,
  refundOrder,
  refreshOrderFromFazer,
  revealAdminOrder,
  retryFulfillment
} from '@/actions/admin.actions';
import { Alert, Badge, Button, TextField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { formatFaDate, formatToman, toFaDigits } from '@/lib/fa';
import {
  FULFILLMENT_STATUS_LABEL,
  ORDER_PIPELINE,
  PAYMENT_STATUS_LABEL,
  TIMELINE_LABEL,
  orderStatusColor,
  orderStatusLabel
} from '@/lib/order-labels';
import type { AdminOrder } from '@/types/admin';

const UNPAID = new Set(['draft', 'pending_payment', 'awaiting_payment']);
const REFUNDABLE = new Set(['failed', 'partial', 'delivered', 'completed', 'refund_pending', 'fulfilling', 'processing', 'needs_review']);

function canRedeliver(order: AdminOrder) {
  const attempt = order.fulfillmentAttempts?.at(-1);
  return Boolean(attempt && ['failed', 'refund', 'refunded', 'abandoned'].includes(attempt.status));
}

function playerFields(requirements: AdminOrder['requirements']) {
  if (!requirements || typeof requirements !== 'object') return [];
  return Object.entries(requirements).filter(([key]) => !/pass|otp|pin|secret|token/i.test(key) || /steamlogin/i.test(key));
}

type OrderDetailScreenProps = {
  order: AdminOrder;
};

export function OrderDetailScreen({ order }: OrderDetailScreenProps) {
  const router = useRouter();
  const [reason, setReason] = useState('');
  const [fazerId, setFazerId] = useState('');
  const [busy, setBusy] = useState<string | null>(null);
  const [, setMessage] = useToastMessage();
  const payments = order.payments ?? [];
  const attempts = order.fulfillmentAttempts ?? [];
  const timeline = order.timeline ?? [];
  const fields = playerFields(order.requirements);
  const latest = attempts.at(-1);

  async function run(key: string, action: () => Promise<{ ok: true } | { ok: false; message: string }>) {
    if (!window.confirm('این عملیات را تأیید می‌کنید؟')) return;
    setBusy(key);
    const result = await action();
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'وضعیت سفارش به‌روز شد.' });
    router.refresh();
  }

  return (
    <div className='space-y-6'>
      <Link href='/orders' className='text-theme-sm font-medium text-brand-500'>
        بازگشت به سفارش‌ها
      </Link>

      <ol className='flex flex-wrap gap-2'>
        {ORDER_PIPELINE.map((step) => (
          <li key={step}>
            <Badge size='sm' color={order.status === step ? orderStatusColor(step) : 'light'}>
              {orderStatusLabel(step)}
            </Badge>
          </li>
        ))}
        {!(ORDER_PIPELINE as readonly string[]).includes(order.status) ? (
          <li>
            <Badge size='sm' color={orderStatusColor(order.status)}>
              {orderStatusLabel(order.status)}
            </Badge>
          </li>
        ) : null}
      </ol>

      <div className='grid gap-3 sm:grid-cols-2'>
        <p className='text-sm text-gray-500'>شماره: {order.number}</p>
        <p className='text-sm text-gray-500'>
          وضعیت: <Badge size='sm' color={orderStatusColor(order.status)}>{order.statusLabelFa || orderStatusLabel(order.status)}</Badge>
        </p>
        <p className='text-sm text-gray-500'>تاریخ: {formatFaDate(order.createdAt)}</p>
        <p className='text-sm text-gray-500'>انقضا: {formatFaDate(order.expiresAt)}</p>
        <p className='text-sm text-gray-500'>موبایل: {order.userPhoneNumber || '—'}</p>
        <p className='text-sm text-gray-500'>مبلغ: {formatToman(order.amountToman)}</p>
        {order.couponCode ? (
          <p className='text-sm text-gray-500'>
            کد تخفیف: {order.couponCode}
            {order.discountToman ? ` · ${formatToman(order.discountToman)}` : ''}
            {order.subtotalToman ? ` از ${formatToman(order.subtotalToman)}` : ''}
          </p>
        ) : null}
        <p className='text-sm text-gray-500'>نوع: {order.fulfillmentType || '—'}</p>
        {order.holdReason ? <p className='text-sm text-error-500'>hold: {order.holdReason}</p> : null}
        {order.needsReviewReason ? <p className='text-sm text-error-500'>بررسی: {order.needsReviewReason}</p> : null}
      </div>

      <section className='space-y-2 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>ورودی خریدار</h3>
        {fields.length === 0 ? (
          <p className='text-sm text-gray-500'>فیلد خریدار در این سفارش نیست.</p>
        ) : (
          fields.map(([key, value]) => (
            <p key={key} className='text-sm text-gray-500'>
              {key}: {String(value ?? '—')}
            </p>
          ))
        )}
      </section>

      <section className='space-y-2 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>آیتم و قیمت</h3>
        {(order.items ?? []).map((item) => (
          <p key={item.id} className='text-sm text-gray-500'>
            {item.fulfillmentType} × {toFaDigits(item.quantity)} · {formatToman(item.totalToman ?? 0)} · USD {item.totalCostUsd || '—'} · نرخ {item.fxRate || '—'} · سود {item.marginPercent || '—'}٪
          </p>
        ))}
        <p className='text-theme-xs text-gray-500'>
          نرخ دلار سفارش: {order.fxRateSnapshot || '—'} · بهای خرید USD: {order.costUsdSnapshot || '—'}
        </p>
        {order.productId ? (
          <Link href={`/catalog/products/${order.productId}`} className='text-theme-sm font-medium text-brand-500'>
            ویرایش محصول در کاتالوگ
          </Link>
        ) : null}
      </section>

      <section className='space-y-2 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>پرداخت</h3>
        {payments.length === 0 ? (
          <p className='text-sm text-gray-500'>پرداختی ثبت نشده است.</p>
        ) : (
          payments.map((payment) => (
            <div key={payment.id} className='text-sm text-gray-500'>
              <Badge size='sm' color={payment.status === 'verified' ? 'success' : payment.status === 'failed' ? 'error' : 'light'}>
                {PAYMENT_STATUS_LABEL[payment.status] ?? payment.status}
              </Badge>
              <span className='ms-2'>
                {payment.provider} · {formatToman(payment.amountToman)} · {formatFaDate(payment.createdAt)}
              </span>
            </div>
          ))
        )}
      </section>

      <section className='space-y-2 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>تلاش‌های تأمین</h3>
        {attempts.length === 0 ? (
          <p className='text-sm text-gray-500'>تلاش تحویلی هنوز ثبت نشده.</p>
        ) : (
          attempts.map((attempt) => (
            <div key={attempt.id} className='space-y-1 rounded-lg bg-gray-50 p-3 text-sm text-gray-500 dark:bg-white/5'>
              <Badge size='sm' color={attempt.status === 'completed' ? 'success' : attempt.status === 'failed' ? 'error' : 'warning'}>
                #{toFaDigits(attempt.attemptNo ?? 1)} {FULFILLMENT_STATUS_LABEL[attempt.status] ?? attempt.status}
              </Badge>
              <p>Fazer: {attempt.fazerOrderId || '—'} · kind: {attempt.fazerKind || '—'} · raw: {attempt.fazerStatus || '—'}</p>
              <p>کلید: {attempt.idempotencyKeyShort || '—'} · HTTP {attempt.httpStatus ?? '—'}</p>
              {attempt.errorCode ? <p>کد: {attempt.errorCode}</p> : null}
              {attempt.failReason ? <p>fail: {attempt.failReason}</p> : null}
              <p>
                poll: {toFaDigits(attempt.pollCount ?? 0)} · reconcile: {toFaDigits(attempt.reconcileCount ?? 0)} · next:{' '}
                {formatFaDate(attempt.nextPollAt)}
              </p>
              {attempt.requestRedacted ? (
                <pre className='overflow-auto text-theme-xs'>{JSON.stringify(attempt.requestRedacted, null, 2)}</pre>
              ) : null}
              {attempt.responseRedacted ? (
                <pre className='overflow-auto text-theme-xs'>{JSON.stringify(attempt.responseRedacted, null, 2)}</pre>
              ) : null}
            </div>
          ))
        )}
      </section>

      {(order.webhookEvents ?? []).length > 0 ? (
        <section className='space-y-2 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>وبهوک</h3>
          {order.webhookEvents?.map((event, index) => (
            <p key={`${event.eventId}-${index}`} className='text-sm text-gray-500'>
              {event.event} · {event.dataStatus} · {formatFaDate(event.receivedAt)}
            </p>
          ))}
        </section>
      ) : null}

      <section className='space-y-2 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>خط زمان</h3>
        {timeline.length === 0 ? (
          <p className='text-sm text-gray-500'>رویدادی ثبت نشده است.</p>
        ) : (
          <ol className='space-y-2'>
            {timeline.map((event, index) => (
              <li key={`${event.at}-${index}`} className='text-sm text-gray-500'>
                {formatFaDate(event.at)} · {TIMELINE_LABEL[event.type ?? ''] ?? event.type ?? 'رویداد'}
                {event.status ? ` (${orderStatusLabel(event.status)})` : ''}
                {event.note ? ` — ${event.note}` : ''}
              </li>
            ))}
          </ol>
        )}
      </section>

      {order.customerMessage ? <Alert variant='info' title='پیام مشتری' message={order.customerMessage} /> : null}

      <div className='flex flex-wrap gap-3'>
        {UNPAID.has(order.status) ? (
          <Button type='button' loading={busy === 'cancel'} disabled={busy !== null} onClick={() => void run('cancel', () => cancelOrder(order.id))}>
            لغو سفارش پرداخت‌نشده
          </Button>
        ) : null}
        <Button type='button' variant='outline' loading={busy === 'refresh'} disabled={busy !== null} onClick={() => void run('refresh', () => refreshOrderFromFazer(order.id))}>
          تازه‌سازی از Fazer
        </Button>
        {canRedeliver(order) ? (
          <Button type='button' variant='outline' loading={busy === 'retry'} disabled={busy !== null} onClick={() => void run('retry', () => retryFulfillment(order.id))}>
            تحویل دوباره
          </Button>
        ) : null}
        <Button
          type='button'
          variant='outline'
          loading={busy === 'reveal'}
          disabled={busy !== null}
          onClick={() =>
            void run('reveal', async () => {
              const result = await revealAdminOrder(order.id);
              if (!result.ok) return result;
              setMessage({ variant: 'success', text: result.data.items.join(' · ') });
              return { ok: true as const };
            })
          }
        >
          نمایش کد
        </Button>
      </div>

      {latest?.status === 'unknown' ? (
        <div className='space-y-3'>
          <TextField label='شناسه Fazer (ord-…)' value={fazerId} onChange={setFazerId} />
          <div className='flex flex-wrap gap-3'>
            <Button
              type='button'
              loading={busy === 'link'}
              disabled={busy !== null || !fazerId.trim()}
              onClick={() => void run('link', () => linkFazerOrder(order.id, fazerId.trim()))}
            >
              اتصال به سفارش Fazer
            </Button>
            <Button
              type='button'
              variant='outline'
              loading={busy === 'abandon'}
              disabled={busy !== null}
              onClick={() => void run('abandon', () => abandonFazerOrder(order.id, reason || 'ثبت نشده در Fazer'))}
            >
              ثبت نشده در Fazer
            </Button>
          </div>
        </div>
      ) : null}

      {REFUNDABLE.has(order.status) ? (
        <div className='space-y-3'>
          <TextField label='یادداشت بازگشت' value={reason} onChange={setReason} placeholder='توضیح پشتیبانی' />
          <div className='flex flex-wrap gap-3'>
            <Button
              type='button'
              loading={busy === 'wallet'}
              disabled={busy !== null}
              onClick={() => void run('wallet', () => refundOrder(order.id, { method: 'wallet', note: reason }))}
            >
              برگشت به کیف پول
            </Button>
            <Button
              type='button'
              variant='outline'
              loading={busy === 'manual'}
              disabled={busy !== null}
              onClick={() => void run('manual', () => refundOrder(order.id, { method: 'manual_gateway', note: reason, externalRef: 'manual' }))}
            >
              بازگشت دستی انجام شد
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

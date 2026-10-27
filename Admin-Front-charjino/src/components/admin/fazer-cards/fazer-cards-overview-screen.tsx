'use client';

import Link from 'next/link';

import { Alert, Badge, Button } from '@/components/ui';
import { formatFaDate, toFaDigits } from '@/lib/fa';
import { FAZER_CARDS_NAV } from '@/lib/fazer-cards-nav';
import type { CatalogSyncStatus, FazerCardsStatus } from '@/types/admin';

import { FazerCardsConfirmModal } from './fazer-cards-confirm-modal';
import { FazerCardsResult } from './fazer-cards-result';
import { useFazerCardsProxy } from './use-fazer-cards-proxy';

type FazerCardsOverviewScreenProps = {
  status?: FazerCardsStatus;
  lastSync?: CatalogSyncStatus;
  error?: string;
};

export function FazerCardsOverviewScreen({ status, lastSync, error }: FazerCardsOverviewScreenProps) {
  const proxy = useFazerCardsProxy();
  const wallet = status?.account?.wallet;
  const syncRow = lastSync && lastSync.status !== 'idle' ? lastSync : null;

  return (
    <div className='space-y-6'>
      {error ? <Alert variant='error' title='خطا' message={error} /> : null}

      {!status?.configured ? (
        <Alert
          variant='warning'
          title='کلید FazerCards'
          message='از صفحهٔ تنظیمات کلید Reseller را ذخیره کن. مقدار کامل هرگز برنمی‌گردد.'
        />
      ) : null}
      {status?.accountError ? <Alert variant='warning' title='حساب خوانده نشد' message={status.accountError} /> : null}

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <p className='text-theme-xs text-gray-500'>اتصال</p>
          <div className='mt-2'>
            <Badge color={status?.configured ? 'success' : 'error'}>
              {status?.configured ? `تنظیم شده · ****${status.keyLast4 || ''}` : 'کلید نیست'}
            </Badge>
          </div>
          <p className='text-theme-xs mt-2 text-gray-500'>{status?.host || 'api.fzr.cards'}</p>
        </div>
        <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <p className='text-theme-xs text-gray-500'>والت USD</p>
          <p className='mt-2 text-lg font-semibold text-gray-800 dark:text-white/90'>
            {wallet ? `${wallet} USD` : '—'}
          </p>
          <p className='text-theme-xs mt-1 text-gray-500'>
            {status?.account?.email || status?.account?.login || 'حساب خوانده نشد'}
          </p>
        </div>
        <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <p className='text-theme-xs text-gray-500'>پلن</p>
          <p className='mt-2 text-sm text-gray-800 dark:text-white/90'>{status?.account?.plan || '—'}</p>
          <p className='text-theme-xs mt-1 text-gray-500'>انقضا: {status?.account?.planExpiresAt || '—'}</p>
          <p className='text-theme-xs mt-1 text-gray-500'>
            دوره {status?.account?.active ? 'فعال' : 'غیرفعال'} · تمدید خودکار{' '}
            {status?.account?.planAutoRenew ? 'روشن' : 'خاموش'}
          </p>
        </div>
        <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <p className='text-theme-xs text-gray-500'>سفارش زنده / وب‌هوک</p>
          <div className='mt-2 flex flex-wrap gap-2'>
            <Badge color={status?.liveOrderEnabled ? 'warning' : 'light'}>
              {status?.liveOrderEnabled ? 'لایو باز است' : 'قفل (dry-run)'}
            </Badge>
            <Badge color={status?.webhookConfigured ? 'success' : 'light'}>
              {status?.webhookConfigured ? 'secret وب‌هوک هست' : 'secret وب‌هوک نیست'}
            </Badge>
          </div>
        </div>
      </div>

      <div className='flex flex-wrap gap-2'>
        <Button
          type='button'
          variant='outline'
          loading={proxy.isBusy('GET', 'me')}
          disabled={proxy.busy || !status?.configured}
          onClick={() => void proxy.invoke('GET', 'me')}
        >
          خواندن me
        </Button>
        <Button
          type='button'
          variant='outline'
          loading={proxy.isBusy('GET', 'balance')}
          disabled={proxy.busy || !status?.configured}
          onClick={() => void proxy.invoke('GET', 'balance')}
        >
          خواندن balance
        </Button>
        <Button
          type='button'
          variant='outline'
          loading={proxy.isBusy('GET', 'subscription')}
          disabled={proxy.busy || !status?.configured}
          onClick={() => void proxy.invoke('GET', 'subscription')}
        >
          خواندن اشتراک
        </Button>
        <Link
          href='/catalog/fazercards/settings'
          className='inline-flex items-center justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700'
        >
          تنظیمات کلید
        </Link>
      </div>

      {syncRow ? (
        <p className='text-theme-sm text-gray-500'>
          آخرین همگام فروشگاه: {syncRow.status} · {formatFaDate(syncRow.finishedAt)} · {toFaDigits(syncRow.products ?? 0)}{' '}
          محصول / {toFaDigits(syncRow.skus ?? 0)} بسته
        </p>
      ) : (
        <p className='text-theme-sm text-gray-500'>همگام کاتالوگ هنوز اجرا نشده. از نگاشت SKU بزن؛ تکه‌تکه است و به ۶۰ ثانیه PHP نمی‌خورد.</p>
      )}

      <div className='grid gap-2 sm:grid-cols-2 lg:grid-cols-3'>
        {FAZER_CARDS_NAV.filter((item) => item.key !== 'overview').map((item) => (
          <Link
            key={item.key}
            href={item.href}
            className='rounded-xl border border-gray-100 p-4 text-sm text-gray-800 hover:border-brand-500 dark:border-white/5 dark:text-white/90'
          >
            {item.label}
          </Link>
        ))}
      </div>

      <FazerCardsResult last={proxy.last} />
      <FazerCardsConfirmModal
        isOpen={proxy.confirmModal.isOpen}
        title={proxy.confirmCopy.title}
        message={proxy.confirmCopy.message}
        busy={proxy.busy}
        onClose={proxy.confirmModal.closeModal}
        onConfirm={() => void proxy.confirmPending()}
      />
    </div>
  );
}

'use client';

import { Alert, Badge } from '@/components/ui';
import type { SupplierStatus } from '@/types/admin';

function keyErrorTitle(code?: string) {
  if (code === 'IP_NOT_ALLOWED') return 'IP در allowlist نیست';
  if (code === 'INVALID_API_KEY' || code === 'UNAUTHORIZED') return 'کلید تأمین نامعتبر است';
  if (code === 'SUPPLIER_NOT_CONFIGURED') return 'کلید تنظیم نشده';
  return 'حساب خوانده نشد';
}

function walletLooksEmpty(status?: SupplierStatus) {
  if (!status) return false;
  if (status.accountErrorCode === 'INSUFFICIENT_BALANCE') return true;
  const wallet = status.account?.wallet;
  if (wallet == null || wallet === '') return false;
  const n = Number.parseFloat(wallet);
  return Number.isFinite(n) && n <= 0;
}

type SupplierScreenProps = {
  status?: SupplierStatus;
  error?: string;
};

export function SupplierScreen({ status, error }: SupplierScreenProps) {
  const wallet = status?.account?.wallet;

  return (
    <div className='space-y-6'>
      {error ? <Alert variant='error' title='خطا' message={error} /> : null}

      <Alert
        variant='info'
        title='کاتالوگ فروشگاه'
        message='همگام Shop2 حذف شده است. محصولات فروشگاه از FazerCards و نگاشت SKU می‌آیند.'
      />

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <p className='text-theme-xs text-gray-500'>کلید S2T (پشتیبان سفارش)</p>
          <div className='mt-2'>
            <Badge color={status?.configured ? 'success' : 'error'}>
              {status?.configured ? 'تنظیم شده روی بک‌اند' : 'کلید نیست'}
            </Badge>
          </div>
          <p className='text-theme-xs mt-2 text-gray-500'>{status?.host || '—'}</p>
        </div>
        <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <p className='text-theme-xs text-gray-500'>موجودی کیف Shop2</p>
          <p className='mt-2 text-lg font-semibold text-gray-800 dark:text-white/90'>
            {wallet ? `${wallet} USD` : '—'}
          </p>
          <p className='text-theme-xs mt-1 text-gray-500'>
            {status?.account?.email ? `ایمیل ${status.account.email}` : 'حساب خوانده نشد'}
          </p>
        </div>
        <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <p className='text-theme-xs text-gray-500'>سفارش زنده Shop2</p>
          <div className='mt-2'>
            <Badge color={status?.liveOrderEnabled ? 'warning' : 'light'}>
              {status?.liveOrderEnabled ? 'فعال — فقط با تأیید' : 'قفل (dry-run)'}
            </Badge>
          </div>
        </div>
      </div>

      {!status?.configured ? (
        <Alert
          variant='warning'
          title='کلید تأمین'
          message='S2T_KEY روی بک‌اند خالی است. مقدار کلید اینجا نشان داده نمی‌شود.'
        />
      ) : null}

      {walletLooksEmpty(status) ? (
        <Alert
          variant='warning'
          title='آلارم موجودی'
          message='کیف پول Shop2 خالی یا ناکافی است. سفارش زنده با INSUFFICIENT_BALANCE رد می‌شود.'
        />
      ) : null}

      {status?.accountError ? (
        <Alert
          variant='warning'
          title={keyErrorTitle(status.accountErrorCode)}
          message={status.accountError}
        />
      ) : null}

      {status?.account && !status.accountError ? (
        <Alert variant='success' title='حساب قابل دسترس' message='کیف پول بدون payload خام تأمین‌کننده خوانده شد.' />
      ) : null}
    </div>
  );
}

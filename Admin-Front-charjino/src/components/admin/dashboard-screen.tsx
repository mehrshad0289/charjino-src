import {
  getDashboardSummary,
  getFazerCardsStatus,
  getPricing,
  getSupplierStatus,
  listGroups,
  listOrders,
  listProducts,
  listUsers
} from '@/actions/admin.actions';
import { Alert } from '@/components/ui';
import { formatFaDate, formatToman, toFaDigits } from '@/lib/fa';
import Link from 'next/link';

export async function AdminDashboardScreen() {
  const [groups, products, users, pricing, supplier, fazercards, summary, orders] = await Promise.all([
    listGroups(),
    listProducts({ perPage: 1, page: 1 }),
    listUsers(),
    getPricing(),
    getSupplierStatus(),
    getFazerCardsStatus(),
    getDashboardSummary(),
    listOrders({ page: 1 })
  ]);

  const errors = [groups, products, users, pricing, supplier, fazercards, summary, orders]
    .filter((result) => !result.ok)
    .map((result) => ('message' in result ? result.message : ''));

  const groupCount = groups.ok ? groups.items.length : 0;
  const productCount = products.ok ? products.meta.total : 0;
  const userCount = users.ok ? users.items.length : 0;
  const liveOrders = orders.ok ? orders.items : [];

  return (
    <div className='space-y-6'>
      {errors.length > 0 ? (
        <Alert variant='error' title='برخی داده‌ها خوانده نشد' message={errors.join(' · ')} />
      ) : null}

      {summary.ok && summary.data.supplierWalletLow ? (
        <Alert
          variant='warning'
          title='آلارم موجودی تأمین'
          message='کیف پول تأمین‌کننده برای سفارش زنده کافی نیست. تا شارژ مجدد، تحویل زنده را باز نکن.'
        />
      ) : null}

      <div className='grid gap-4 sm:grid-cols-2 xl:grid-cols-4'>
        <DashboardStat href='/catalog/groups' label='دسته‌ها' value={toFaDigits(groupCount)} />
        <DashboardStat href='/catalog/products' label='محصولات' value={toFaDigits(productCount)} />
        <DashboardStat
          href='/orders'
          label='سفارش امروز'
          value={toFaDigits(summary.ok ? summary.data.ordersToday : 0)}
        />
        <DashboardStat href='/users' label='کاربران' value={toFaDigits(userCount)} />
      </div>
      <div className='grid gap-4 sm:grid-cols-2 xl:grid-cols-4'>
        <DashboardStat href='/orders?status=needs_review' label='نیاز به بررسی' value={toFaDigits(summary.ok ? summary.data.needsReview ?? 0 : 0)} />
        <DashboardStat href='/orders?status=refund_pending' label='بازگشت در انتظار' value={toFaDigits(summary.ok ? summary.data.refundPending ?? 0 : 0)} />
        <DashboardStat href='/orders?unknown=1' label='unknown' value={toFaDigits(summary.ok ? summary.data.unknownAttempts ?? 0 : 0)} />
        <DashboardStat href='/orders?hold=1' label='hold' value={toFaDigits(summary.ok ? summary.data.hold ?? 0 : 0)} />
      </div>

      <div className='grid gap-4 lg:grid-cols-2'>
        <div className='rounded-xl border border-gray-200 bg-white p-5 dark:border-white/5 dark:bg-white/3'>
          <h2 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>تأمین‌کننده</h2>
          <p className='mt-2 text-sm text-gray-500'>
            کلید:{' '}
            {supplier.ok && supplier.data.configured ? 'تنظیم شده' : 'نیست'} · سفارش زنده:{' '}
            {supplier.ok && supplier.data.liveOrderEnabled ? 'فعال' : 'قفل'}
          </p>
          {supplier.ok && supplier.data.account ? (
            <p className='mt-2 text-sm text-gray-500'>کیف پول: {supplier.data.account.wallet} USD</p>
          ) : null}
          {supplier.ok && supplier.data.accountError ? (
            <p className='mt-2 text-sm text-error-500'>{supplier.data.accountError}</p>
          ) : null}
          {summary.ok ? (
            <p className='mt-2 text-sm text-gray-500'>
              در صف تحویل: {toFaDigits(summary.data.pendingFulfillment)} · ناموفق امروز:{' '}
              {toFaDigits(summary.data.failedToday)} · unknown: {toFaDigits(summary.data.unknownAttempts ?? 0)} · hold:{' '}
              {toFaDigits(summary.data.hold ?? 0)} · بازگشت: {toFaDigits(summary.data.refundPending ?? 0)}
            </p>
          ) : null}
          <p className='mt-4 text-sm text-gray-500'>
            FazerCards:{' '}
            {fazercards.ok && fazercards.data.configured
              ? `تنظیم شده ****${fazercards.data.keyLast4 || ''}`
              : 'کلید نیست'}
            {fazercards.ok && fazercards.data.account ? ` · والت ${fazercards.data.account.wallet} USD` : ''}
          </p>
          <Link href='/catalog/fazercards' className='text-theme-sm mt-2 inline-flex font-medium text-brand-500'>
            کنسول FazerCards
          </Link>
        </div>
        <div className='rounded-xl border border-gray-200 bg-white p-5 dark:border-white/5 dark:bg-white/3'>
          <h2 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>نرخ و سود</h2>
          {pricing.ok ? (
            <p className='mt-2 text-sm text-gray-500'>
              حالت: {pricing.data.fxMode === 'auto' ? 'خودکار' : 'دستی'} · دلار:{' '}
              {formatToman(pricing.data.usdTomanRate)} · سود: {toFaDigits(pricing.data.marginPercent)}٪ ·{' '}
              {formatFaDate(pricing.data.fxUpdatedAt)}
            </p>
          ) : (
            <p className='mt-2 text-sm text-gray-500'>نرخ خوانده نشد.</p>
          )}
          {summary.ok ? (
            <p className='mt-2 text-sm text-gray-500'>
              فروش امروز: {formatToman(summary.data.revenueTodayToman)}
            </p>
          ) : null}
          <Link href='/pricing' className='text-theme-sm mt-3 inline-flex font-medium text-brand-500'>
            تنظیم نرخ دلار
          </Link>
        </div>
      </div>

      <div className='rounded-xl border border-gray-200 bg-white p-5 dark:border-white/5 dark:bg-white/3'>
        <h2 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>سفارش‌های اخیر</h2>
        {!orders.ok ? (
          <Alert variant='warning' title='سفارش‌ها' message={orders.message} />
        ) : liveOrders.length === 0 ? (
          <Alert
            variant='info'
            title='سفارشی نیست'
            message='هنوز سفارشی از API فروشگاه نیامده است. دادهٔ نمونه TailAdmin اینجا نمایش داده نمی‌شود.'
            showLink
            linkHref='/orders'
            linkText='صفحهٔ سفارش‌ها'
          />
        ) : (
          <ul className='mt-3 space-y-2'>
            {liveOrders.slice(0, 5).map((order) => (
              <li key={order.id}>
                <Link href={`/orders/${order.number}`} className='text-theme-sm font-medium text-brand-500'>
                  {order.number}
                </Link>
                <span className='text-theme-xs ms-2 text-gray-500'>
                  {order.status} · {formatToman(order.amountToman)}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

function DashboardStat({
  href,
  label,
  value
}: {
  href?: string;
  label: string;
  value: string;
}) {
  const body = (
    <>
      <p className='text-theme-xs text-gray-500'>{label}</p>
      <p className='mt-2 text-lg font-semibold text-gray-800 dark:text-white/90'>{value}</p>
    </>
  );

  if (!href) {
    return (
      <div className='rounded-xl border border-gray-200 bg-white p-5 dark:border-white/5 dark:bg-white/3'>
        {body}
      </div>
    );
  }

  return (
    <Link
      href={href}
      className='rounded-xl border border-gray-200 bg-white p-5 transition-colors hover:border-brand-500 dark:border-white/5 dark:bg-white/3'
    >
      {body}
    </Link>
  );
}

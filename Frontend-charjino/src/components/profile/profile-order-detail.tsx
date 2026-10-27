import { DryRunNotice } from '@/components/checkout/dry-run-notice';
import { customerErrorMessage } from '@/lib/customer-errors';
import { formatFaDateTime } from '@/lib/fa';
import { isPayableStatus, orderStatusLabel } from '@/lib/order-labels';
import { formatTomanNumber } from '@/lib/price';
import { ROUTES } from '@/lib/routes';
import type { CustomerOrder } from '@/types/catalog';
import { OrderPrintButton, ProfilePayButton } from './order-actions';
import { OrderLive } from './order-live';
import { OrderTimeline } from './order-timeline';
import { ProfileBack } from './profile-chrome';
import { ProfileSubnav } from './profile-subnav';
import styles from './profile.module.css';

type ProfileOrderDetailProps = {
  order: CustomerOrder;
  fromPsp?: boolean;
  errorCode?: string | null;
};

export function ProfileOrderDetail({ order, fromPsp = false, errorCode = null }: ProfileOrderDetailProps) {
  const returnError = errorCode ? customerErrorMessage(errorCode) : null;

  return (
    <>
      <ProfileSubnav />
      <ProfileBack href={ROUTES.profileOrders} label="بازگشت به فهرست سفارش‌ها" />
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold text-text">جزئیات سفارش</h1>
        <OrderPrintButton />
      </div>
      {fromPsp && !returnError ? (
        <p className={styles.panel}>بازگشت از درگاه ثبت شد. وضعیت زیر از API است.</p>
      ) : null}
      {returnError ? <p className="text-sm text-error-200">{returnError}</p> : null}
      <DryRunNotice
        liveOrderEnabled={!order.dryRun}
        productTitle={order.productTitle}
        pagePath={ROUTES.invoice(order.number)}
      />

      <div className={styles.panel}>
        <dl className="grid gap-4 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-text/45">شماره فاکتور</dt>
            <dd className="mt-1 font-semibold text-text">{order.number}</dd>
          </div>
          <div>
            <dt className="text-text/45">وضعیت</dt>
            <dd className="mt-1 text-primary-600">{orderStatusLabel(order.status, order.statusLabelFa)}</dd>
          </div>
          {order.discountToman && order.discountToman > 0 ? (
            <>
              <div>
                <dt className="text-text/45">مبلغ قبل از تخفیف</dt>
                <dd className="mt-1 text-text">
                  {formatTomanNumber(order.subtotalToman ?? order.amountToman)} تومان
                </dd>
              </div>
              <div>
                <dt className="text-text/45">تخفیف{order.couponCode ? ` (${order.couponCode})` : ''}</dt>
                <dd className="mt-1 text-primary-600">{formatTomanNumber(order.discountToman)} تومان</dd>
              </div>
              <div>
                <dt className="text-text/45">قابل پرداخت</dt>
                <dd className="mt-1 text-text">{formatTomanNumber(order.amountToman)} تومان</dd>
              </div>
            </>
          ) : (
            <div>
              <dt className="text-text/45">مبلغ</dt>
              <dd className="mt-1 text-text">{formatTomanNumber(order.amountToman)} تومان</dd>
            </div>
          )}
          <div>
            <dt className="text-text/45">تاریخ</dt>
            <dd className="mt-1 text-text">{formatFaDateTime(order.createdAt)}</dd>
          </div>
          <div>
            <dt className="text-text/45">محصول</dt>
            <dd className="mt-1 text-text">{order.productTitle ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-text/45">بسته</dt>
            <dd className="mt-1 text-text">{order.skuName ?? '—'}</dd>
          </div>
          {order.versionLabel ? (
            <div>
              <dt className="text-text/45">نسخه</dt>
              <dd className="mt-1 text-text">{order.versionLabel}</dd>
            </div>
          ) : null}
          {order.playerId ? (
            <div>
              <dt className="text-text/45">شناسه</dt>
              <dd className="mt-1 text-text" dir="ltr">
                {order.playerId}
              </dd>
            </div>
          ) : null}
          {order.quantity > 1 ? (
            <div>
              <dt className="text-text/45">تعداد</dt>
              <dd className="mt-1 text-text">{order.quantity}</dd>
            </div>
          ) : null}
          {order.paymentProvider === 'zibal' || order.paymentProvider === 'zarinpal' ? (
            <>
              <div>
                <dt className="text-text/45">درگاه</dt>
                <dd className="mt-1 text-text">{order.paymentProvider === 'zarinpal' ? 'زرین‌پال' : 'زیبال'}</dd>
              </div>
              <div>
                <dt className="text-text/45">کد پیگیری</dt>
                <dd className="mt-1 text-text" dir="ltr">
                  {order.paymentRef || '—'}
                </dd>
              </div>
            </>
          ) : null}
        </dl>

        {order.customerMessage ? <p className="mt-5 text-sm text-error-200">{order.customerMessage}</p> : null}

        {isPayableStatus(order.status) ? (
          <div className="mt-6">
            <ProfilePayButton number={order.number} amountToman={order.amountToman} />
          </div>
        ) : null}
      </div>

      <OrderTimeline order={order} />
      <OrderLive order={order} />
    </>
  );
}

import Link from 'next/link';
import { formatFaDateTime } from '@/lib/fa';
import { orderStatusLabel } from '@/lib/order-labels';
import { formatTomanNumber } from '@/lib/price';
import { ROUTES } from '@/lib/routes';
import type { CustomerOrder } from '@/types/catalog';
import { ProfileEmpty, ProfileHeading, ProfilePager } from './profile-chrome';
import { ProfileSubnav } from './profile-subnav';
import styles from './profile.module.css';

type ProfileOrdersListProps = {
  orders: CustomerOrder[];
  page: number;
  totalPages: number;
  listError?: string | null;
};

export function ProfileOrdersList({
  orders,
  page,
  totalPages,
  listError = null,
}: ProfileOrdersListProps) {
  return (
    <>
      <ProfileSubnav />
      <ProfileHeading title="سفارش‌ها" />
      {listError ? (
        <p className="text-sm text-error-200">{listError}</p>
      ) : orders.length === 0 ? (
        <ProfileEmpty>هنوز سفارشی برای این حساب نیست.</ProfileEmpty>
      ) : (
        <ul className="flex flex-col gap-3">
          {orders.map((order) => (
            <li key={order.id}>
              <Link href={ROUTES.profileOrder(order.number)} className={styles.row}>
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="font-semibold text-text">{order.number}</span>
                  <span className="text-sm text-primary-600">{orderStatusLabel(order.status, order.statusLabelFa)}</span>
                </div>
                <p className="text-sm text-text/60">
                  {order.productTitle ?? 'محصول'}
                  {order.skuName ? ` · ${order.skuName}` : ''}
                </p>
                <p className="text-xs text-text/45">
                  {formatTomanNumber(order.amountToman)} تومان · {formatFaDateTime(order.createdAt)}
                </p>
              </Link>
            </li>
          ))}
        </ul>
      )}
      <ProfilePager
        page={page}
        totalPages={totalPages}
        hrefFor={(next) => (next > 1 ? `${ROUTES.profileOrders}?page=${next}` : ROUTES.profileOrders)}
      />
    </>
  );
}

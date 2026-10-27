import { formatFaDateTime } from '@/lib/fa';
import { timelineLabel } from '@/lib/order-labels';
import type { CustomerOrder } from '@/types/catalog';
import styles from './profile.module.css';

export function OrderTimeline({ order }: { order: CustomerOrder }) {
  const events = order.timeline ?? [];
  return (
    <div className={styles.panel}>
      <h2 className="mb-4 text-sm font-semibold text-text">مرحله‌ها</h2>
      {order.requirementsMasked && order.requirementsMasked.length > 0 ? (
        <ul className="mb-4 space-y-1 text-sm text-text/80">
          {order.requirementsMasked.map((row) => (
            <li key={row.key}>
              {row.label}: <span dir="ltr">{row.value}</span>
            </li>
          ))}
        </ul>
      ) : null}
      {events.length > 0 ? (
        <ol className="space-y-3 text-sm">
          {events.map((event, index) => (
            <li key={`${event.type}-${event.at ?? index}`} className="flex justify-between gap-3">
              <span className="text-text/80">{timelineLabel(event.type ?? '')}</span>
              <span className="text-xs text-text/45">{formatFaDateTime(event.at)}</span>
            </li>
          ))}
        </ol>
      ) : (
        <p className="text-sm text-text/50">هنوز رویدادی ثبت نشده است.</p>
      )}
    </div>
  );
}

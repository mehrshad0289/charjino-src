import { SUPPORT_COPY } from '@/data/support';
import { toFaDigits } from '@/lib/fa';
import { BoxIconLine, ChatIcon, GroupIcon } from '@/components/ui/icon';

type SupportMetricsProps = {
  total: number;
  open: number;
  answered: number;
  closed: number;
};

const METRICS = [
  { key: 'total', label: SUPPORT_COPY.total, icon: ChatIcon },
  { key: 'open', label: SUPPORT_COPY.open, icon: BoxIconLine },
  { key: 'closed', label: SUPPORT_COPY.closed, icon: GroupIcon },
] as const;

export function SupportMetrics({ total, open, answered, closed }: SupportMetricsProps) {
  const values = { total, open, answered, closed };

  return (
    <div className='grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6'>
      {METRICS.map((item) => {
        const Icon = item.icon;
        const value = values[item.key];
        return (
          <div
            key={item.key}
            className='rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3'
          >
            <div className='flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800'>
              <Icon className='size-6 text-gray-800 dark:text-white/90' />
            </div>
            <div className='mt-5'>
              <span className='text-sm text-gray-500 dark:text-gray-400'>{item.label}</span>
              <h4 className='mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90'>{toFaDigits(value)}</h4>
              {item.key === 'open' ? (
                <p className='mt-1 text-theme-xs text-gray-500 dark:text-gray-400'>
                  {SUPPORT_COPY.answered}: {toFaDigits(answered)}
                </p>
              ) : null}
            </div>
          </div>
        );
      })}
    </div>
  );
}

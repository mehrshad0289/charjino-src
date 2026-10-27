'use client';

import { SUPPORT_COPY, SUPPORT_PAGE_SIZE } from '@/data/support';
import { presentSupportTicket } from '@/lib/tickets';
import { Pagination } from '@/components/ui/pagination';
import { Tabs } from '@/components/ui/tabs';
import { Alert } from '@/components/ui';
import type { AdminTicket } from '@/types/tickets';
import type { SupportTabId } from '@/types/support';
import { useRouter } from 'next/navigation';
import { useMemo } from 'react';

import { SupportMetrics } from './support-metrics';
import { SupportTicketsTable } from './support-tickets-table';

type SupportListScreenProps = {
  items: AdminTicket[];
  error?: string;
  tab: SupportTabId;
  page: number;
  totalPages: number;
  total: number;
  metrics: { total: number; open: number; answered: number; closed: number };
};

export function SupportListScreen({
  items,
  error,
  tab,
  page,
  totalPages,
  total,
  metrics,
}: SupportListScreenProps) {
  const router = useRouter();
  const tickets = useMemo(() => items.map(presentSupportTicket), [items]);
  const currentPage = Math.min(page, totalPages);
  const from = total === 0 ? 0 : (currentPage - 1) * SUPPORT_PAGE_SIZE + 1;
  const to = Math.min(currentPage * SUPPORT_PAGE_SIZE, total);

  function navigate(nextTab: SupportTabId, nextPage = 1) {
    const params = new URLSearchParams();
    if (nextTab !== 'all') params.set('status', nextTab);
    if (nextPage > 1) params.set('page', String(nextPage));
    const suffix = params.toString();
    router.push(suffix ? `/support?${suffix}` : '/support');
  }

  return (
    <div className='space-y-6'>
      {error ? <Alert variant='error' title='خوانده نشد' message={error} /> : null}
      <SupportMetrics {...metrics} />
      <div className='overflow-hidden rounded-2xl border border-gray-200 bg-white px-4 pt-4 pb-3 sm:px-6 dark:border-gray-800 dark:bg-white/3'>
        <div className='mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
          <div>
            <h3 className='text-lg font-semibold text-gray-800 dark:text-white/90'>{SUPPORT_COPY.ticketsCard}</h3>
            <p className='mt-1 text-theme-sm text-gray-500 dark:text-gray-400'>{SUPPORT_COPY.ticketsCardDesc}</p>
          </div>
          <div className='w-fit'>
            <Tabs items={[...SUPPORT_COPY.tabs]} value={tab} onChange={(value) => navigate(value as SupportTabId, 1)} />
          </div>
        </div>
        <SupportTicketsTable tickets={tickets} />
        <div className='mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800'>
          <p className='text-theme-sm text-gray-500 dark:text-gray-400'>
            {SUPPORT_COPY.showing(from, to, total)}
          </p>
          <div className='flex flex-col gap-2 sm:items-end'>
            <p className='text-theme-sm text-gray-500 dark:text-gray-400'>
              {SUPPORT_COPY.pageOf(currentPage, totalPages)}
            </p>
            <Pagination currentPage={currentPage} totalPages={totalPages} onPageChange={(next) => navigate(tab, next)} />
          </div>
        </div>
      </div>
    </div>
  );
}

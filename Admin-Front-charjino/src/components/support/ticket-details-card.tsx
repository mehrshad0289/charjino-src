import { SUPPORT_COPY, SUPPORT_STATUS_COLOR } from '@/data/support';
import { Badge } from '@/components/ui/badge';
import type { SupportTicket } from '@/types/support';

type TicketDetailsCardProps = {
  ticket: SupportTicket;
};

export function TicketDetailsCard({ ticket }: TicketDetailsCardProps) {
  const rows: { label: string; value: string }[] = [
    { label: SUPPORT_COPY.customer, value: ticket.requester.name },
    { label: SUPPORT_COPY.phone, value: ticket.requester.phone || '—' },
    { label: SUPPORT_COPY.ticketId, value: SUPPORT_COPY.ticketNo(ticket.number) },
    { label: SUPPORT_COPY.category, value: ticket.category },
    { label: SUPPORT_COPY.created, value: ticket.createdLabel },
  ];

  if (ticket.orderNumber) {
    rows.splice(4, 0, { label: SUPPORT_COPY.orderNumber, value: ticket.orderNumber });
  }

  return (
    <div className='rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/3'>
      <h3 className='mb-5 text-lg font-semibold text-gray-800 dark:text-white/90'>{SUPPORT_COPY.details}</h3>
      <div className='space-y-4'>
        {rows.map((row) => (
          <div key={row.label} className='flex items-start justify-between gap-4'>
            <span className='text-theme-sm text-gray-500 dark:text-gray-400'>{row.label}</span>
            <span
              className={`max-w-[60%] text-end text-theme-sm font-medium text-gray-800 dark:text-white/90 ${
                row.label === SUPPORT_COPY.phone ? 'break-all' : ''
              }`}
              dir={row.label === SUPPORT_COPY.phone ? 'ltr' : undefined}
            >
              {row.value}
            </span>
          </div>
        ))}
        <div className='flex items-center justify-between gap-4'>
          <span className='text-theme-sm text-gray-500 dark:text-gray-400'>{SUPPORT_COPY.status}</span>
          <Badge size='sm' color={SUPPORT_STATUS_COLOR[ticket.status]}>
            {SUPPORT_COPY.statuses[ticket.status]}
          </Badge>
        </div>
      </div>
    </div>
  );
}

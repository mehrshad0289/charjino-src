'use client';

import { replyAdminTicket, setAdminTicketStatus } from '@/actions/tickets.actions';
import { presentSupportTicket, presentTicketMessages } from '@/lib/tickets';
import type { AdminTicket } from '@/types/tickets';
import type { TicketStatus } from '@/types/support';
import { Alert } from '@/components/ui';
import { useRouter } from 'next/navigation';
import { useMemo, useState, useTransition } from 'react';

import { TicketDetailsCard } from './ticket-details-card';
import { TicketThread } from './ticket-thread';

type SupportReplyScreenProps = {
  ticket: AdminTicket;
};

export function SupportReplyScreen({ ticket: initialTicket }: SupportReplyScreenProps) {
  const router = useRouter();
  const [ticketRow, setTicketRow] = useState(initialTicket);
  const [status, setStatus] = useState<TicketStatus>((initialTicket.status as TicketStatus) ?? 'open');
  const [error, setError] = useState<string>();
  const [pending, startTransition] = useTransition();

  const ticket = useMemo(() => presentSupportTicket(ticketRow), [ticketRow]);
  const messages = useMemo(() => presentTicketMessages(ticketRow), [ticketRow]);

  function applyTicket(next: AdminTicket) {
    setTicketRow(next);
    setStatus((next.status as TicketStatus) ?? 'open');
    router.refresh();
  }

  function handleStatusChange(next: TicketStatus) {
    if (next === status) return;
    setStatus(next);
    startTransition(async () => {
      setError(undefined);
      const result = await setAdminTicketStatus(ticketRow.id, next);
      if (!result.ok) {
        setError(result.message);
        setStatus((ticketRow.status as TicketStatus) ?? 'open');
        return;
      }
      applyTicket(result.ticket);
    });
  }

  function handleReply(body: string) {
    startTransition(async () => {
      setError(undefined);
      const result = await replyAdminTicket(ticketRow.id, body);
      if (!result.ok) {
        setError(result.message);
        return;
      }
      applyTicket(result.ticket);
    });
  }

  return (
    <div className='space-y-4'>
      {error ? <Alert variant='error' title='خطا' message={error} /> : null}
      <div className='grid grid-cols-1 items-start gap-6 xl:grid-cols-3'>
        <div className='xl:col-span-2'>
          <TicketThread
            ticket={ticket}
            messages={messages}
            status={status}
            busy={pending}
            onStatusChange={handleStatusChange}
            onReply={handleReply}
          />
        </div>
        <TicketDetailsCard ticket={ticket} />
      </div>
    </div>
  );
}

'use client';

import { SUPPORT_COPY } from '@/data/support';
import type { SupportTicket, TicketMessage, TicketStatus } from '@/types/support';
import Image from 'next/image';

import { TicketReplyForm } from './ticket-reply-form';

type TicketThreadProps = {
  ticket: SupportTicket;
  messages: TicketMessage[];
  status: TicketStatus;
  replyDisabled?: boolean;
  busy?: boolean;
  onStatusChange: (status: TicketStatus) => void;
  onReply: (message: string) => void;
};

export function TicketThread({
  ticket,
  messages,
  status,
  replyDisabled,
  busy,
  onStatusChange,
  onReply,
}: TicketThreadProps) {
  const formDisabled = replyDisabled || status === 'closed';

  return (
    <div className='rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/3'>
      <div className='mb-6 border-b border-gray-100 pb-5 dark:border-gray-800'>
        <h3 className='text-lg font-semibold text-gray-800 dark:text-white/90'>
          {SUPPORT_COPY.ticketHeading} {SUPPORT_COPY.ticketNo(ticket.number)} — {ticket.subject}
        </h3>
        <p className='mt-1 text-theme-sm text-gray-500 dark:text-gray-400'>{ticket.headerTime ?? ticket.createdLabel}</p>
      </div>

      <div className='custom-scrollbar max-h-125 space-y-6 overflow-y-auto pe-1'>
        {messages.length === 0 ? (
          <p className='text-theme-sm text-gray-500 dark:text-gray-400'>پیامی ثبت نشده است.</p>
        ) : (
          messages.map((message) => (
            <article key={message.id} className='flex gap-3'>
              <div className='h-10 w-10 shrink-0 overflow-hidden rounded-full'>
                <Image width={40} height={40} src={message.avatar} alt={message.authorName} />
              </div>
              <div className='min-w-0 flex-1'>
                <div className='mb-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between'>
                  <div>
                    <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{message.authorName}</p>
                    {message.authorEmail ? (
                      <span className='text-theme-xs text-gray-500 dark:text-gray-400' dir='ltr'>{message.authorEmail}</span>
                    ) : null}
                  </div>
                  <span className='text-theme-xs text-gray-400 dark:text-gray-500'>{message.timeLabel}</span>
                </div>
                <p className='whitespace-pre-line text-theme-sm leading-6 text-gray-600 dark:text-gray-400'>{message.body}</p>
              </div>
            </article>
          ))
        )}
      </div>

      <TicketReplyForm
        status={status}
        disabled={formDisabled}
        busy={busy}
        onStatusChange={onStatusChange}
        onSubmit={onReply}
      />
    </div>
  );
}

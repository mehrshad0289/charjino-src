'use client';

import { SUPPORT_AGENT, SUPPORT_COPY } from '@/data/support';
import { Button } from '@/components/ui/button';
import { textareaClass } from '@/components/ui/field/classNames';
import { Radio } from '@/components/ui/radio';
import type { TicketStatus } from '@/types/support';
import Image from 'next/image';

type TicketReplyFormProps = {
  status: TicketStatus;
  disabled?: boolean;
  busy?: boolean;
  onStatusChange: (status: TicketStatus) => void;
  onSubmit: (message: string) => void;
};

const STATUS_OPTIONS: TicketStatus[] = ['open', 'answered', 'closed'];

export function TicketReplyForm({ status, disabled, busy, onStatusChange, onSubmit }: TicketReplyFormProps) {
  if (disabled) {
    return (
      <p className='mt-6 border-t border-gray-100 pt-6 text-theme-sm text-gray-500 dark:border-gray-800 dark:text-gray-400'>
        {SUPPORT_COPY.closedHint}
      </p>
    );
  }

  return (
    <form
      className='mt-6 border-t border-gray-100 pt-6 dark:border-gray-800'
      onSubmit={(event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = new FormData(form);
        const message = String(data.get('reply') ?? '').trim();
        if (!message) return;
        onSubmit(message);
        form.reset();
      }}
    >
      <div className='mb-4 flex items-center gap-3'>
        <div className='h-10 w-10 overflow-hidden rounded-full'>
          <Image width={40} height={40} src={SUPPORT_AGENT.avatar} alt={SUPPORT_AGENT.name} />
        </div>
        <div>
          <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{SUPPORT_AGENT.name}</p>
          <span className='text-theme-xs text-gray-500 dark:text-gray-400'>{SUPPORT_COPY.fromTeam}</span>
        </div>
      </div>
      <div className='rounded-2xl border border-gray-200 p-4 sm:p-5 dark:border-gray-800'>
        <textarea
          name='reply'
          rows={5}
          placeholder={SUPPORT_COPY.replyPlaceholder}
          disabled={busy}
          className={textareaClass('default', 'min-h-32 border-0 p-0 shadow-none focus:ring-0 dark:bg-transparent')}
        />
        <div className='mt-4 flex justify-end'>
          <Button type='submit' size='sm' disabled={busy}>
            {SUPPORT_COPY.reply}
          </Button>
        </div>
      </div>
      <div className='mt-5 flex flex-wrap items-center gap-5'>
        <span className='text-theme-sm font-medium text-gray-700 dark:text-gray-400'>{SUPPORT_COPY.status}:</span>
        {STATUS_OPTIONS.map((item) => (
          <Radio
            key={item}
            id={`ticket-status-${item}`}
            name='ticket-status'
            value={item}
            checked={status === item}
            disabled={busy}
            label={SUPPORT_COPY.statuses[item]}
            onChange={(value) => onStatusChange(value as TicketStatus)}
          />
        ))}
      </div>
    </form>
  );
}

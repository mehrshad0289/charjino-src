'use client';

import { SUPPORT_COPY, SUPPORT_STATUS_COLOR } from '@/data/support';
import { Badge } from '@/components/ui/badge';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { MoreDotIcon } from '@/components/ui/icon';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import type { SupportTicket } from '@/types/support';
import Image from 'next/image';
import { useState } from 'react';

type SupportTicketsTableProps = {
  tickets: SupportTicket[];
};

export function SupportTicketsTable({ tickets }: SupportTicketsTableProps) {
  const [openId, setOpenId] = useState<string | null>(null);

  if (tickets.length === 0) {
    return <p className='py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400'>تیکتی برای نمایش نیست.</p>;
  }

  return (
    <div className='max-w-full overflow-x-auto'>
      <Table>
        <TableHeader className='border-y border-gray-100 dark:border-gray-800'>
          <TableRow>
            <TableCell isHeader className='px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
              {SUPPORT_COPY.ticketId}
            </TableCell>
            <TableCell isHeader className='px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
              {SUPPORT_COPY.requestedBy}
            </TableCell>
            <TableCell isHeader className='px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
              {SUPPORT_COPY.subject}
            </TableCell>
            <TableCell isHeader className='px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
              {SUPPORT_COPY.createDate}
            </TableCell>
            <TableCell isHeader className='px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
              {SUPPORT_COPY.status}
            </TableCell>
            <TableCell isHeader className='px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
              {''}
            </TableCell>
          </TableRow>
        </TableHeader>
        <TableBody className='divide-y divide-gray-100 dark:divide-gray-800'>
          {tickets.map((ticket) => (
            <TableRow key={ticket.id}>
              <TableCell className='px-5 py-4 text-theme-sm font-medium text-gray-800 dark:text-white/90'>
                {SUPPORT_COPY.ticketNo(ticket.number)}
              </TableCell>
              <TableCell className='px-5 py-4'>
                <div className='flex items-center gap-3'>
                  <div className='h-10 w-10 overflow-hidden rounded-full'>
                    <Image width={40} height={40} src={ticket.requester.avatar} alt={ticket.requester.name} />
                  </div>
                  <div>
                    <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{ticket.requester.name}</p>
                    <span className='text-theme-xs text-gray-500 dark:text-gray-400' dir='ltr'>{ticket.requester.phone || '—'}</span>
                  </div>
                </div>
              </TableCell>
              <TableCell className='px-5 py-4 text-theme-sm text-gray-500 dark:text-gray-400'>{ticket.subject}</TableCell>
              <TableCell className='px-5 py-4 text-theme-sm text-gray-500 dark:text-gray-400'>{ticket.createdLabel}</TableCell>
              <TableCell className='px-5 py-4'>
                <Badge size='sm' color={SUPPORT_STATUS_COLOR[ticket.status]}>
                  {SUPPORT_COPY.statuses[ticket.status]}
                </Badge>
              </TableCell>
              <TableCell className='px-5 py-4'>
                <div className='relative h-fit'>
                  <button
                    type='button'
                    onClick={() => setOpenId(openId === ticket.id ? null : ticket.id)}
                    className='dropdown-toggle'
                  >
                    <MoreDotIcon className='text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' />
                  </button>
                  <Dropdown isOpen={openId === ticket.id} onClose={() => setOpenId(null)} className='w-40 p-2'>
                    <DropdownItem
                      tag='a'
                      href={`/support/${ticket.id}`}
                      onItemClick={() => setOpenId(null)}
                      className='flex w-full rounded-lg text-start font-normal text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300'
                    >
                      {SUPPORT_COPY.viewMore}
                    </DropdownItem>
                  </Dropdown>
                </div>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

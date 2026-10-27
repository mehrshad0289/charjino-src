'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';

import {
  Alert,
  Badge,
  Button,
  DataTable,
  SelectField,
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
  TextField
} from '@/components/ui';
import { formatFaDate, formatToman, toFaDigits } from '@/lib/fa';
import { ORDER_STATUS_LABEL, orderStatusColor, orderStatusLabel } from '@/lib/order-labels';
import { parseTablePerPage } from '@/lib/table-page';
import type { AdminOrder } from '@/types/admin';

type OrdersScreenProps = {
  items: AdminOrder[];
  error?: string;
  status?: string;
  q?: string;
  page?: number;
  perPage?: number;
  totalPages?: number;
  total?: number;
};

export function OrdersScreen({
  items,
  error,
  status = '',
  q = '',
  page = 1,
  perPage = 10,
  totalPages = 1,
  total
}: OrdersScreenProps) {
  const router = useRouter();
  const [query, setQuery] = useState(q);
  const [filter, setFilter] = useState(status || 'all');

  function apply(nextPage = 1, nextPerPage = perPage) {
    const params = new URLSearchParams();
    if (filter && filter !== 'all') params.set('status', filter);
    if (query.trim()) params.set('q', query.trim());
    if (nextPerPage !== 10) params.set('perPage', String(nextPerPage));
    if (nextPage > 1) params.set('page', String(nextPage));
    const suffix = params.toString();
    router.push(suffix ? `/orders?${suffix}` : '/orders');
  }

  if (error) {
    return <Alert variant='error' title='سفارش‌ها خوانده نشد' message={error} />;
  }

  const filteredEmpty = items.length === 0 && (Boolean(status) || Boolean(q));

  return (
    <div className='space-y-4'>
      <div className='grid gap-3 sm:grid-cols-3'>
        <TextField label='جستجو' value={query} onChange={setQuery} placeholder='شماره سفارش، بازیکن یا موبایل' />
        <SelectField
          label='وضعیت'
          value={filter}
          onChange={setFilter}
          options={[
            { value: 'all', label: 'همه' },
            ...Object.entries(ORDER_STATUS_LABEL).map(([value, label]) => ({ value, label }))
          ]}
        />
        <div className='flex items-end'>
          <Button type='button' onClick={() => apply(1)}>
            فیلتر
          </Button>
        </div>
      </div>
      {items.length === 0 ? (
        <Alert
          variant='info'
          title={filteredEmpty ? 'نتیجه‌ای نیست' : 'سفارشی نیست'}
          message={
            filteredEmpty
              ? 'با این وضعیت یا جستجو سفارشی پیدا نشد.'
              : 'هنوز سفارشی از API فروشگاه نیامده است. دادهٔ نمونه نمایش داده نمی‌شود.'
          }
        />
      ) : (
        <DataTable
          page={page}
          perPage={perPage}
          total={total ?? items.length}
          totalPages={totalPages}
          onPageChange={(next) => apply(next)}
          onPerPageChange={(next) => apply(1, parseTablePerPage(next))}
        >
          <Table>
              <TableHeader className='border-b border-gray-100 dark:border-white/5'>
                <TableRow>
                  <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                    شماره
                  </TableCell>
                  <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                    بازیکن
                  </TableCell>
                  <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                    بسته
                  </TableCell>
                  <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                    مبلغ
                  </TableCell>
                  <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                    وضعیت
                  </TableCell>
                  <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                    تاریخ
                  </TableCell>
                </TableRow>
              </TableHeader>
              <TableBody className='divide-y divide-gray-100 dark:divide-white/5'>
                {items.map((order) => (
                  <TableRow key={order.id}>
                    <TableCell className='px-5 py-4'>
                      <Link href={`/orders/${order.number}`} className='text-theme-sm font-medium text-brand-500'>
                        {order.number}
                      </Link>
                      <span className='text-theme-xs mt-1 block text-gray-500'>
                        {order.userPhoneNumber || '—'}
                      </span>
                    </TableCell>
                    <TableCell className='text-theme-sm px-4 py-3 text-gray-500'>
                      {order.playerName || '—'}
                    </TableCell>
                    <TableCell className='text-theme-sm px-4 py-3 text-gray-500'>
                      {order.productTitle} · {order.skuName}
                      {order.quantity > 1 ? ` × ${toFaDigits(order.quantity)}` : ''}
                    </TableCell>
                    <TableCell className='text-theme-sm px-4 py-3 text-gray-800 dark:text-white/90'>
                      {formatToman(order.amountToman)}
                    </TableCell>
                    <TableCell className='px-4 py-3'>
                      <Badge size='sm' color={orderStatusColor(order.status)}>
                        {orderStatusLabel(order.status)}
                      </Badge>
                    </TableCell>
                    <TableCell className='text-theme-sm px-4 py-3 text-gray-500'>
                      {formatFaDate(order.createdAt)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
        </DataTable>
      )}
    </div>
  );
}

'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { deleteCoupon } from '@/actions/coupons.actions';
import { Alert, Badge, Button, DataTable, SelectField, Table, TableBody, TableCell, TableHeader, TableRow, TextField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { formatFaDate, formatToman, toFaDigits } from '@/lib/fa';
import { parseTablePerPage } from '@/lib/table-page';
import type { AdminCoupon } from '@/types/coupons';

type CouponsScreenProps = {
  items: AdminCoupon[];
  error?: string;
  q?: string;
  status?: string;
  page?: number;
  perPage?: number;
  totalPages?: number;
  total?: number;
};

export function CouponsScreen({
  items,
  error,
  q = '',
  status = '',
  page = 1,
  perPage = 10,
  totalPages = 1,
  total
}: CouponsScreenProps) {
  const router = useRouter();
  const [, setMessage] = useToastMessage();
  const [query, setQuery] = useState(q);
  const [statusFilter, setStatusFilter] = useState(status || 'all');
  const [busy, setBusy] = useState<number | null>(null);

  function apply(nextPage = 1, nextPerPage = perPage) {
    const params = new URLSearchParams();
    if (query.trim()) params.set('q', query.trim());
    if (statusFilter !== 'all') params.set('status', statusFilter);
    if (nextPerPage !== 10) params.set('perPage', String(nextPerPage));
    if (nextPage > 1) params.set('page', String(nextPage));
    const suffix = params.toString();
    router.push(suffix ? `/coupons?${suffix}` : '/coupons');
  }

  async function remove(id: number) {
    if (!window.confirm('این کد تخفیف حذف شود؟')) return;
    setBusy(id);
    const result = await deleteCoupon(id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'کد تخفیف حذف شد.' });
    router.refresh();
  }

  return (
    <div className='space-y-4'>
      {error ? <Alert variant='error' title='خوانده نشد' message={error} /> : null}
      <div className='flex flex-wrap items-end justify-between gap-3'>
        <div className='flex flex-wrap items-end gap-3'>
          <TextField label='جستجو' value={query} onChange={setQuery} />
          <SelectField
            label='وضعیت'
            value={statusFilter}
            onChange={setStatusFilter}
            options={[
              { value: 'all', label: 'همه' },
              { value: 'active', label: 'فعال' },
              { value: 'inactive', label: 'غیرفعال' },
              { value: 'expired', label: 'منقضی' }
            ]}
          />
          <Button type='button' onClick={() => apply(1)}>
            فیلتر
          </Button>
        </div>
        <Link href='/coupons/new'>
          <Button type='button'>کد جدید</Button>
        </Link>
      </div>
      <DataTable
        page={page}
        perPage={parseTablePerPage(perPage)}
        totalPages={totalPages}
        total={total ?? 0}
        onPageChange={(next) => apply(next)}
        onPerPageChange={(next) => apply(1, next)}
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableCell header>کد</TableCell>
              <TableCell header>نوع</TableCell>
              <TableCell header>مقدار</TableCell>
              <TableCell header>محدودیت</TableCell>
              <TableCell header>انقضا</TableCell>
              <TableCell header>استفاده</TableCell>
              <TableCell header>عملیات</TableCell>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7}>کد تخفیفی نیست.</TableCell>
              </TableRow>
            ) : (
              items.map((coupon) => (
                <TableRow key={coupon.id}>
                  <TableCell>
                    <p className='font-semibold'>{coupon.code}</p>
                    <p className='text-xs text-gray-500'>{coupon.name}</p>
                  </TableCell>
                  <TableCell>
                    <Badge color={coupon.type === 'percentage' ? 'info' : 'primary'}>
                      {coupon.type === 'percentage' ? 'درصدی' : 'مبلغی'}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    {coupon.type === 'percentage'
                      ? `${toFaDigits(coupon.percentage ?? 0)}٪ تا ${formatToman(coupon.maxPercentageAmount)}`
                      : formatToman(coupon.amount)}
                  </TableCell>
                  <TableCell>
                    {coupon.type === 'amount' || coupon.minOrderToman > 0
                      ? `حداقل ${formatToman(coupon.minOrderToman)}`
                      : '—'}
                  </TableCell>
                  <TableCell>
                    <Badge color={coupon.isActive ? 'success' : 'light'}>{coupon.isActive ? 'فعال' : 'غیرفعال'}</Badge>
                    <p className='mt-1 text-xs'>{formatFaDate(coupon.expiredAt)}</p>
                  </TableCell>
                  <TableCell>{toFaDigits(coupon.usedCount)}</TableCell>
                  <TableCell>
                    <div className='flex flex-wrap gap-2'>
                      <Link href={`/coupons/${coupon.id}`}>
                        <Button type='button' size='sm' variant='outline'>
                          ویرایش
                        </Button>
                      </Link>
                      <Button
                        type='button'
                        size='sm'
                        variant='outline'
                        loading={busy === coupon.id}
                        onClick={() => remove(coupon.id)}
                      >
                        حذف
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </DataTable>
    </div>
  );
}

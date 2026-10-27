'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';

import { useToastMessage } from '@/hooks/use-toast-message';
import { purgeShop2Catalog, purgeUnlinkedGroups } from '@/actions/admin.actions';
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
  TableRow
} from '@/components/ui';
import { ORIGIN_FILTER_OPTIONS, originBadgeColor, originLabel } from '@/lib/catalog-origin';
import { toFaDigits } from '@/lib/fa';
import { parseTablePerPage } from '@/lib/table-page';
import type { AdminGroup } from '@/types/admin';

type GroupsScreenProps = {
  items: AdminGroup[];
  error?: string;
  source?: string;
  page?: number;
  perPage?: number;
  totalPages?: number;
  total?: number;
};

export function GroupsScreen({
  items,
  error,
  source = '',
  page = 1,
  perPage = 10,
  totalPages = 1,
  total = 0
}: GroupsScreenProps) {
  const router = useRouter();
  const [sourceFilter, setSourceFilter] = useState(source || 'all');
  const [busy, setBusy] = useState<string | null>(null);
  const [, setMessage] = useToastMessage();

  function apply(nextPage = 1, nextPerPage = perPage) {
    const params = new URLSearchParams();
    if (sourceFilter && sourceFilter !== 'all') params.set('source', sourceFilter);
    if (nextPerPage !== 10) params.set('perPage', String(nextPerPage));
    if (nextPage > 1) params.set('page', String(nextPage));
    const suffix = params.toString();
    router.push(suffix ? `/catalog/groups?${suffix}` : '/catalog/groups');
  }

  async function purge() {
    if (!window.confirm('دسته‌های ساخته‌شده که به تأمین‌کننده وصل نیستند حذف شوند؟')) return;
    if (!window.confirm('این عمل برگشت‌پذیر نیست. ادامه می‌دهی؟')) return;
    setBusy('purge');
    const result = await purgeUnlinkedGroups();
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({
      variant: 'success',
      text: `${toFaDigits(result.item.deleted)} دستهٔ بدون تأمین‌کننده حذف شد.`
    });
    router.refresh();
  }

  async function purgeShop2() {
    if (!window.confirm('همهٔ دسته و محصول و بستهٔ Shop2 از دیتابیس حذف شود؟ FazerCards می‌ماند.')) return;
    if (!window.confirm('برگشت ندارد. ادامه می‌دهی؟')) return;
    setBusy('shop2');
    const result = await purgeShop2Catalog();
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({
      variant: 'success',
      text: `${toFaDigits(result.item.groups)} دسته، ${toFaDigits(result.item.products)} محصول، ${toFaDigits(result.item.skus)} بستهٔ Shop2 حذف شد.`
    });
    router.refresh();
  }

  if (error) {
    return <Alert variant='error' title='دسته‌ها خوانده نشد' message={error} />;
  }

  return (
    <div className='space-y-4'>
      <div className='flex flex-wrap items-end gap-3'>
        <SelectField label='منبع' value={sourceFilter} onChange={setSourceFilter} options={ORIGIN_FILTER_OPTIONS} />
        <Button type='button' variant='outline' onClick={() => apply(1)}>
          فیلتر
        </Button>
        <Button type='button' variant='outline' loading={busy === 'purge'} disabled={busy !== null} onClick={() => void purge()}>
          حذف دسته‌های ساخته‌شدهٔ بدون تأمین‌کننده
        </Button>
        <Button type='button' variant='outline' loading={busy === 'shop2'} disabled={busy !== null} onClick={() => void purgeShop2()}>
          حذف Shop2 از دیتابیس
        </Button>
      </div>
      {items.length === 0 ? (
        <Alert variant='info' title='دسته‌ای نیست' message='با این فیلتر دسته‌ای پیدا نشد.' />
      ) : (
        <DataTable
          page={page}
          perPage={perPage}
          total={total}
          totalPages={totalPages}
          onPageChange={(next) => apply(next)}
          onPerPageChange={(next) => apply(1, parseTablePerPage(next))}
        >
          <Table>
            <TableHeader className='border-b border-gray-100 dark:border-white/5'>
              <TableRow>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  دسته
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  slug
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  محصول
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  منبع
                </TableCell>
              </TableRow>
            </TableHeader>
            <TableBody className='divide-y divide-gray-100 dark:divide-white/5'>
              {items.map((group) => (
                <TableRow key={group.id}>
                  <TableCell className='px-5 py-4'>
                    <Link href={`/catalog/groups/${group.id}`} className='text-theme-sm font-medium text-brand-500'>
                      {group.title}
                    </Link>
                    {group.nameEn ? (
                      <p className='text-theme-xs mt-1 text-left text-gray-500' dir='ltr'>
                        {group.nameEn}
                      </p>
                    ) : null}
                  </TableCell>
                  <TableCell className='text-theme-sm px-5 py-4 text-gray-500'>{group.slug}</TableCell>
                  <TableCell className='px-5 py-4'>
                    <Link href={`/catalog/groups/${group.id}/products`} className='text-theme-sm text-brand-500'>
                      {toFaDigits(group.productsCount ?? 0)} محصول
                    </Link>
                  </TableCell>
                  <TableCell className='px-5 py-4'>
                    <Badge color={originBadgeColor(group.source)}>{originLabel(group.source)}</Badge>
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

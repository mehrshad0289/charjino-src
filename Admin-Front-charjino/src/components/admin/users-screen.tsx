'use client';

import { Alert, Badge, DataTable, Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui';
import { formatFaDate } from '@/lib/fa';
import { parseTablePerPage } from '@/lib/table-page';
import type { AdminUserRow } from '@/types/admin';
import { useRouter } from 'next/navigation';

const ROLE_LABEL: Record<string, string> = {
  admin: 'مدیر',
  user: 'کاربر'
};

const STATUS_COLOR: Record<string, 'success' | 'warning' | 'error'> = {
  active: 'success',
  blocked: 'error',
  pending: 'warning'
};

type UsersScreenProps = {
  items: AdminUserRow[];
  error?: string;
  page?: number;
  perPage?: number;
  totalPages?: number;
  total?: number;
};

export function UsersScreen({ items, error, page = 1, perPage = 10, totalPages = 1, total }: UsersScreenProps) {
  const router = useRouter();

  function apply(nextPage = 1, nextPerPage = perPage) {
    const params = new URLSearchParams();
    if (nextPerPage !== 10) params.set('perPage', String(nextPerPage));
    if (nextPage > 1) params.set('page', String(nextPage));
    const suffix = params.toString();
    router.push(suffix ? `/users?${suffix}` : '/users');
  }

  if (error) {
    return <Alert variant='error' title='لیست کاربران' message={error} />;
  }

  if (items.length === 0) {
    return <Alert variant='info' title='کاربری نیست' message='هنوز کاربری در فروشگاه ثبت نشده است.' />;
  }

  return (
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
              کاربر
            </TableCell>
            <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
              نقش
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
          {items.map((user) => (
            <TableRow key={user.id}>
              <TableCell className='px-5 py-4 text-start'>
                <span className='text-theme-sm block font-medium text-gray-800 dark:text-white/90'>
                  {user.name || user.phoneNumber || user.email || 'بدون نام'}
                </span>
                <span className='text-theme-xs block text-gray-500'>
                  {[user.phoneNumber, user.email].filter(Boolean).join(' · ')}
                </span>
              </TableCell>
              <TableCell className='text-theme-sm px-4 py-3 text-gray-500'>
                {ROLE_LABEL[user.role] ?? user.role}
              </TableCell>
              <TableCell className='px-4 py-3'>
                <Badge size='sm' color={STATUS_COLOR[user.status] ?? 'light'}>
                  {user.status === 'active' ? 'فعال' : user.status}
                </Badge>
              </TableCell>
              <TableCell className='text-theme-sm px-4 py-3 text-gray-500'>
                {formatFaDate(user.createdAt)}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </DataTable>
  );
}

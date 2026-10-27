'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { deleteBlogPost, publishBlogPost, unpublishBlogPost } from '@/actions/blog.actions';
import { Alert, Badge, Button, DataTable, SelectField, Table, TableBody, TableCell, TableHeader, TableRow, TextField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { formatFaDate, toFaDigits } from '@/lib/fa';
import { parseTablePerPage } from '@/lib/table-page';
import type { AdminBlogCategory, AdminBlogPost } from '@/types/blog';

type BlogLibraryScreenProps = {
  items: AdminBlogPost[];
  categories: AdminBlogCategory[];
  error?: string;
  q?: string;
  status?: string;
  categoryId?: string;
  page?: number;
  perPage?: number;
  totalPages?: number;
  total?: number;
};

export function BlogLibraryScreen({
  items,
  categories,
  error,
  q = '',
  status = '',
  categoryId = '',
  page = 1,
  perPage = 10,
  totalPages = 1,
  total
}: BlogLibraryScreenProps) {
  const router = useRouter();
  const [, setMessage] = useToastMessage();
  const [query, setQuery] = useState(q);
  const [statusFilter, setStatusFilter] = useState(status || 'all');
  const [categoryFilter, setCategoryFilter] = useState(categoryId || 'all');
  const [busy, setBusy] = useState<number | null>(null);

  function apply(nextPage = 1, nextPerPage = perPage) {
    const params = new URLSearchParams();
    if (query.trim()) params.set('q', query.trim());
    if (statusFilter !== 'all') params.set('status', statusFilter);
    if (categoryFilter !== 'all') params.set('categoryId', categoryFilter);
    if (nextPerPage !== 10) params.set('perPage', String(nextPerPage));
    if (nextPage > 1) params.set('page', String(nextPage));
    const suffix = params.toString();
    router.push(suffix ? `/content/blog?${suffix}` : '/content/blog');
  }

  async function publish(id: number, live: boolean) {
    setBusy(id);
    const result = live ? await unpublishBlogPost(id) : await publishBlogPost(id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: live ? 'از انتشار خارج شد.' : 'منتشر شد.' });
    router.refresh();
  }

  async function remove(id: number) {
    if (!window.confirm('این مطلب حذف شود؟')) return;
    setBusy(id);
    const result = await deleteBlogPost(id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'مطلب حذف شد.' });
    router.refresh();
  }

  return (
    <div className='space-y-4'>
      {error ? <Alert variant='error' title='خوانده نشد' message={error} /> : null}
      <div className='flex flex-wrap items-end gap-3'>
        <TextField label='جستجو' value={query} onChange={setQuery} placeholder='عنوان مطلب' />
        <SelectField
          label='وضعیت'
          value={statusFilter}
          onChange={setStatusFilter}
          options={[
            { value: 'all', label: 'همه' },
            { value: 'draft', label: 'پیش‌نویس' },
            { value: 'published', label: 'منتشرشده' },
            { value: 'scheduled', label: 'زمان‌بندی' }
          ]}
        />
        <SelectField
          label='دسته'
          value={categoryFilter}
          onChange={setCategoryFilter}
          options={[
            { value: 'all', label: 'همه دسته‌ها' },
            ...categories.map((category) => ({ value: String(category.id), label: category.title }))
          ]}
        />
        <Button type='button' onClick={() => apply(1)}>
          فیلتر
        </Button>
        <Link href='/content/blog/new' className='ms-auto'>
          <Button type='button'>مطلب جدید</Button>
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
              <TableCell header>عنوان</TableCell>
              <TableCell header>دسته</TableCell>
              <TableCell header>وضعیت</TableCell>
              <TableCell header>بازدید</TableCell>
              <TableCell header>به‌روز</TableCell>
              <TableCell header>عملیات</TableCell>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6}>مطلبی در کتابخانه نیست. از «مطلب جدید» ثبت کن.</TableCell>
              </TableRow>
            ) : (
              items.map((post) => (
                <TableRow key={post.id}>
                  <TableCell>
                    <Link href={`/content/blog/${post.id}`} className='font-medium text-brand-500'>
                      {post.title}
                    </Link>
                  </TableCell>
                  <TableCell>{post.categoryTitle || '—'}</TableCell>
                  <TableCell>
                    <Badge color={post.status === 'published' ? 'success' : post.status === 'scheduled' ? 'warning' : 'light'}>
                      {post.status === 'published' ? 'منتشرشده' : post.status === 'scheduled' ? 'زمان‌بندی' : 'پیش‌نویس'}
                    </Badge>
                  </TableCell>
                  <TableCell>{toFaDigits(post.viewsCount)}</TableCell>
                  <TableCell>{formatFaDate(post.updatedAt)}</TableCell>
                  <TableCell>
                    <div className='flex flex-wrap gap-2'>
                      <Button
                        type='button'
                        size='sm'
                        variant='outline'
                        loading={busy === post.id}
                        onClick={() => publish(post.id, post.status === 'published')}
                      >
                        {post.status === 'published' ? 'خروج از انتشار' : 'انتشار'}
                      </Button>
                      <Button type='button' size='sm' variant='outline' loading={busy === post.id} onClick={() => remove(post.id)}>
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

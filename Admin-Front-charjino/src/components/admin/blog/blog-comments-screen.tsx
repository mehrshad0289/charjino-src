'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { setBlogCommentStatus } from '@/actions/blog.actions';
import { CommentModerationActions } from '@/components/admin/comment-moderation-actions';
import { Alert, Badge, Button, DataTable, SelectField, Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { COMMENT_STATUS_FILTERS, commentStatusBadgeColor, commentStatusLabel } from '@/lib/comment-moderation';
import { formatFaDate } from '@/lib/fa';
import { parseTablePerPage } from '@/lib/table-page';
import type { AdminBlogComment } from '@/types/blog';

type BlogCommentsScreenProps = {
  items: AdminBlogComment[];
  error?: string;
  status?: string;
  page?: number;
  perPage?: number;
  totalPages?: number;
  total?: number;
};

export function BlogCommentsScreen({
  items,
  error,
  status = 'pending',
  page = 1,
  perPage = 10,
  totalPages = 1,
  total
}: BlogCommentsScreenProps) {
  const router = useRouter();
  const [, setMessage] = useToastMessage();
  const [statusFilter, setStatusFilter] = useState(status || 'pending');
  const [busy, setBusy] = useState<number | null>(null);

  function apply(nextPage = 1, nextPerPage = perPage) {
    const params = new URLSearchParams();
    if (statusFilter !== 'pending') params.set('status', statusFilter);
    if (nextPerPage !== 10) params.set('perPage', String(nextPerPage));
    if (nextPage > 1) params.set('page', String(nextPage));
    const suffix = params.toString();
    router.push(suffix ? `/content/blog/comments?${suffix}` : '/content/blog/comments');
  }

  async function setStatus(id: number, next: 'approved' | 'hidden') {
    setBusy(id);
    const result = await setBlogCommentStatus(id, next);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: next === 'approved' ? 'نظر تأیید شد.' : 'نظر پنهان شد.' });
    router.refresh();
  }

  return (
    <div className='space-y-4'>
      {error ? <Alert variant='error' title='خوانده نشد' message={error} /> : null}
      <div className='flex flex-wrap items-end gap-3'>
        <SelectField
          label='وضعیت'
          value={statusFilter}
          onChange={setStatusFilter}
          options={[...COMMENT_STATUS_FILTERS]}
        />
        <Button type='button' onClick={() => apply(1)}>
          فیلتر
        </Button>
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
              <TableCell header>نظر</TableCell>
              <TableCell header>مطلب</TableCell>
              <TableCell header>نویسنده</TableCell>
              <TableCell header>وضعیت</TableCell>
              <TableCell header>تاریخ</TableCell>
              <TableCell header>عملیات</TableCell>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6}>نظری نیست.</TableCell>
              </TableRow>
            ) : (
              items.map((comment) => (
                <TableRow key={comment.id}>
                  <TableCell>
                    <p className='max-w-sm whitespace-pre-wrap text-sm'>{comment.body}</p>
                  </TableCell>
                  <TableCell>{comment.postTitle || '—'}</TableCell>
                  <TableCell>{comment.authorName || '—'}</TableCell>
                  <TableCell>
                    <Badge color={commentStatusBadgeColor(comment.status)}>{commentStatusLabel(comment.status)}</Badge>
                  </TableCell>
                  <TableCell>{formatFaDate(comment.createdAt)}</TableCell>
                  <TableCell>
                    <CommentModerationActions
                      status={comment.status}
                      busy={busy === comment.id}
                      onApprove={() => setStatus(comment.id, 'approved')}
                      onHide={() => setStatus(comment.id, 'hidden')}
                    />
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

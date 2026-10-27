'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

import { deleteBanner, saveBanner } from '@/actions/admin.actions';
import { HomeMediaField } from '@/components/admin/home-media-field';
import { useToastMessage } from '@/hooks/use-toast-message';
import { HOME_BANNER_GUIDE } from '@/lib/catalog-image-guide';
import {
  Alert,
  Badge,
  Button,
  DataTable,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
  TextField,
  useClientTablePage
} from '@/components/ui';
import type { AdminBanner } from '@/types/admin';

type BannersScreenProps = {
  items: AdminBanner[];
  error?: string;
};

const emptyDraft = {
  src: '',
  title: '',
  href: '/',
  description: '',
  mediaType: 'image' as AdminBanner['mediaType'],
  visible: true,
  sortOrder: 0
};

export function BannersScreen({ items, error }: BannersScreenProps) {
  const router = useRouter();
  const [draft, setDraft] = useState(emptyDraft);
  const [busy, setBusy] = useState<string | null>(null);
  const [, setMessage] = useToastMessage();
  const pager = useClientTablePage(items);

  async function create() {
    setBusy('create');
    const result = await saveBanner({
      ...draft,
      sortOrder: Number(draft.sortOrder) || 0
    });
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setDraft(emptyDraft);
    setMessage({ variant: 'success', text: 'اسلاید اضافه شد.' });
    router.refresh();
  }

  async function patch(item: AdminBanner, payload: Partial<AdminBanner>) {
    const result = await saveBanner({ ...item, ...payload, id: item.id });
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({
      variant: 'success',
      text: payload.visible === false ? 'اسلاید مخفی شد.' : payload.visible === true ? 'اسلاید نمایش داده می‌شود.' : 'اسلاید به‌روز شد.'
    });
    router.refresh();
  }

  async function remove(id: number) {
    setBusy(`remove:${id}`);
    const result = await deleteBanner(id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'اسلاید حذف شد.' });
    router.refresh();
  }

  if (error) {
    return <Alert variant='error' title='اسلایدر' message={error} />;
  }

  return (
    <div className='space-y-8'>
      <div className='space-y-4 rounded-xl border border-gray-200 p-5 dark:border-white/5'>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>اسلاید جدید</h3>
        <HomeMediaField
          label='عکس / گیف / ویدیو'
          guide={HOME_BANNER_GUIDE}
          value={draft.src}
          onChange={(src, mediaType) =>
            setDraft((current) => ({
              ...current,
              src,
              mediaType: (mediaType as AdminBanner['mediaType']) || current.mediaType
            }))
          }
        />
        <TextField
          label='عنوان'
          value={draft.title}
          onChange={(title) => setDraft((current) => ({ ...current, title }))}
          placeholder='فروش ویژه پابجی'
        />
        <TextField
          label='لینک'
          value={draft.href}
          onChange={(href) => setDraft((current) => ({ ...current, href }))}
          placeholder='/catalog/pubg-mobile'
        />
        <TextField
          label='توضیح'
          value={draft.description}
          onChange={(description) => setDraft((current) => ({ ...current, description }))}
        />
        <Switch
          label='نمایش در فروشگاه'
          checked={draft.visible}
          onChange={(visible) => setDraft((current) => ({ ...current, visible }))}
        />
        <Button type='button' loading={busy === 'create'} disabled={busy !== null || !draft.src || !draft.title} onClick={() => void create()}>
          افزودن اسلاید
        </Button>
      </div>

      <DataTable
        page={pager.page}
        perPage={pager.perPage}
        total={pager.total}
        totalPages={pager.totalPages}
        onPageChange={pager.onPageChange}
        onPerPageChange={pager.onPerPageChange}
      >
        <Table>
            <TableHeader className='border-b border-gray-100 dark:border-white/5'>
              <TableRow>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  رسانه
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  عنوان / لینک
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  نمایش
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  عملیات
                </TableCell>
              </TableRow>
            </TableHeader>
            <TableBody className='divide-y divide-gray-100 dark:divide-white/5'>
              {pager.rows.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className='px-5 py-4'>
                    {item.mediaType === 'video' ? (
                      <video src={item.src} className='h-14 w-24 rounded-lg bg-gray-100 object-contain dark:bg-white/5' muted />
                    ) : (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={item.src} alt='' className='h-14 w-24 rounded-lg bg-gray-100 object-contain dark:bg-white/5' />
                    )}
                    <Badge>{item.mediaType}</Badge>
                  </TableCell>
                  <TableCell className='px-5 py-4'>
                    <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{item.title}</p>
                    <p className='text-theme-xs mt-1 text-gray-500' dir='ltr'>
                      {item.href}
                    </p>
                  </TableCell>
                  <TableCell className='px-5 py-4'>
                    <Switch
                      label={item.visible ? 'فعال' : 'مخفی'}
                      checked={item.visible}
                      onChange={(visible) => void patch(item, { visible })}
                    />
                  </TableCell>
                  <TableCell className='px-5 py-4'>
                    <Button
                      variant='outline'
                      size='sm'
                      type='button'
                      loading={busy === `remove:${item.id}`}
                      disabled={busy !== null}
                      onClick={() => void remove(item.id)}
                    >
                      حذف
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
        </Table>
      </DataTable>
    </div>
  );
}

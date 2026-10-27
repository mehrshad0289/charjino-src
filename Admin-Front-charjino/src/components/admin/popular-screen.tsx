'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

import { saveHomeCopy, updateProduct } from '@/actions/admin.actions';
import {
  Alert,
  Button,
  DataTable,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
  TextField,
  TextareaField,
  useClientTablePage
} from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import type { AdminHomePayload, AdminProduct } from '@/types/admin';

type PopularScreenProps = {
  home: AdminHomePayload;
  products: AdminProduct[];
  error?: string;
};

export function PopularScreen({ home, products, error }: PopularScreenProps) {
  const router = useRouter();
  const [title, setTitle] = useState(home.popularTitle);
  const [subtitle, setSubtitle] = useState(home.popularSubtitle);
  const [introHtml, setIntroHtml] = useState(home.introHtml ?? '');
  const [busy, setBusy] = useState(false);
  const [, setMessage] = useToastMessage();
  const pager = useClientTablePage(products);

  async function saveCopy() {
    setBusy(true);
    const result = await saveHomeCopy({
      popularTitle: title,
      popularSubtitle: subtitle,
      introHtml
    });
    setBusy(false);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'عنوان بخش محبوب ذخیره شد.' });
    router.refresh();
  }

  async function toggle(product: AdminProduct, isPopular: boolean) {
    const result = await updateProduct(product.id, { isPopular });
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({
      variant: 'success',
      text: isPopular ? 'به محبوب‌های هوم اضافه شد.' : 'از محبوب‌های هوم برداشته شد.'
    });
    router.refresh();
  }

  if (error) {
    return <Alert variant='error' title='محبوب‌ها' message={error} />;
  }

  return (
    <div className='space-y-8'>
      <div className='space-y-4 rounded-xl border border-gray-200 p-5 dark:border-white/5'>
        <TextField label='عنوان بخش' value={title} onChange={setTitle} />
        <TextField label='زیرعنوان' value={subtitle} onChange={setSubtitle} />
        <TextareaField
          label='متن معرفی صفحهٔ اصلی'
          hint='پاراگراف‌های همان بلوک متنی هوم؛ لینک داخلی مجاز است.'
          rows={8}
          value={introHtml}
          onChange={setIntroHtml}
        />
        <Button type='button' loading={busy} disabled={busy} onClick={() => void saveCopy()}>
          ذخیره متن
        </Button>
      </div>
      <p className='text-theme-sm text-gray-500'>
        تا ۸ محصول با سوییچ «محبوب» در هوم فروشگاه دیده می‌شوند.
      </p>
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
                  محصول
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  محبوب هوم
                </TableCell>
              </TableRow>
            </TableHeader>
            <TableBody className='divide-y divide-gray-100 dark:divide-white/5'>
              {pager.rows.map((product) => (
                <TableRow key={product.id}>
                  <TableCell className='px-5 py-4'>
                    <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{product.title}</p>
                    <p className='text-theme-xs text-gray-500'>{product.categoryName}</p>
                  </TableCell>
                  <TableCell className='px-5 py-4'>
                    <Switch
                      label={product.isPopular ? 'در هوم' : 'خارج'}
                      checked={product.isPopular}
                      onChange={(checked) => void toggle(product, checked)}
                    />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
        </Table>
      </DataTable>
    </div>
  );
}

'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import * as Yup from 'yup';

import { listProducts, saveHomeSections } from '@/actions/admin.actions';
import { Alert, Button, Switch, TextField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import type { AdminProduct, HomeSectionSetting } from '@/types/admin';

type HomeSectionsScreenProps = {
  sections: HomeSectionSetting[];
  error?: string;
};

type Hit = { id: number; title: string };

export function HomeSectionsScreen({ sections, error }: HomeSectionsScreenProps) {
  const router = useRouter();
  const [query, setQuery] = useState<Record<number, string>>({});
  const [hits, setHits] = useState<Record<number, Hit[]>>({});
  const [searching, setSearching] = useState<number | null>(null);
  const [, setMessage] = useToastMessage();

  const form = useFormik({
    enableReinitialize: true,
    initialValues: {
      sections: sections.map((row) => ({
        groupId: row.groupId,
        title: row.title,
        slug: row.slug,
        enabled: row.enabled,
        limit: row.limit,
        productIds: row.productIds,
        labels: Object.fromEntries(row.products.map((product) => [product.id, product.title])) as Record<number, string>
      }))
    },
    validationSchema: Yup.object({
      sections: Yup.array().of(
        Yup.object({
          groupId: Yup.number().required(),
          enabled: Yup.boolean().required(),
          limit: Yup.number().min(1, 'حداقل ۱').max(24, 'حداکثر ۲۴').required('تعداد لازم است'),
          productIds: Yup.array().of(Yup.number())
        })
      )
    }),
    onSubmit: async (values) => {
      const result = await saveHomeSections(
        values.sections.map((row) => ({
          groupId: row.groupId,
          enabled: row.enabled,
          limit: Number(row.limit),
          productIds: row.productIds.slice(0, Number(row.limit))
        }))
      );
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        return;
      }
      setMessage({ variant: 'success', text: 'بخش‌های صفحهٔ اصلی ذخیره شد.' });
      router.refresh();
    }
  });

  async function search(index: number, groupId: number) {
    setSearching(groupId);
    const result = await listProducts({
      category: groupId,
      q: query[groupId] || '',
      visible: '1',
      perPage: 8
    });
    setSearching(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setHits((current) => ({
      ...current,
      [groupId]: result.items.map((item: AdminProduct) => ({ id: item.id, title: item.title }))
    }));
  }

  function addProduct(index: number, hit: Hit) {
    const row = form.values.sections[index];
    if (!row || row.productIds.includes(hit.id) || row.productIds.length >= Number(row.limit)) return;
    void form.setFieldValue(`sections.${index}.productIds`, [...row.productIds, hit.id]);
    void form.setFieldValue(`sections.${index}.labels`, { ...row.labels, [hit.id]: hit.title });
  }

  function removeProduct(index: number, productId: number) {
    const row = form.values.sections[index];
    if (!row) return;
    void form.setFieldValue(
      `sections.${index}.productIds`,
      row.productIds.filter((id) => id !== productId)
    );
  }

  if (error) {
    return <Alert variant='error' title='صفحه اصلی' message={error} />;
  }

  return (
    <form className='space-y-4' onSubmit={form.handleSubmit}>
      <p className='text-theme-sm text-gray-500'>
        هر دسته را برای هوم روشن کن و تعداد نمایش را بگذار. اگر محصولی انتخاب نکنی، همان تعداد از جدیدترین‌های دسته
        می‌آید. دکمهٔ مشاهده همه در فروشگاه به فهرست همان دسته می‌رود.
      </p>
      {form.values.sections.map((row, index) => (
        <div key={row.groupId} className='space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/5'>
          <div className='flex flex-wrap items-center justify-between gap-3'>
            <div>
              <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{row.title}</p>
              <p className='text-theme-xs text-gray-500'>{row.slug}</p>
            </div>
            <Switch
              label={row.enabled ? 'در هوم' : 'خاموش'}
              checked={row.enabled}
              onChange={(checked) => void form.setFieldValue(`sections.${index}.enabled`, checked)}
            />
          </div>
          <TextField
            label='تعداد محصول در هوم'
            value={String(row.limit)}
            onChange={(value) => void form.setFieldValue(`sections.${index}.limit`, Number(value.replace(/\D/g, '')) || 1)}
          />
          <div className='grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end'>
            <TextField
              label='جستجوی محصول همین دسته'
              placeholder='خالی بماند تا جدیدترین‌ها بیاید'
              value={query[row.groupId] || ''}
              onChange={(value) => setQuery((current) => ({ ...current, [row.groupId]: value }))}
            />
            <Button
              type='button'
              variant='outline'
              loading={searching === row.groupId}
              disabled={form.isSubmitting || searching !== null}
              onClick={() => void search(index, row.groupId)}
            >
              جستجو
            </Button>
          </div>
          {(hits[row.groupId] || []).length > 0 ? (
            <ul className='space-y-2'>
              {hits[row.groupId].map((hit) => (
                <li key={hit.id} className='flex items-center justify-between gap-2'>
                  <span className='text-theme-sm text-gray-800 dark:text-white/90'>{hit.title}</span>
                  <Button type='button' size='sm' variant='outline' onClick={() => addProduct(index, hit)}>
                    انتخاب
                  </Button>
                </li>
              ))}
            </ul>
          ) : null}
          {row.productIds.length > 0 ? (
            <ul className='space-y-2'>
              {row.productIds.map((id) => (
                <li key={id} className='flex items-center justify-between gap-2'>
                  <span className='text-theme-sm text-gray-800 dark:text-white/90'>{row.labels[id] || id}</span>
                  <Button type='button' size='sm' variant='outline' onClick={() => removeProduct(index, id)}>
                    حذف از هوم
                  </Button>
                </li>
              ))}
            </ul>
          ) : (
            <p className='text-theme-xs text-gray-500'>محصول دستی انتخاب نشده؛ جدیدترین‌های دسته نشان داده می‌شود.</p>
          )}
        </div>
      ))}
      <Button type='submit' loading={form.isSubmitting} disabled={form.isSubmitting || form.values.sections.length === 0}>
        ذخیره بخش‌ها
      </Button>
    </form>
  );
}

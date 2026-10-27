'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { useFormik } from 'formik';
import * as Yup from 'yup';

import { deleteGroup, updateGroup } from '@/actions/admin.actions';
import { CatalogImageField } from '@/components/admin/catalog-image-field';
import { PurchaseGuideEditor } from '@/components/admin/purchase-guide-editor';
import { catalogImageGuide } from '@/lib/catalog-image-guide';
import { normalizePurchaseGuideItems } from '@/lib/purchase-guide';
import { Button, Switch, TextField, TextareaField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { originLabel } from '@/lib/catalog-origin';
import type { AdminGroup } from '@/types/admin';

type GroupEditScreenProps = {
  group: AdminGroup;
};

const groupSchema = Yup.object({
  title: Yup.string().trim().required('نام فارسی لازم است').max(120, 'حداکثر ۱۲۰ کاراکتر'),
  nameEn: Yup.string().trim().required('نام انگلیسی لازم است').max(160, 'حداکثر ۱۶۰ کاراکتر'),
  slug: Yup.string()
    .trim()
    .required('slug لازم است')
    .matches(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, 'فقط حروف انگلیسی کوچک، عدد و خط تیره'),
  description: Yup.string().max(4000, 'حداکثر ۴۰۰۰ کاراکتر'),
  seoTitle: Yup.string().max(180, 'حداکثر ۱۸۰ کاراکتر'),
  seoDescription: Yup.string().max(300, 'حداکثر ۳۰۰ کاراکتر'),
  purchaseGuideItems: Yup.array().max(20),
  imageUrl: Yup.string().max(500, 'حداکثر ۵۰۰ کاراکتر'),
  sortOrder: Yup.string(),
  visible: Yup.boolean()
});

export function GroupEditScreen({ group }: GroupEditScreenProps) {
  const router = useRouter();
  const [busy, setBusy] = useState<string | null>(null);
  const [, setMessage] = useToastMessage();

  const form = useFormik({
    enableReinitialize: true,
    initialValues: {
      title: group.title,
      nameEn: group.nameEn ?? '',
      slug: group.slug,
      description: group.description ?? '',
      seoTitle: group.seoTitle ?? '',
      seoDescription: group.seoDescription ?? '',
      purchaseGuideItems: normalizePurchaseGuideItems(group.purchaseGuideItems, group.purchaseGuide),
      imageUrl: group.imageUrl ?? '',
      visible: group.visible,
      sortOrder: String(group.sortOrder ?? 0)
    },
    validationSchema: groupSchema,
    onSubmit: async (values) => {
      setBusy('save');
      const result = await updateGroup(group.id, {
        title: values.title.trim(),
        nameEn: values.nameEn.trim(),
        slug: values.slug.trim(),
        description: values.description,
        seoTitle: values.seoTitle.trim() === '' ? null : values.seoTitle.trim(),
        seoDescription: values.seoDescription.trim() === '' ? null : values.seoDescription.trim(),
        purchaseGuideItems: values.purchaseGuideItems,
        imageUrl: values.imageUrl,
        visible: values.visible,
        sortOrder: Number(values.sortOrder) || 0
      });
      setBusy(null);
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        return;
      }
      setMessage({ variant: 'success', text: 'دسته ذخیره شد.' });
      router.refresh();
    }
  });

  async function remove() {
    if (!window.confirm('این دسته حذف شود؟ دستهٔ همگام‌شده فقط مخفی می‌شود.')) return;
    setBusy('delete');
    const result = await deleteGroup(group.id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'دسته حذف شد.' });
    router.push('/catalog/groups');
  }

  return (
    <div className='space-y-6'>
      <Link href={`/catalog/groups/${group.id}/products`} className='text-theme-sm inline-flex font-medium text-brand-500'>
        محصولات این دسته
      </Link>
      <p className='text-sm text-gray-500'>منبع: {originLabel(group.source)}</p>
      <p className='text-sm text-gray-500'>نام تأمین‌کننده: {group.name}</p>
      <form className='space-y-6' onSubmit={form.handleSubmit}>
        <TextField
          name='title'
          label='نام فارسی'
          hint='برای نمایش فروشگاه، جستجو و سئو'
          value={form.values.title}
          onChange={(value) => void form.setFieldValue('title', value)}
          onBlur={() => form.setFieldTouched('title', true)}
          previewState={form.touched.title && form.errors.title ? 'error' : 'default'}
        />
        {form.touched.title && form.errors.title ? (
          <p className='text-theme-xs text-error-500'>{form.errors.title}</p>
        ) : null}
        <TextField
          name='nameEn'
          label='نام انگلیسی'
          hint='برای جستجو و سئو؛ با همگام تأمین‌کننده بازنویسی نمی‌شود'
          dir='ltr'
          className='text-left'
          placeholder='Game top-up'
          value={form.values.nameEn}
          onChange={(value) => void form.setFieldValue('nameEn', value)}
          onBlur={() => form.setFieldTouched('nameEn', true)}
          previewState={form.touched.nameEn && form.errors.nameEn ? 'error' : 'default'}
        />
        {form.touched.nameEn && form.errors.nameEn ? (
          <p className='text-theme-xs text-error-500'>{form.errors.nameEn}</p>
        ) : null}
        <TextField
          name='slug'
          label='slug'
          dir='ltr'
          className='text-left'
          value={form.values.slug}
          onChange={(value) => void form.setFieldValue('slug', value)}
          onBlur={() => form.setFieldTouched('slug', true)}
          previewState={form.touched.slug && form.errors.slug ? 'error' : 'default'}
        />
        {form.touched.slug && form.errors.slug ? (
          <p className='text-theme-xs text-error-500'>{form.errors.slug}</p>
        ) : null}
        <TextareaField
          label='توضیح'
          hint='خط جدید و فاصله‌ها همان‌طور در فروشگاه دیده می‌شوند'
          rows={6}
          value={form.values.description}
          onChange={(value) => void form.setFieldValue('description', value)}
        />
        <TextField
          name='seoTitle'
          label='عنوان سئو (seo_title)'
          hint='اگر خالی باشد الگوی پیش‌فرض دسته استفاده می‌شود'
          value={form.values.seoTitle}
          onChange={(value) => void form.setFieldValue('seoTitle', value)}
        />
        <TextareaField
          label='توضیح سئو (seo_description)'
          hint='اگر خالی باشد توضیح یکتا با تومان، تحویل فوری و پشتیبانی ساخته می‌شود'
          rows={3}
          value={form.values.seoDescription}
          onChange={(value) => void form.setFieldValue('seoDescription', value)}
        />
        <div className='space-y-3'>
          <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>راهنمای خرید</h3>
          <PurchaseGuideEditor
            items={form.values.purchaseGuideItems}
            onChange={(items) => void form.setFieldValue('purchaseGuideItems', items)}
          />
        </div>
        <CatalogImageField
          label='عکس دسته'
          guide={catalogImageGuide(form.values.slug || group.slug)}
          value={form.values.imageUrl}
          onChange={(value) => void form.setFieldValue('imageUrl', value)}
        />
        <TextField
          label='ترتیب'
          value={form.values.sortOrder}
          onChange={(value) => void form.setFieldValue('sortOrder', value)}
        />
        <Switch
          label='نمایش در فروشگاه'
          checked={form.values.visible}
          onChange={(next) => void form.setFieldValue('visible', next)}
        />
        <div className='flex flex-wrap gap-3'>
          <Button type='submit' loading={busy === 'save'} disabled={busy !== null}>
            ذخیره
          </Button>
          <Button type='button' variant='outline' loading={busy === 'delete'} disabled={busy !== null} onClick={() => void remove()}>
            حذف دسته
          </Button>
        </div>
      </form>
    </div>
  );
}

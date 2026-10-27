'use client';

import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import * as Yup from 'yup';

import { createGiftProduct } from '@/actions/admin.actions';
import { CatalogImageField } from '@/components/admin/catalog-image-field';
import { Button, SelectField, TextField, TextareaField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { GIFT_PLATFORM_OPTIONS } from '@/lib/game-key-platform';
import type { AdminGroup } from '@/types/admin';

const schema = Yup.object({
  title: Yup.string().trim().required('نام فارسی لازم است').max(160),
  nameEn: Yup.string().trim().required('نام انگلیسی لازم است').max(160),
  catalogGroupId: Yup.string().required('دسته لازم است'),
  platform: Yup.string().required('پلتفرم لازم است'),
  region: Yup.string().max(40),
  publisher: Yup.string().max(120),
  description: Yup.string().max(8000),
  imageUrl: Yup.string().max(2048)
});

export function GiftProductForm({ groups }: { groups: AdminGroup[] }) {
  const router = useRouter();
  const [, setMessage] = useToastMessage();
  const form = useFormik({
    initialValues: {
      title: '',
      nameEn: '',
      catalogGroupId: groups[0] ? String(groups[0].id) : '',
      platform: 'xbox',
      region: 'US',
      publisher: '',
      description: '',
      imageUrl: ''
    },
    validationSchema: schema,
    onSubmit: async (values) => {
      const result = await createGiftProduct({
        title: values.title.trim(),
        nameEn: values.nameEn.trim(),
        catalogGroupId: Number(values.catalogGroupId),
        platform: values.platform,
        region: values.region.trim(),
        publisher: values.publisher.trim(),
        description: values.description.trim(),
        imageUrl: values.imageUrl.trim()
      });
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        return;
      }
      setMessage({ variant: 'success', text: 'محصول ساخته شد. حالا بسته‌ها را با قیمت دلاری و گیفت معادل اضافه کن.' });
      router.push(`/catalog/products/${result.item.id}`);
    }
  });

  return (
    <form className='space-y-4' onSubmit={form.handleSubmit}>
      <p className='text-theme-sm leading-7 text-gray-500'>
        برای بازی یا محصولی که تأمین‌کننده مستقیم نمی‌فروشد. در دستهٔ انتخابی با مشخصات خودش دیده می‌شود و به‌جای خود
        بسته، گیفت‌کارت همان پلتفرم و ریجن تحویل می‌شود.
      </p>
      <TextField
        label='نام فارسی'
        value={form.values.title}
        onChange={(value) => void form.setFieldValue('title', value)}
        previewState={form.touched.title && form.errors.title ? 'error' : 'default'}
      />
      <TextField
        label='نام انگلیسی'
        dir='ltr'
        className='text-left'
        placeholder='FC 27 Xbox'
        value={form.values.nameEn}
        onChange={(value) => void form.setFieldValue('nameEn', value)}
        previewState={form.touched.nameEn && form.errors.nameEn ? 'error' : 'default'}
      />
      <SelectField
        label='دسته'
        value={form.values.catalogGroupId || 'none'}
        onChange={(value) => void form.setFieldValue('catalogGroupId', value === 'none' ? '' : value)}
        options={[
          { value: 'none', label: 'انتخاب کن' },
          ...groups.map((group) => ({ value: String(group.id), label: group.title }))
        ]}
      />
      <SelectField
        label='پلتفرم'
        value={form.values.platform}
        onChange={(value) => void form.setFieldValue('platform', value)}
        options={GIFT_PLATFORM_OPTIONS}
      />
      <TextField
        label='ریجن'
        dir='ltr'
        className='text-left'
        hint='مثلاً US یا EU یا TR؛ گیفت معادل با همین ریجن پیشنهاد می‌شود'
        value={form.values.region}
        onChange={(value) => void form.setFieldValue('region', value.toUpperCase())}
      />
      <TextField
        label='ناشر'
        value={form.values.publisher}
        onChange={(value) => void form.setFieldValue('publisher', value)}
      />
      <TextareaField
        label='توضیح'
        value={form.values.description}
        onChange={(value) => void form.setFieldValue('description', value)}
      />
      <CatalogImageField
        label='تصویر'
        alt={form.values.title || 'محصول'}
        value={form.values.imageUrl}
        onChange={(url) => void form.setFieldValue('imageUrl', url)}
      />
      <Button type='submit' loading={form.isSubmitting}>
        ساخت محصول
      </Button>
    </form>
  );
}

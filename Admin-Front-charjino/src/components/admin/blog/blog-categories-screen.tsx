'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import * as Yup from 'yup';
import { createBlogCategory, deleteBlogCategory, updateBlogCategory } from '@/actions/blog.actions';
import { BlogCoverField } from '@/components/admin/blog/blog-cover-field';
import { RichTextField } from '@/components/admin/blog/rich-text-field';
import { Alert, Button, Checkbox, SelectField, Switch, Table, TableBody, TableCell, TableHeader, TableRow, TextField, TextareaField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import type { AdminBlogCategory } from '@/types/blog';

const schema = Yup.object({
  title: Yup.string().trim().required('عنوان دسته لازم است').min(2).max(80),
  slug: Yup.string().max(80),
  description: Yup.string().max(500),
  bodyHtml: Yup.string(),
  metaTitle: Yup.string().max(80),
  metaDescription: Yup.string().max(180),
  parentId: Yup.string()
});

type BlogCategoriesScreenProps = {
  items: AdminBlogCategory[];
  error?: string;
};

export function BlogCategoriesScreen({ items, error }: BlogCategoriesScreenProps) {
  const router = useRouter();
  const [, setMessage] = useToastMessage();
  const [editingId, setEditingId] = useState<number | null>(null);
  const formik = useFormik({
    enableReinitialize: true,
    initialValues: {
      title: '',
      slug: '',
      description: '',
      bodyHtml: '',
      metaTitle: '',
      metaDescription: '',
      image: '',
      imageAlt: '',
      noindex: false,
      parentId: '',
      isActive: true
    },
    validationSchema: schema,
    onSubmit: async (values, helpers) => {
      const payload = {
        title: values.title.trim(),
        slug: values.slug.trim() || undefined,
        description: values.description.trim() || undefined,
        bodyHtml: values.bodyHtml || undefined,
        metaTitle: values.metaTitle.trim() || undefined,
        metaDescription: values.metaDescription.trim() || undefined,
        image: values.image.trim() || undefined,
        imageAlt: values.imageAlt.trim() || undefined,
        noindex: values.noindex,
        parentId: values.parentId ? Number(values.parentId) : null,
        isActive: values.isActive
      };
      const result = editingId
        ? await updateBlogCategory(editingId, payload)
        : await createBlogCategory(payload);
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        helpers.setSubmitting(false);
        return;
      }
      setMessage({ variant: 'success', text: editingId ? 'دسته به‌روز شد.' : 'دسته ساخته شد.' });
      setEditingId(null);
      helpers.resetForm();
      router.refresh();
    }
  });

  function edit(category: AdminBlogCategory) {
    setEditingId(category.id);
    void formik.setValues({
      title: category.title,
      slug: category.slug,
      description: category.description ?? '',
      bodyHtml: category.bodyHtml ?? '',
      metaTitle: category.metaTitle ?? '',
      metaDescription: category.metaDescription ?? '',
      image: category.image ?? '',
      imageAlt: category.imageAlt ?? '',
      noindex: Boolean(category.noindex),
      parentId: category.parentId ? String(category.parentId) : '',
      isActive: category.isActive
    });
  }

  async function remove(id: number) {
    if (!window.confirm('این دسته حذف شود؟')) return;
    const result = await deleteBlogCategory(id);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'دسته حذف شد.' });
    router.refresh();
  }

  return (
    <div className='space-y-6'>
      {error ? <Alert variant='error' title='خوانده نشد' message={error} /> : null}
      <form className='grid gap-4 md:grid-cols-2' onSubmit={formik.handleSubmit}>
        <TextField label='عنوان دسته' value={formik.values.title} onChange={(value) => void formik.setFieldValue('title', value)} />
        <TextField label='نامک' value={formik.values.slug} onChange={(value) => void formik.setFieldValue('slug', value)} />
        <SelectField
          label='دسته والد'
          value={formik.values.parentId}
          onChange={(value) => void formik.setFieldValue('parentId', value)}
          options={[
            { value: '', label: 'بدون والد' },
            ...items
              .filter((item) => item.id !== editingId && !item.parentId)
              .map((item) => ({ value: String(item.id), label: item.title }))
          ]}
        />
        <Switch label='فعال در فروشگاه' checked={formik.values.isActive} onChange={(checked) => void formik.setFieldValue('isActive', checked)} />
        <Checkbox label='noindex' checked={formik.values.noindex} onChange={(checked) => void formik.setFieldValue('noindex', checked)} />
        <div className='md:col-span-2'>
          <TextareaField
            label='توضیح کوتاه'
            rows={2}
            value={formik.values.description}
            onChange={(value) => void formik.setFieldValue('description', value)}
          />
        </div>
        <div className='md:col-span-2'>
          <RichTextField
            label='توضیح غنی بالای آرشیو'
            value={formik.values.bodyHtml}
            onChange={(value) => void formik.setFieldValue('bodyHtml', value)}
          />
        </div>
        <TextField label='عنوان متا' value={formik.values.metaTitle} onChange={(value) => void formik.setFieldValue('metaTitle', value)} />
        <TextField
          label='توضیح متا'
          value={formik.values.metaDescription}
          onChange={(value) => void formik.setFieldValue('metaDescription', value)}
        />
        <div className='md:col-span-2'>
          <BlogCoverField
            label='تصویر دسته'
            value={formik.values.image}
            alt={formik.values.imageAlt}
            role='inline'
            onChange={(next) => {
              void formik.setFieldValue('image', next.url);
              if (next.alt !== undefined) void formik.setFieldValue('imageAlt', next.alt);
            }}
          />
        </div>
        <div className='flex gap-2'>
          <Button type='submit' loading={formik.isSubmitting}>
            {editingId ? 'ذخیره دسته' : 'افزودن دسته'}
          </Button>
          {editingId ? (
            <Button
              type='button'
              variant='outline'
              onClick={() => {
                setEditingId(null);
                formik.resetForm();
              }}
            >
              انصراف
            </Button>
          ) : null}
        </div>
      </form>
      <Table>
        <TableHeader>
          <TableRow>
            <TableCell header>عنوان</TableCell>
            <TableCell header>نامک</TableCell>
            <TableCell header>والد</TableCell>
            <TableCell header>وضعیت</TableCell>
            <TableCell header>عملیات</TableCell>
          </TableRow>
        </TableHeader>
        <TableBody>
          {items.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5}>هنوز دسته‌ای نیست.</TableCell>
            </TableRow>
          ) : (
            items.map((category) => (
              <TableRow key={category.id}>
                <TableCell>{category.title}</TableCell>
                <TableCell>{category.slug}</TableCell>
                <TableCell>{category.parentTitle || '—'}</TableCell>
                <TableCell>{category.isActive ? 'فعال' : 'غیرفعال'}</TableCell>
                <TableCell>
                  <div className='flex gap-2'>
                    <Button type='button' size='sm' variant='outline' onClick={() => edit(category)}>
                      ویرایش
                    </Button>
                    <Button type='button' size='sm' variant='outline' onClick={() => remove(category.id)}>
                      حذف
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  );
}

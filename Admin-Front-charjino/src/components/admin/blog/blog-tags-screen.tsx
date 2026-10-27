'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import * as Yup from 'yup';
import { createBlogTag, deleteBlogTag, updateBlogTag } from '@/actions/blog.actions';
import { BlogCoverField } from '@/components/admin/blog/blog-cover-field';
import { RichTextField } from '@/components/admin/blog/rich-text-field';
import { Alert, Button, Checkbox, Table, TableBody, TableCell, TableHeader, TableRow, TextField, TextareaField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import type { AdminBlogTag } from '@/types/blog';

const schema = Yup.object({
  title: Yup.string().trim().required('عنوان تگ لازم است').min(2).max(80),
  slug: Yup.string().max(80),
  description: Yup.string().max(500),
  bodyHtml: Yup.string(),
  metaTitle: Yup.string().max(80),
  metaDescription: Yup.string().max(180)
});

type BlogTagsScreenProps = {
  items: AdminBlogTag[];
  error?: string;
};

export function BlogTagsScreen({ items, error }: BlogTagsScreenProps) {
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
      forceNoindex: false
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
        forceNoindex: values.forceNoindex
      };
      const result = editingId ? await updateBlogTag(editingId, payload) : await createBlogTag(payload);
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        helpers.setSubmitting(false);
        return;
      }
      setMessage({ variant: 'success', text: editingId ? 'تگ به‌روز شد.' : 'تگ ساخته شد.' });
      setEditingId(null);
      helpers.resetForm();
      router.refresh();
    }
  });

  function edit(tag: AdminBlogTag) {
    setEditingId(tag.id);
    void formik.setValues({
      title: tag.title,
      slug: tag.slug,
      description: tag.description ?? '',
      bodyHtml: tag.bodyHtml ?? '',
      metaTitle: tag.metaTitle ?? '',
      metaDescription: tag.metaDescription ?? '',
      image: tag.image ?? '',
      imageAlt: tag.imageAlt ?? '',
      forceNoindex: Boolean(tag.forceNoindex)
    });
  }

  async function remove(id: number) {
    if (!window.confirm('این تگ حذف شود؟')) return;
    const result = await deleteBlogTag(id);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'تگ حذف شد.' });
    router.refresh();
  }

  return (
    <div className='space-y-6'>
      {error ? <Alert variant='error' title='خوانده نشد' message={error} /> : null}
      <form className='grid gap-4 md:grid-cols-2' onSubmit={formik.handleSubmit}>
        <TextField label='عنوان تگ' value={formik.values.title} onChange={(value) => void formik.setFieldValue('title', value)} />
        <TextField label='نامک' value={formik.values.slug} onChange={(value) => void formik.setFieldValue('slug', value)} />
        <Checkbox
          label='noindex اجباری'
          checked={formik.values.forceNoindex}
          onChange={(checked) => void formik.setFieldValue('forceNoindex', checked)}
        />
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
            label='تصویر تگ'
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
            {editingId ? 'ذخیره تگ' : 'افزودن تگ'}
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
            <TableCell header>مطالب</TableCell>
            <TableCell header>ایندکس</TableCell>
            <TableCell header>عملیات</TableCell>
          </TableRow>
        </TableHeader>
        <TableBody>
          {items.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5}>هنوز تگی نیست.</TableCell>
            </TableRow>
          ) : (
            items.map((tag) => (
              <TableRow key={tag.id}>
                <TableCell>{tag.title}</TableCell>
                <TableCell>{tag.slug}</TableCell>
                <TableCell>{tag.postsCount ?? 0}</TableCell>
                <TableCell>{tag.noindex ? 'noindex' : 'index'}</TableCell>
                <TableCell>
                  <div className='flex gap-2'>
                    <Button type='button' size='sm' variant='outline' onClick={() => edit(tag)}>
                      ویرایش
                    </Button>
                    <Button type='button' size='sm' variant='outline' onClick={() => remove(tag.id)}>
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

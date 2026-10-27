'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import * as Yup from 'yup';
import {
  deleteSearchSynonym,
  saveSearchSynonym,
  syncSearchSynonyms,
  type AdminSearchHealth,
  type AdminSearchSynonym,
} from '@/actions/admin.actions';
import { Button, TextField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';

const schema = Yup.object({
  term: Yup.string().trim().required('عبارت لازم است').max(120, 'حداکثر ۱۲۰ کاراکتر'),
  synonyms: Yup.string().trim().required('حداقل یک مترادف لازم است').max(500, 'حداکثر ۵۰۰ کاراکتر'),
  group_slug: Yup.string().max(120, 'حداکثر ۱۲۰ کاراکتر'),
  is_active: Yup.boolean(),
});

export function SearchSynonymsScreen({
  items,
  health,
  error,
}: {
  items: AdminSearchSynonym[];
  health?: AdminSearchHealth;
  error?: string;
}) {
  const router = useRouter();
  const [busy, setBusy] = useState<string | null>(null);
  const [, setMessage] = useToastMessage(error ? { variant: 'error', text: error } : null);
  const formik = useFormik({
    initialValues: { term: '', synonyms: '', group_slug: '', is_active: true },
    validationSchema: schema,
    onSubmit: async (values, helpers) => {
      setBusy('save');
      const result = await saveSearchSynonym(values);
      setBusy(null);
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        return;
      }
      helpers.resetForm();
      setMessage({ variant: 'success', text: 'مترادف ذخیره شد.' });
      router.refresh();
    },
  });

  async function remove(id: number) {
    if (!window.confirm('این مترادف حذف شود؟')) return;
    setBusy(`del-${id}`);
    const result = await deleteSearchSynonym(id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    router.refresh();
  }

  async function sync() {
    if (!window.confirm('مترادف‌ها به Meilisearch فرستاده شود؟')) return;
    setBusy('sync');
    const result = await syncSearchSynonyms();
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'همگام شد.' });
    router.refresh();
  }

  return (
    <div className="flex flex-col gap-6">
      {health ? (
        <p className="text-theme-sm text-gray-500">
          driver={health.driver} prefix={health.prefix}
          {health.indexes
            ? Object.entries(health.indexes)
                .map(([name, row]) => ` ${name}:${row.documents ?? 0}`)
                .join('')
            : ''}
        </p>
      ) : null}
      <form onSubmit={formik.handleSubmit} className="grid gap-3 sm:grid-cols-2">
        <TextField
          label="عبارت"
          name="term"
          value={formik.values.term}
          onChange={(value) => void formik.setFieldValue('term', value)}
          onBlur={() => formik.setFieldTouched('term', true)}
          previewState={formik.touched.term && formik.errors.term ? 'error' : 'default'}
        />
        <TextField
          label="مترادف‌ها (با ویرگول)"
          name="synonyms"
          value={formik.values.synonyms}
          onChange={(value) => void formik.setFieldValue('synonyms', value)}
          onBlur={() => formik.setFieldTouched('synonyms', true)}
          previewState={formik.touched.synonyms && formik.errors.synonyms ? 'error' : 'default'}
        />
        <TextField
          label="گروه (اختیاری)"
          name="group_slug"
          value={formik.values.group_slug}
          onChange={(value) => void formik.setFieldValue('group_slug', value)}
        />
        <div className="flex items-end gap-3">
          <Button type="submit" disabled={busy === 'save'}>
            ذخیره مترادف
          </Button>
          <Button type="button" variant="outline" disabled={busy === 'sync'} onClick={() => void sync()}>
            همگام با Meili
          </Button>
        </div>
      </form>
      <ul className="divide-y divide-gray-200 dark:divide-gray-800">
        {items.map((row) => (
          <li key={row.id} className="flex items-center justify-between gap-3 py-3 text-sm">
            <div>
              <div className="font-medium">{row.term}</div>
              <div className="text-gray-500">{row.synonyms.join('، ')}</div>
            </div>
            <Button type="button" variant="outline" disabled={busy === `del-${row.id}`} onClick={() => void remove(row.id)}>
              حذف
            </Button>
          </li>
        ))}
      </ul>
    </div>
  );
}

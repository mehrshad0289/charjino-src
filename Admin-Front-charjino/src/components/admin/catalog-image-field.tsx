'use client';

import { useState } from 'react';

import { uploadCatalogImage } from '@/actions/admin.actions';
import { FileField, TextField } from '@/components/ui';
import { notify } from '@/lib/toast';
import { adminMediaSrc } from '@/lib/admin-media';

type CatalogImageFieldProps = {
  label: string;
  alt?: string;
  value: string;
  onChange: (url: string) => void;
  guide?: string;
};

export function CatalogImageField({ label, alt = '', value, onChange, guide }: CatalogImageFieldProps) {
  const [busy, setBusy] = useState(false);
  const [hint, setHint] = useState('JPG یا PNG تا ۴ مگابایت');
  const preview = adminMediaSrc(value);

  return (
    <div className='space-y-4'>
      {preview ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={preview} alt={alt} className='h-24 w-24 rounded-xl bg-gray-100 object-contain dark:bg-gray-900' />
      ) : null}
      {guide ? <p className='text-theme-sm leading-6 text-gray-600 dark:text-gray-300'>{guide}</p> : null}
      <FileField
        label={label}
        hint={busy ? 'در حال بارگذاری...' : hint}
        previewState={busy ? 'disabled' : 'default'}
        onChange={async (event) => {
          const file = event.target.files?.[0];
          if (!file) return;
          setBusy(true);
          const formData = new FormData();
          formData.append('file', file);
          const result = await uploadCatalogImage(formData);
          setBusy(false);
          if (!result.ok) {
            setHint(result.message);
            notify.error(result.message);
            return;
          }
          setHint('تصویر ذخیره شد');
          notify.success('تصویر ذخیره شد.');
          onChange(result.url);
        }}
      />
      <TextField
        label='آدرس تصویر'
        placeholder='https://...'
        value={value}
        onChange={onChange}
      />
    </div>
  );
}

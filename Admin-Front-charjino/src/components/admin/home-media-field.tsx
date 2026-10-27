'use client';

import { useState } from 'react';

import { uploadCatalogImage } from '@/actions/admin.actions';
import { FileField, TextField } from '@/components/ui';
import { notify } from '@/lib/toast';
import { adminMediaSrc } from '@/lib/admin-media';

type HomeMediaFieldProps = {
  label: string;
  value: string;
  guide?: string;
  onChange: (url: string, mediaType?: string) => void;
};

export function HomeMediaField({ label, value, guide, onChange }: HomeMediaFieldProps) {
  const [busy, setBusy] = useState(false);
  const [hint, setHint] = useState('عکس، گیف یا ویدیو mp4/webm تا ۸ مگابایت');
  const isVideo = /\.(mp4|webm|mov)(\?|$)/i.test(value);

  return (
    <div className='space-y-4'>
      {value ? (
        isVideo ? (
          <video src={adminMediaSrc(value)} className='h-auto w-full max-w-md rounded-xl object-contain' muted playsInline />
        ) : (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={adminMediaSrc(value)} alt='' className='h-auto w-full max-w-md rounded-xl object-contain' />
        )
      ) : null}
      {guide ? <p className='text-theme-sm leading-6 text-gray-600 dark:text-gray-300'>{guide}</p> : null}
      <FileField
        label={label}
        accept='image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm'
        hint={busy ? 'در حال بارگذاری...' : hint}
        previewState={busy ? 'disabled' : 'default'}
        onChange={async (event) => {
          const file = event.target.files?.[0];
          if (!file) return;
          setBusy(true);
          const formData = new FormData();
          formData.append('file', file);
          formData.append('purpose', 'banner');
          const result = await uploadCatalogImage(formData);
          setBusy(false);
          if (!result.ok) {
            setHint(result.message);
            notify.error(result.message);
            return;
          }
          setHint('فایل ذخیره شد');
          notify.success('فایل ذخیره شد.');
          onChange(result.url, result.mediaType);
        }}
      />
      <TextField
        label='آدرس فایل'
        placeholder='https://api.charjino.ir/storage/banners/... یا /image/banners/...'
        value={value}
        onChange={(url) => onChange(url)}
      />
    </div>
  );
}

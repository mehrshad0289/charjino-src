'use client';

import { useState } from 'react';
import { uploadBlogImage } from '@/actions/blog.actions';
import { FileField, TextField } from '@/components/ui';
import { notify } from '@/lib/toast';
import { adminMediaSrc } from '@/lib/admin-media';

type BlogCoverFieldProps = {
  label: string;
  value: string;
  alt: string;
  width?: number | null;
  height?: number | null;
  onChange: (next: { url: string; alt?: string; width?: number; height?: number }) => void;
  role?: 'cover' | 'inline';
};

export function BlogCoverField({ label, value, alt, width, height, onChange, role = 'cover' }: BlogCoverFieldProps) {
  const [busy, setBusy] = useState(false);
  const [hint, setHint] = useState('حداقل ۱۲۰۰×۶۳۰، JPG/PNG/WebP تا ۴ مگابایت');
  const preview = adminMediaSrc(value);

  return (
    <div className='space-y-4'>
      {preview ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={preview} alt={alt} className='h-28 w-full max-w-sm rounded-xl bg-gray-100 object-cover dark:bg-gray-900' />
      ) : null}
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
          formData.append('alt', alt);
          formData.append('role', role);
          const result = await uploadBlogImage(formData);
          setBusy(false);
          if (!result.ok) {
            setHint(result.message);
            notify.error(result.message);
            return;
          }
          setHint(
            result.width && result.height ? `${result.width}×${result.height}` : 'تصویر ذخیره شد'
          );
          notify.success('تصویر ذخیره شد.');
          onChange({ url: result.url, width: result.width, height: result.height });
        }}
      />
      <TextField label='آدرس تصویر' placeholder='/storage/blog/...' value={value} onChange={(url) => onChange({ url })} />
      <TextField label='متن جایگزین تصویر شاخص' value={alt} onChange={(next) => onChange({ url: value, alt: next })} />
      {width && height ? (
        <p className='text-xs text-gray-500'>
          ابعاد: {width}×{height}
        </p>
      ) : null}
    </div>
  );
}

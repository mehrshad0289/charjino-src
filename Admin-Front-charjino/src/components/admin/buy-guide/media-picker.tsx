'use client';

import { useState } from 'react';
import { uploadBuyGuideMedia } from '@/actions/buy-guide.actions';
import { FileField, TextField } from '@/components/ui';
import { notify } from '@/lib/toast';
import { adminMediaSrc } from '@/lib/admin-media';

type MediaPickerProps = {
  label: string;
  mediaId?: number | null;
  src?: string | null;
  alt?: string | null;
  onChange: (next: { mediaId: number | null; src: string; alt: string }) => void;
};

export function MediaPicker({ label, mediaId, src, alt, onChange }: MediaPickerProps) {
  const [busy, setBusy] = useState(false);
  const [draftAlt, setDraftAlt] = useState(alt ?? '');
  const preview = adminMediaSrc(src ?? '');

  return (
    <div className='space-y-3'>
      {preview ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={preview} alt={alt || ''} className='h-24 w-auto rounded-xl bg-gray-100 object-contain dark:bg-gray-900' />
      ) : null}
      <TextField
        label='متن جایگزین تصویر (الزامی)'
        value={draftAlt}
        onChange={setDraftAlt}
        hint='مثلاً صفحه‌ی Redeem Code در PlayStation Store روی PS5'
      />
      <FileField
        label={label}
        accept='image/jpeg,image/png,image/webp'
        hint={busy ? 'در حال تبدیل به WebP...' : 'JPG یا PNG تا ۶ مگابایت — قبل از آپلود alt را بنویس'}
        previewState={busy ? 'disabled' : 'default'}
        onChange={async (event) => {
          const file = event.target.files?.[0];
          if (!file) return;
          if (draftAlt.trim().length < 3) {
            notify.error('اول متن جایگزین را بنویس.');
            return;
          }
          setBusy(true);
          const formData = new FormData();
          formData.append('file', file);
          formData.append('alt', draftAlt.trim());
          formData.append('kind', 'image');
          const result = await uploadBuyGuideMedia(formData);
          setBusy(false);
          if (!result.ok) {
            notify.error(result.message);
            return;
          }
          notify.success('تصویر WebP ذخیره شد.');
          onChange({ mediaId: result.media.id, src: result.media.src, alt: result.media.alt });
        }}
      />
      {mediaId ? (
        <button type='button' className='text-theme-sm text-error-500' onClick={() => onChange({ mediaId: null, src: '', alt: draftAlt })}>
          حذف تصویر
        </button>
      ) : null}
    </div>
  );
}

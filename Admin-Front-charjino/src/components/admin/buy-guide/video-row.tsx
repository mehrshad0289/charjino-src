'use client';

import { useState } from 'react';
import { FileField, SelectField, TextField } from '@/components/ui';
import { uploadBuyGuideMedia } from '@/actions/buy-guide.actions';
import { notify } from '@/lib/toast';
import { MediaPicker } from './media-picker';

export type VideoDraft = {
  provider: 'aparat' | 'youtube' | 'self';
  url: string;
  title: string;
  lang: 'fa' | 'en';
  duration: string;
  thumbnail_media_id?: number | null;
  thumbnailSrc?: string;
  video_media_id?: number | null;
  thumbnail?: { id?: number; src?: string } | null;
  video?: { id?: number } | null;
};

function providerFromUrl(url: string): VideoDraft['provider'] {
  const host = url.toLowerCase();
  if (host.includes('aparat.com')) return 'aparat';
  if (host.includes('youtu')) return 'youtube';
  return 'self';
}

export function VideoRow({
  value,
  onChange
}: {
  value: VideoDraft;
  onChange: (next: VideoDraft) => void;
}) {
  const [busy, setBusy] = useState(false);

  return (
    <div className='grid gap-3 md:grid-cols-2'>
      <TextField
        label='آدرس ویدیو'
        value={value.url}
        onChange={(url) => onChange({ ...value, url, provider: providerFromUrl(url) })}
      />
      <TextField label='عنوان' value={value.title} onChange={(title) => onChange({ ...value, title })} />
      <SelectField
        label='زبان'
        value={value.lang}
        onChange={(lang) => onChange({ ...value, lang: lang as 'fa' | 'en' })}
        options={[
          { value: 'fa', label: 'فارسی' },
          { value: 'en', label: 'انگلیسی' }
        ]}
      />
      <TextField label='مدت' value={value.duration} onChange={(duration) => onChange({ ...value, duration })} />
      <div className='md:col-span-2'>
        <MediaPicker
          label='تامبنیل'
          mediaId={value.thumbnail_media_id}
          src={value.thumbnailSrc}
          onChange={(next) =>
            onChange({ ...value, thumbnail_media_id: next.mediaId, thumbnailSrc: next.src })
          }
        />
      </div>
      <div className='md:col-span-2'>
        <FileField
          label='آپلود MP4 (اختیاری)'
          accept='video/mp4'
          hint={busy ? 'در حال آپلود...' : 'حداکثر ۵۰ مگابایت — اگر لینک آپارات/یوتیوب دارید لازم نیست'}
          previewState={busy ? 'disabled' : 'default'}
          onChange={async (event) => {
            const file = event.target.files?.[0];
            if (!file) return;
            setBusy(true);
            const formData = new FormData();
            formData.append('file', file);
            formData.append('alt', (value.title || 'ویدیو آموزشی شارژینو').slice(0, 180));
            formData.append('kind', 'video');
            const result = await uploadBuyGuideMedia(formData);
            setBusy(false);
            if (!result.ok) {
              notify.error(result.message);
              return;
            }
            notify.success('ویدیو ذخیره شد.');
            onChange({ ...value, provider: 'self', video_media_id: result.media.id });
          }}
        />
      </div>
    </div>
  );
}

'use client';

import { Button, SelectField, TextField, TextareaField } from '@/components/ui';
import { HomeMediaField } from '@/components/admin/home-media-field';
import {
  isPurchaseGuideEmbed,
  mediaTypeToGuideType,
  type PurchaseGuideItem,
  type PurchaseGuideItemType,
} from '@/lib/purchase-guide';

const TYPE_OPTIONS = [
  { value: 'text', label: 'متن' },
  { value: 'image', label: 'تصویر' },
  { value: 'gif', label: 'گیف' },
  { value: 'video', label: 'ویدیو / لینک' },
];

const EMPTY: Record<PurchaseGuideItemType, PurchaseGuideItem> = {
  text: { type: 'text', title: '', body: '' },
  image: { type: 'image', title: '', src: '', caption: '' },
  gif: { type: 'gif', title: '', src: '', caption: '' },
  video: { type: 'video', title: '', src: '', caption: '' },
};

type PurchaseGuideEditorProps = {
  items: PurchaseGuideItem[];
  onChange: (items: PurchaseGuideItem[]) => void;
};

export function PurchaseGuideEditor({ items, onChange }: PurchaseGuideEditorProps) {
  const slides = items ?? [];

  function add(type: PurchaseGuideItemType) {
    if (slides.length >= 20) return;
    onChange([...slides, { ...EMPTY[type] }]);
  }

  function patch(index: number, next: PurchaseGuideItem) {
    onChange(slides.map((item, i) => (i === index ? next : item)));
  }

  function move(index: number, dir: -1 | 1) {
    const target = index + dir;
    if (target < 0 || target >= slides.length) return;
    const next = [...slides];
    const [row] = next.splice(index, 1);
    next.splice(target, 0, row);
    onChange(next);
  }

  return (
    <div className='space-y-4'>
      <p className='text-theme-xs leading-6 text-gray-500'>
        هر مورد جداگانه در مدال صفحهٔ محصول نشان داده می‌شود؛ خریدار با قبلی/بعدی بین آن‌ها جابه‌جا می‌شود و همه پشت سر هم
        نیستند.
      </p>
      {slides.length === 0 ? (
        <p className='text-theme-sm text-gray-500'>هنوز اسلایدی نیست. متن، تصویر یا ویدیو اضافه کن.</p>
      ) : null}
      {slides.map((item, index) => {
        const media = item.type !== 'text';
        return (
          <article key={`${item.type}-${index}`} className='space-y-3 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
            <div className='flex flex-wrap items-center justify-between gap-2'>
              <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>اسلاید {index + 1}</p>
              <div className='flex flex-wrap gap-2'>
                <Button type='button' size='sm' variant='outline' disabled={index === 0} onClick={() => move(index, -1)}>
                  بالا
                </Button>
                <Button
                  type='button'
                  size='sm'
                  variant='outline'
                  disabled={index === slides.length - 1}
                  onClick={() => move(index, 1)}
                >
                  پایین
                </Button>
                <Button
                  type='button'
                  size='sm'
                  variant='outline'
                  onClick={() => onChange(slides.filter((_, i) => i !== index))}
                >
                  حذف
                </Button>
              </div>
            </div>
            <SelectField
              label='نوع'
              options={TYPE_OPTIONS}
              value={item.type}
              onChange={(value) => {
                const type = (value as PurchaseGuideItemType) || 'text';
                patch(index, {
                  ...EMPTY[type],
                  title: item.title,
                  body: type === 'text' ? item.body : '',
                  src: type === 'text' ? '' : item.src,
                  caption: type === 'text' ? '' : item.caption,
                  type,
                });
              }}
            />
            <TextField
              label='عنوان (اختیاری)'
              value={item.title ?? ''}
              onChange={(title) => patch(index, { ...item, title })}
            />
            {item.type === 'text' ? (
              <TextareaField
                label='متن'
                hint='خط جدید همان‌طور در فروشگاه دیده می‌شود'
                rows={6}
                value={item.body ?? ''}
                onChange={(body) => patch(index, { ...item, body })}
              />
            ) : null}
            {media ? (
              <>
                <HomeMediaField
                  label={item.type === 'video' ? 'ویدیو یا لینک یوتیوب/آپارات' : 'تصویر یا گیف'}
                  value={item.src ?? ''}
                  onChange={(src, mediaType) => {
                    const nextType = isPurchaseGuideEmbed(src)
                      ? 'video'
                      : item.type === 'video'
                        ? 'video'
                        : mediaTypeToGuideType(mediaType, src);
                    patch(index, { ...item, src, type: nextType });
                  }}
                />
                <TextField
                  label='توضیح زیر رسانه (اختیاری)'
                  value={item.caption ?? ''}
                  onChange={(caption) => patch(index, { ...item, caption })}
                />
              </>
            ) : null}
          </article>
        );
      })}
      <div className='flex flex-wrap gap-2'>
        <Button type='button' size='sm' variant='outline' disabled={slides.length >= 20} onClick={() => add('text')}>
          افزودن متن
        </Button>
        <Button type='button' size='sm' variant='outline' disabled={slides.length >= 20} onClick={() => add('image')}>
          افزودن تصویر
        </Button>
        <Button type='button' size='sm' variant='outline' disabled={slides.length >= 20} onClick={() => add('video')}>
          افزودن ویدیو
        </Button>
      </div>
    </div>
  );
}

'use client';

import { useMemo, useState, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import * as Yup from 'yup';
import {
  deleteBuyGuide,
  duplicateBuyGuide,
  listBuyGuideRegions,
  publishBuyGuide,
  resolveBuyGuidePreview,
  unpublishBuyGuide,
  updateBuyGuide
} from '@/actions/buy-guide.actions';
import { Button, SelectField, Switch, TextField, TextareaField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import type { AdminBuyGuide, BuyGuideSectionType } from '@/types/buy-guide';
import { ContentLint } from './content-lint';
import { GuidePreview } from './guide-preview';
import { MediaPicker } from './media-picker';
import { SortableList } from './sortable-list';
import { VideoRow, type VideoDraft } from './video-row';

const TABS: { id: string; label: string }[] = [
  { id: 'info', label: 'کلی' },
  { id: 'before_buy', label: 'قبل از خرید' },
  { id: 'fields', label: 'فیلدها' },
  { id: 'purchase_steps', label: 'مراحل خرید' },
  { id: 'redeem', label: 'فعال‌سازی' },
  { id: 'regions', label: 'ریجن' },
  { id: 'errors', label: 'خطاها' },
  { id: 'faq', label: 'FAQ' },
  { id: 'videos', label: 'ویدیو' },
  { id: 'sources', label: 'منابع' },
  { id: 'seo', label: 'سئو' }
];

type Note = { text: string; tone: 'info' | 'warning' };
type FieldRow = {
  name: string;
  label: string;
  help: string;
  media_id?: number | null;
  mediaSrc?: string;
  media?: { id?: number; src?: string; alt?: string } | null;
};
type Step = {
  text: string;
  media_id?: number | null;
  mediaSrc?: string;
  alt?: string;
  media?: { id?: number; src?: string; alt?: string } | null;
};
type Tab = { key: string; label: string; steps: Step[] };
type Region = { code: string; label: string; compat: string; product_handle?: string };
type Pair = { error?: string; fix?: string; q?: string; a?: string; url?: string; label?: string };
type EditorValues = {
  title: string;
  summary: string;
  intro: string;
  lastReviewedAt: string;
  seoTitle: string;
  seoDescription: string;
  dupCategory: string;
  dupSubcategory: string;
  inherit: Record<BuyGuideSectionType, boolean>;
  notes: Note[];
  fields: FieldRow[];
  steps: Step[];
  tabs: Tab[];
  regions: Region[];
  errors: Pair[];
  faq: Pair[];
  videos: VideoDraft[];
  sources: Pair[];
};

function withMedia<T extends { media?: { id?: number; src?: string; alt?: string } | null; media_id?: number | null; mediaSrc?: string; alt?: string }>(
  item: T
): T {
  const media = item.media;
  return {
    ...item,
    media_id: item.media_id ?? media?.id ?? null,
    mediaSrc: item.mediaSrc ?? media?.src ?? '',
    alt: item.alt ?? media?.alt ?? ''
  };
}

function withVideo(item: VideoDraft & { thumbnail?: { id?: number; src?: string } | null; video?: { id?: number } | null }): VideoDraft {
  return {
    ...item,
    thumbnail_media_id: item.thumbnail_media_id ?? item.thumbnail?.id ?? null,
    thumbnailSrc: item.thumbnailSrc ?? item.thumbnail?.src ?? '',
    video_media_id: item.video_media_id ?? item.video?.id ?? null
  };
}

function sectionData(guide: AdminBuyGuide, type: BuyGuideSectionType) {
  return guide.sections.find((item) => item.type === type);
}

function asArray<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

export function BuyGuideEditorScreen({ guide }: { guide: AdminBuyGuide }) {
  const router = useRouter();
  const [, setMessage] = useToastMessage();
  const [tab, setTab] = useState('info');
  const [busy, setBusy] = useState<string | null>(null);
  const [preview, setPreview] = useState<Record<string, unknown> | null>(null);
  const lower = guide.scopeType !== 'category';

  const initial = useMemo(() => {
    const before = sectionData(guide, 'before_buy');
    const fields = sectionData(guide, 'fields');
    const steps = sectionData(guide, 'purchase_steps');
    const redeem = sectionData(guide, 'redeem');
    const regions = sectionData(guide, 'regions');
    const errors = sectionData(guide, 'errors');
    const faq = sectionData(guide, 'faq');
    const videos = sectionData(guide, 'videos');
    const sources = sectionData(guide, 'sources');
    const inheritOf = (type: BuyGuideSectionType, fallback: boolean) =>
      sectionData(guide, type)?.inherit ?? fallback;
    return {
      title: guide.title ?? '',
      summary: guide.summary ?? '',
      intro: guide.intro ?? '',
      lastReviewedAt: guide.lastReviewedAt ?? '',
      seoTitle: guide.seoTitle ?? '',
      seoDescription: guide.seoDescription ?? '',
      dupCategory: guide.category,
      dupSubcategory: '',
      inherit: {
        before_buy: inheritOf('before_buy', lower),
        fields: inheritOf('fields', lower),
        purchase_steps: inheritOf('purchase_steps', lower),
        redeem: inheritOf('redeem', lower),
        regions: inheritOf('regions', lower),
        errors: inheritOf('errors', lower),
        faq: inheritOf('faq', lower),
        videos: inheritOf('videos', lower),
        sources: inheritOf('sources', lower)
      },
      notes: asArray<Note>(before?.data?.notes ?? before?.notes),
      fields: asArray<FieldRow>(fields?.data?.items ?? fields?.items).map(withMedia),
      steps: asArray<Step>(steps?.data?.steps ?? steps?.steps).map(withMedia),
      tabs: asArray<Tab>(redeem?.data?.tabs ?? redeem?.tabs).map((tab) => ({
        ...tab,
        steps: asArray<Step>(tab.steps).map(withMedia)
      })),
      regions: asArray<Region>(regions?.data?.rows ?? regions?.rows),
      errors: asArray<Pair>(errors?.data?.items ?? errors?.items),
      faq: asArray<Pair>(faq?.data?.items ?? faq?.items),
      videos: asArray<VideoDraft>(videos?.data?.items ?? videos?.items).map(withVideo),
      sources: asArray<Pair>(sources?.data?.items ?? sources?.items)
    };
  }, [guide, lower]);

  const form = useFormik({
    enableReinitialize: true,
    initialValues: initial,
    validationSchema: Yup.object({
      title: Yup.string().max(180),
      summary: Yup.string().max(2000),
      intro: Yup.string().max(20000)
    }),
    onSubmit: async (values) => {
      setBusy('save');
      const result = await updateBuyGuide(guide.id, packPayload(values, lower));
      setBusy(null);
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        return;
      }
      setMessage({ variant: 'success', text: 'راهنما ذخیره شد.' });
      router.refresh();
    }
  });

  const lintSource = `${form.values.title}\n${form.values.summary}\n${form.values.intro}\n${JSON.stringify(form.values)}`;

  async function togglePublish() {
    setBusy('publish');
    const result = guide.status === 'published' ? await unpublishBuyGuide(guide.id) : await publishBuyGuide(guide.id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: guide.status === 'published' ? 'از انتشار خارج شد.' : 'منتشر شد.' });
    router.refresh();
  }

  async function fillRegions() {
    setBusy('regions');
    const result = await listBuyGuideRegions(guide.category, guide.subcategory ?? undefined);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    void form.setFieldValue('regions', result.items);
    setMessage({ variant: 'success', text: 'جدول ریجن از محصولات پر شد.' });
  }

  async function showPreview() {
    setBusy('preview');
    const regions = await listBuyGuideRegions(guide.category, guide.subcategory ?? undefined);
    const productId = regions.ok ? regions.items.find((item) => item.product_id)?.product_id : guide.productId;
    if (!productId) {
      setBusy(null);
      setMessage({ variant: 'error', text: 'محصول نمونه‌ای برای پیش‌نمایش پیدا نشد.' });
      return;
    }
    const result = await resolveBuyGuidePreview(productId);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setPreview(result.data);
  }

  async function duplicate() {
    setBusy('dup');
    const result = await duplicateBuyGuide(guide.id, {
      scopeType: form.values.dupSubcategory ? 'subcategory' : 'category',
      category: form.values.dupCategory,
      subcategory: form.values.dupSubcategory || undefined
    });
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    router.push(`/content/buy-guides/${result.item.id}`);
  }

  async function remove() {
    if (!window.confirm('این راهنما حذف شود؟')) return;
    setBusy('delete');
    const result = await deleteBuyGuide(guide.id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    router.push('/content/buy-guides');
  }

  function inheritSwitch(type: BuyGuideSectionType) {
    if (!lower) return null;
    return (
      <Switch
        label='ارث‌بری از والد'
        checked={form.values.inherit[type]}
        onChange={(checked) => void form.setFieldValue(`inherit.${type}`, checked)}
      />
    );
  }

  return (
    <form className='space-y-6' onSubmit={form.handleSubmit}>
      <div className='flex flex-wrap gap-2'>
        {TABS.map((item) => (
          <button
            key={item.id}
            type='button'
            className={`rounded-full px-3 py-1 text-theme-sm ${tab === item.id ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 dark:bg-white/5'}`}
            onClick={() => setTab(item.id)}
          >
            {item.label}
          </button>
        ))}
      </div>
      <ContentLint text={lintSource} />

      {tab === 'info' ? (
        <div className='grid gap-4'>
          <p className='text-theme-sm text-gray-500'>
            سطح: {guide.scopeType} · {guide.category}
            {guide.subcategory ? ` / ${guide.subcategory}` : ''}
          </p>
          <TextField label='عنوان' value={form.values.title} onChange={(value) => void form.setFieldValue('title', value)} />
          <TextareaField label='خلاصه' value={form.values.summary} onChange={(value) => void form.setFieldValue('summary', value)} />
          <TextareaField
            label='مقدمه (HTML محدود: p / h3 / ul / a)'
            value={form.values.intro}
            onChange={(value) => void form.setFieldValue('intro', value)}
          />
          <TextField
            label='آخرین بازبینی (YYYY-MM-DD)'
            value={form.values.lastReviewedAt}
            onChange={(value) => void form.setFieldValue('lastReviewedAt', value)}
          />
        </div>
      ) : null}

      {tab === 'before_buy' ? (
        <div className='space-y-3'>
          {inheritSwitch('before_buy')}
          <div className={form.values.inherit.before_buy && lower ? 'pointer-events-none opacity-50' : ''}>
          <SortableList
            items={form.values.notes}
            onChange={(items) => void form.setFieldValue('notes', items)}
            addLabel='افزودن نکته'
            create={() => ({ text: '', tone: 'info' as const })}
            renderItem={(item, index) => (
              <div className='grid gap-3 md:grid-cols-[1fr_8rem]'>
                <TextareaField
                  label='متن'
                  value={item.text}
                  onChange={(text) => {
                    const next = [...form.values.notes];
                    next[index] = { ...item, text };
                    void form.setFieldValue('notes', next);
                  }}
                />
                <SelectField
                  label='لحن'
                  value={item.tone}
                  options={[
                    { value: 'info', label: 'اطلاع' },
                    { value: 'warning', label: 'هشدار' }
                  ]}
                  onChange={(tone) => {
                    const next = [...form.values.notes];
                    next[index] = { ...item, tone: tone as Note['tone'] };
                    void form.setFieldValue('notes', next);
                  }}
                />
              </div>
            )}
          />
          </div>
        </div>
      ) : null}

      {tab === 'fields' ? (
        <div className='space-y-3'>
          {inheritSwitch('fields')}
          <div className={form.values.inherit.fields && lower ? 'pointer-events-none opacity-50' : ''}>
          <SortableList<FieldRow>
            items={form.values.fields}
            onChange={(items) => void form.setFieldValue('fields', items)}
            addLabel='افزودن فیلد'
            create={() => ({ name: '', label: '', help: '' })}
            renderItem={(item, index) => (
              <div className='grid gap-3'>
                <TextField
                  label='name'
                  value={item.name}
                  onChange={(name) => {
                    const next = [...form.values.fields];
                    next[index] = { ...item, name };
                    void form.setFieldValue('fields', next);
                  }}
                />
                <TextField
                  label='برچسب'
                  value={item.label}
                  onChange={(label) => {
                    const next = [...form.values.fields];
                    next[index] = { ...item, label };
                    void form.setFieldValue('fields', next);
                  }}
                />
                <TextareaField
                  label='راهنما'
                  value={item.help}
                  onChange={(help) => {
                    const next = [...form.values.fields];
                    next[index] = { ...item, help };
                    void form.setFieldValue('fields', next);
                  }}
                />
                <MediaPicker
                  label='تصویر فیلد'
                  mediaId={item.media_id}
                  src={item.mediaSrc}
                  onChange={(media) => {
                    const next = [...form.values.fields];
                    next[index] = { ...item, media_id: media.mediaId, mediaSrc: media.src };
                    void form.setFieldValue('fields', next);
                  }}
                />
              </div>
            )}
          />
          </div>
        </div>
      ) : null}

      {tab === 'purchase_steps' ? (
        <div className='space-y-3'>
          {inheritSwitch('purchase_steps')}
          <div className={form.values.inherit.purchase_steps && lower ? 'pointer-events-none opacity-50' : ''}>
          <StepList
            items={form.values.steps}
            onChange={(items) => void form.setFieldValue('steps', items)}
          />
          </div>
        </div>
      ) : null}

      {tab === 'redeem' ? (
        <div className='space-y-3'>
          {inheritSwitch('redeem')}
          <div className={form.values.inherit.redeem && lower ? 'pointer-events-none opacity-50' : ''}>
          <SortableList
            items={form.values.tabs}
            onChange={(items) => void form.setFieldValue('tabs', items)}
            addLabel='افزودن دستگاه'
            create={() => ({ key: '', label: '', steps: [{ text: '' }] })}
            renderItem={(item, index) => (
              <div className='grid gap-3'>
                <TextField
                  label='کلید'
                  value={item.key}
                  onChange={(key) => {
                    const next = [...form.values.tabs];
                    next[index] = { ...item, key };
                    void form.setFieldValue('tabs', next);
                  }}
                />
                <TextField
                  label='عنوان تب'
                  value={item.label}
                  onChange={(label) => {
                    const next = [...form.values.tabs];
                    next[index] = { ...item, label };
                    void form.setFieldValue('tabs', next);
                  }}
                />
                <StepList
                  items={item.steps ?? []}
                  onChange={(steps) => {
                    const next = [...form.values.tabs];
                    next[index] = { ...item, steps };
                    void form.setFieldValue('tabs', next);
                  }}
                />
              </div>
            )}
          />
          </div>
        </div>
      ) : null}

      {tab === 'regions' ? (
        <div className='space-y-3'>
          {inheritSwitch('regions')}
          <div className={form.values.inherit.regions && lower ? 'pointer-events-none opacity-50' : ''}>
          <Button type='button' size='sm' variant='outline' onClick={() => void fillRegions()} disabled={busy === 'regions'}>
            پرکردن خودکار از محصولات این زیردسته
          </Button>
          <SortableList
            items={form.values.regions}
            onChange={(items) => void form.setFieldValue('regions', items)}
            addLabel='افزودن ریجن'
            create={() => ({ code: '', label: '', compat: '', product_handle: '' })}
            renderItem={(item, index) => (
              <div className='grid gap-3 md:grid-cols-2'>
                <TextField
                  label='کد'
                  value={item.code}
                  onChange={(code) => patch(form, 'regions', index, { ...item, code })}
                />
                <TextField
                  label='عنوان'
                  value={item.label}
                  onChange={(label) => patch(form, 'regions', index, { ...item, label })}
                />
                <TextField
                  label='سازگاری'
                  value={item.compat}
                  onChange={(compat) => patch(form, 'regions', index, { ...item, compat })}
                />
                <TextField
                  label='handle محصول'
                  value={item.product_handle ?? ''}
                  onChange={(product_handle) => patch(form, 'regions', index, { ...item, product_handle })}
                />
              </div>
            )}
          />
          </div>
        </div>
      ) : null}

      {tab === 'errors' ? (
        <PairList
          inherit={inheritSwitch('errors')}
          items={form.values.errors}
          left='error'
          right='fix'
          leftLabel='خطا'
          rightLabel='راه‌حل'
          dim={Boolean(form.values.inherit.errors && lower)}
          onChange={(items) => void form.setFieldValue('errors', items)}
        />
      ) : null}

      {tab === 'faq' ? (
        <PairList
          inherit={inheritSwitch('faq')}
          items={form.values.faq}
          left='q'
          right='a'
          leftLabel='سؤال'
          rightLabel='جواب'
          dim={Boolean(form.values.inherit.faq && lower)}
          onChange={(items) => void form.setFieldValue('faq', items)}
        />
      ) : null}

      {tab === 'videos' ? (
        <div className='space-y-3'>
          {inheritSwitch('videos')}
          <div className={form.values.inherit.videos && lower ? 'pointer-events-none opacity-50' : ''}>
          <SortableList
            items={form.values.videos}
            onChange={(items) => void form.setFieldValue('videos', items)}
            addLabel='افزودن ویدیو'
            create={() => ({ provider: 'aparat' as const, url: '', title: '', lang: 'fa' as const, duration: '' })}
            renderItem={(item, index) => (
              <VideoRow
                value={item}
                onChange={(next) => {
                  const copy = [...form.values.videos];
                  copy[index] = next;
                  void form.setFieldValue('videos', copy);
                }}
              />
            )}
          />
          </div>
        </div>
      ) : null}

      {tab === 'sources' ? (
        <PairList
          inherit={inheritSwitch('sources')}
          items={form.values.sources}
          left='url'
          right='label'
          leftLabel='آدرس'
          rightLabel='عنوان'
          dim={Boolean(form.values.inherit.sources && lower)}
          onChange={(items) => void form.setFieldValue('sources', items)}
        />
      ) : null}

      {tab === 'seo' ? (
        <div className='grid gap-4'>
          <TextField label='عنوان سئو' value={form.values.seoTitle} onChange={(value) => void form.setFieldValue('seoTitle', value)} />
          <TextareaField
            label='توضیح سئو'
            value={form.values.seoDescription}
            onChange={(value) => void form.setFieldValue('seoDescription', value)}
          />
          <div className='grid gap-3 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
            <p className='text-theme-sm font-medium'>کپی به زیردسته دیگر</p>
            <TextField label='دسته مقصد' value={form.values.dupCategory} onChange={(value) => void form.setFieldValue('dupCategory', value)} />
            <TextField
              label='زیردسته مقصد'
              value={form.values.dupSubcategory}
              onChange={(value) => void form.setFieldValue('dupSubcategory', value)}
            />
            <Button type='button' variant='outline' onClick={() => void duplicate()} disabled={busy === 'dup'}>
              ساخت کپی
            </Button>
          </div>
        </div>
      ) : null}

      <div className='grid gap-4 lg:grid-cols-[1fr_18rem]'>
        <div className='flex flex-wrap gap-2'>
          <Button type='submit' disabled={busy === 'save'}>
            ذخیره
          </Button>
          <Button type='button' variant='outline' onClick={() => void togglePublish()} disabled={busy === 'publish'}>
            {guide.status === 'published' ? 'خروج از انتشار' : 'انتشار'}
          </Button>
          <Button type='button' variant='outline' onClick={() => void showPreview()} disabled={busy === 'preview'}>
            پیش‌نمایش روی محصول
          </Button>
          <Button type='button' variant='outline' onClick={() => void remove()} disabled={busy === 'delete'}>
            حذف
          </Button>
        </div>
        <GuidePreview data={preview} />
      </div>
    </form>
  );
}

function StepList({ items, onChange }: { items: Step[]; onChange: (items: Step[]) => void }) {
  return (
    <SortableList
      items={items}
      onChange={onChange}
      addLabel='افزودن مرحله'
      create={(): Step => ({ text: '' })}
      renderItem={(item, index) => (
        <div className='grid gap-3'>
          <TextareaField
            label={`مرحله ${index + 1}`}
            value={item.text}
            onChange={(text) => {
              const next = [...items];
              next[index] = { ...item, text };
              onChange(next);
            }}
          />
          <MediaPicker
            label='تصویر مرحله'
            mediaId={item.media_id}
            src={item.mediaSrc}
            alt={item.alt}
            onChange={(media) => {
              const next = [...items];
              next[index] = { ...item, media_id: media.mediaId, mediaSrc: media.src, alt: media.alt };
              onChange(next);
            }}
          />
        </div>
      )}
    />
  );
}

function PairList({
  inherit,
  items,
  onChange,
  left,
  right,
  leftLabel,
  rightLabel,
  dim
}: {
  inherit: ReactNode;
  items: Pair[];
  onChange: (items: Pair[]) => void;
  left: keyof Pair;
  right: keyof Pair;
  leftLabel: string;
  rightLabel: string;
  dim?: boolean;
}) {
  return (
    <div className='space-y-3'>
      {inherit}
      <div className={dim ? 'pointer-events-none opacity-50' : ''}>
      <SortableList
        items={items}
        onChange={onChange}
        addLabel='افزودن مورد'
        create={() => ({ [left]: '', [right]: '' })}
        renderItem={(item, index) => (
          <div className='grid gap-3'>
            <TextField
              label={leftLabel}
              value={String(item[left] ?? '')}
              onChange={(value) => {
                const next = [...items];
                next[index] = { ...item, [left]: value };
                onChange(next);
              }}
            />
            <TextareaField
              label={rightLabel}
              value={String(item[right] ?? '')}
              onChange={(value) => {
                const next = [...items];
                next[index] = { ...item, [right]: value };
                onChange(next);
              }}
            />
          </div>
        )}
      />
      </div>
    </div>
  );
}

function patch(
  form: { values: Record<string, unknown>; setFieldValue: (field: string, value: unknown) => void },
  field: string,
  index: number,
  next: unknown
) {
  const list = [...((form.values[field] as unknown[]) ?? [])];
  list[index] = next;
  void form.setFieldValue(field, list);
}

function packPayload(values: EditorValues, lower: boolean) {
  const v = values;
  const section = (type: BuyGuideSectionType, data: Record<string, unknown>) => ({
    type,
    inherit: lower ? Boolean(v.inherit[type]) : false,
    data
  });
  return {
    title: v.title,
    summary: v.summary,
    intro: v.intro,
    lastReviewedAt: v.lastReviewedAt || null,
    seoTitle: v.seoTitle,
    seoDescription: v.seoDescription,
    sections: [
      section('before_buy', { notes: v.notes }),
      section('fields', {
        items: v.fields.map(({ mediaSrc: _s, media: _m, ...row }) => row)
      }),
      section('purchase_steps', {
        steps: v.steps.map(({ mediaSrc: _s, media: _m, ...row }) => row)
      }),
      section('redeem', {
        tabs: v.tabs.map((tab) => ({
          key: tab.key,
          label: tab.label,
          steps: (tab.steps ?? []).map(({ mediaSrc: _s, media: _m, ...row }) => row)
        }))
      }),
      section('regions', { rows: v.regions }),
      section('errors', { items: v.errors }),
      section('faq', { items: v.faq }),
      section('videos', {
        items: v.videos.map(({ thumbnailSrc: _t, thumbnail: _th, video: _v, ...row }) => row)
      }),
      section('sources', { items: v.sources })
    ]
  };
}

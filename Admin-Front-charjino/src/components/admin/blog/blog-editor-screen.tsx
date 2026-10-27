'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import * as Yup from 'yup';
import DOMPurify from 'isomorphic-dompurify';
import {
  checkBlogSlug,
  createBlogCategory,
  createBlogPost,
  createBlogTag,
  restoreBlogRevision,
  updateBlogPost
} from '@/actions/blog.actions';
import { BlogCoverField } from '@/components/admin/blog/blog-cover-field';
import { formatFaqField, parseFaqField } from '@/components/admin/blog/blog-faq';
import { BlogSeoChecklist } from '@/components/admin/blog/blog-seo-checklist';
import { latinizeSlug, slugifyTitle } from '@/components/admin/blog/blog-slug';
import { RichTextField } from '@/components/admin/blog/rich-text-field';
import { Alert, Button, Checkbox, DateField, SelectField, Tabs, TextField, TextareaField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { adminMediaSrc, shopPath } from '@/lib/admin-media';
import type { AdminBlogCategory, AdminBlogPost, AdminBlogTag } from '@/types/blog';

const TABS = [
  { id: 'info', label: 'اطلاعات' },
  { id: 'content', label: 'متن' },
  { id: 'seo', label: 'سئو' },
  { id: 'preview', label: 'پیش‌نمایش' }
];

const PREVIEW_HTML_CLASS =
  'space-y-4 text-sm leading-8 text-gray-800 dark:text-gray-200 [&_blockquote]:border-s-2 [&_blockquote]:border-brand-500 [&_blockquote]:ps-4 [&_h2]:scroll-mt-24 [&_h2]:text-xl [&_h2]:font-bold [&_h3]:scroll-mt-24 [&_h3]:text-lg [&_h3]:font-bold [&_h4]:font-bold [&_li]:ms-5 [&_ol]:list-decimal [&_table]:w-full [&_table]:text-sm [&_td]:border [&_td]:border-gray-200 [&_td]:p-2 [&_th]:border [&_th]:border-gray-200 [&_th]:p-2 [&_ul]:list-disc';

const schema = Yup.object({
  title: Yup.string().trim().required('عنوان لازم است').min(10, 'حداقل ۱۰ کاراکتر').max(100, 'حداکثر ۱۰۰ کاراکتر'),
  slug: Yup.string().max(80, 'حداکثر ۸۰ کاراکتر'),
  excerpt: Yup.string().max(500, 'حداکثر ۵۰۰ کاراکتر'),
  content: Yup.string().trim().required('متن مطلب لازم است').min(40, 'متن خیلی کوتاه است'),
  cover: Yup.string().max(2048),
  coverAlt: Yup.string().max(180),
  categoryId: Yup.string(),
  metaTitle: Yup.string().max(80),
  metaDescription: Yup.string().max(180),
  canonicalUrl: Yup.string().max(2048),
  focusKeyword: Yup.string().max(80)
});

type AuthorOption = { id: string; name: string; email?: string | null };

type BlogEditorScreenProps = {
  categories: AdminBlogCategory[];
  tags: AdminBlogTag[];
  authors?: AuthorOption[];
  post?: AdminBlogPost;
};

function clockLabel(date = new Date()) {
  return new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit' }).format(date);
}

export function BlogEditorScreen({ categories, tags, authors = [], post }: BlogEditorScreenProps) {
  const router = useRouter();
  const [, setMessage] = useToastMessage();
  const [tab, setTab] = useState('info');
  const [savedId, setSavedId] = useState(post?.id);
  const [previewToken, setPreviewToken] = useState(post?.previewToken ?? '');
  const [slugHint, setSlugHint] = useState('');
  const [autoSavedAt, setAutoSavedAt] = useState('');
  const [catList, setCatList] = useState(categories);
  const [tagList, setTagList] = useState(tags);
  const [newCategory, setNewCategory] = useState('');
  const [newTag, setNewTag] = useState('');
  const [creatingCategory, setCreatingCategory] = useState(false);
  const [creatingTag, setCreatingTag] = useState(false);
  const [slugLocked, setSlugLocked] = useState(Boolean(post?.slug));
  const savingRef = useRef(false);
  const dirtyRef = useRef(false);
  const persistRef = useRef<(silent: boolean) => Promise<AdminBlogPost | null>>(async () => null);

  const formik = useFormik({
    enableReinitialize: true,
    initialValues: {
      title: post?.title ?? '',
      slug: post?.slug ?? '',
      excerpt: post?.excerpt ?? '',
      content: post?.content ?? '',
      cover: post?.cover ?? '',
      coverAlt: post?.coverAlt ?? '',
      coverWidth: post?.coverWidth ?? 0,
      coverHeight: post?.coverHeight ?? 0,
      categoryId: post?.categoryId ? String(post.categoryId) : '',
      userId: post?.userId ?? authors[0]?.id ?? '',
      status: post?.status ?? 'draft',
      publishedAt: post?.publishedAt ?? '',
      metaTitle: post?.metaTitle ?? '',
      metaDescription: post?.metaDescription ?? '',
      canonicalUrl: post?.canonicalUrl ?? '',
      noindex: Boolean(post?.noindex),
      focusKeyword: post?.focusKeyword ?? '',
      ogTitle: post?.ogTitle ?? '',
      ogDescription: post?.ogDescription ?? '',
      ogImage: post?.ogImage ?? '',
      twitterTitle: post?.twitterTitle ?? '',
      twitterDescription: post?.twitterDescription ?? '',
      twitterImage: post?.twitterImage ?? '',
      secondaryKeywords: (post?.secondaryKeywords ?? []).join('، '),
      tagIds: (post?.tagIds ?? []).map(String),
      productIds: (post?.productIds ?? []).join(','),
      faq: formatFaqField(post?.faq)
    },
    validationSchema: schema,
    onSubmit: async (values, helpers) => {
      await submitDraft(values);
      helpers.setSubmitting(false);
    }
  });

  async function persist(
    values: typeof formik.values,
    options: { silent?: boolean; publish?: boolean; asDraft?: boolean } = {}
  ) {
    if (savingRef.current) return null;
    savingRef.current = true;
    const wasNew = !savedId;
    const publishing = Boolean(options.publish);
    if (publishing && !values.categoryId) {
      savingRef.current = false;
      if (!options.silent) setMessage({ variant: 'error', text: 'برای انتشار، دسته لازم است.' });
      return null;
    }
    const payload: Record<string, unknown> = {
      title: values.title.trim(),
      slug: values.slug.trim() || undefined,
      excerpt: values.excerpt.trim() || null,
      content: values.content,
      cover: values.cover.trim() || null,
      coverAlt: values.coverAlt.trim() || null,
      coverWidth: values.coverWidth || undefined,
      coverHeight: values.coverHeight || undefined,
      categoryId: values.categoryId ? Number(values.categoryId) : null,
      userId: values.userId || undefined,
      autosave: options.silent || undefined,
      publishedAt: values.publishedAt
        ? values.publishedAt.length <= 10
          ? `${values.publishedAt}${values.status === 'scheduled' ? 'T23:59:00' : 'T09:00:00'}`
          : values.publishedAt
        : undefined,
      metaTitle: values.metaTitle.trim() || null,
      metaDescription: values.metaDescription.trim() || null,
      canonicalUrl: values.canonicalUrl.trim() || null,
      noindex: values.noindex,
      focusKeyword: values.focusKeyword.trim() || null,
      ogTitle: values.ogTitle.trim() || null,
      ogDescription: values.ogDescription.trim() || null,
      ogImage: values.ogImage.trim() || null,
      twitterTitle: values.twitterTitle.trim() || null,
      twitterDescription: values.twitterDescription.trim() || null,
      twitterImage: values.twitterImage.trim() || null,
      secondaryKeywords: values.secondaryKeywords,
      tagIds: values.tagIds.map(Number).filter(Boolean),
      productIds: values.productIds
        .split(',')
        .map((id) => Number(id.trim()))
        .filter(Boolean),
      faq: parseFaqField(values.faq)
    };
    if (publishing) {
      payload.publish = true;
      payload.status = 'published';
    } else if (options.asDraft) {
      payload.status = 'draft';
      payload.publish = false;
    }
    const result = savedId ? await updateBlogPost(savedId, payload) : await createBlogPost(payload);
    savingRef.current = false;
    if (!result.ok) {
      if (!options.silent) setMessage({ variant: 'error', text: result.message });
      return null;
    }
    setSavedId(result.item.id);
    if (result.item.previewToken) setPreviewToken(result.item.previewToken);
    formik.resetForm({ values });
    return { item: result.item, wasNew };
  }

  async function submitDraft(values: typeof formik.values) {
    const result = await persist(values, { asDraft: true });
    if (!result) return;
    setMessage({ variant: 'success', text: result.wasNew ? 'مطلب ایجاد شد.' : 'پیش‌نویس ذخیره شد.' });
    router.push(`/content/blog/${result.item.id}`);
    router.refresh();
  }

  async function submitPublish() {
    const errors = await formik.validateForm();
    if (Object.keys(errors).length > 0) {
      setMessage({ variant: 'error', text: String(Object.values(errors)[0]) });
      return;
    }
    if (!formik.values.categoryId) {
      setMessage({ variant: 'error', text: 'برای انتشار، دسته لازم است.' });
      setTab('info');
      return;
    }
    const result = await persist(formik.values, { publish: true });
    if (!result) return;
    setMessage({ variant: 'success', text: 'مطلب منتشر شد.' });
    router.push(`/content/blog/${result.item.id}`);
    router.refresh();
  }

  dirtyRef.current = formik.dirty;
  persistRef.current = (silent) => {
    if (formik.values.title.trim().length < 3) return Promise.resolve(null);
    return persist(formik.values, { silent: true }).then((result) => {
      if (result && silent) setAutoSavedAt(clockLabel());
      return result?.item ?? null;
    });
  };

  useEffect(() => {
    const timer = window.setInterval(() => {
      if (!dirtyRef.current) return;
      void persistRef.current(true);
    }, 30000);
    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    function warn(event: BeforeUnloadEvent) {
      if (!formik.dirty) return;
      event.preventDefault();
      event.returnValue = '';
    }
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [formik.dirty]);

  useEffect(() => {
    if (slugLocked) return;
    const next = slugifyTitle(formik.values.title);
    if (next !== formik.values.slug) void formik.setFieldValue('slug', next, false);
  }, [formik.values.title, slugLocked]);

  useEffect(() => {
    const slug = formik.values.slug.trim();
    if (slug.length < 2) return;
    const handle = window.setTimeout(() => {
      void checkBlogSlug(slug, savedId).then((result) => {
        if (result.ok) setSlugHint(result.available ? 'نامک آزاد است.' : 'این نامک گرفته شده؛ با ذخیره یکتا می‌شود.');
      });
    }, 400);
    return () => window.clearTimeout(handle);
  }, [formik.values.slug, savedId]);

  async function addCategory() {
    const title = newCategory.trim();
    if (title.length < 2) return;
    setCreatingCategory(true);
    const result = await createBlogCategory({ title, isActive: true });
    setCreatingCategory(false);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setCatList((current) => [...current, result.item]);
    void formik.setFieldValue('categoryId', String(result.item.id));
    setNewCategory('');
    setMessage({ variant: 'success', text: 'دسته ساخته شد.' });
  }

  async function addTag() {
    const title = newTag.trim();
    if (title.length < 2) return;
    const existing = tagList.find((item) => item.title === title);
    if (existing) {
      void formik.setFieldValue('tagIds', Array.from(new Set([...formik.values.tagIds, String(existing.id)])));
      setNewTag('');
      return;
    }
    setCreatingTag(true);
    const result = await createBlogTag({ title });
    setCreatingTag(false);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setTagList((current) => [...current, result.item]);
    void formik.setFieldValue('tagIds', Array.from(new Set([...formik.values.tagIds, String(result.item.id)])));
    setNewTag('');
    setMessage({ variant: 'success', text: 'تگ ساخته شد.' });
  }

  const firstError = Object.values(formik.errors)[0];
  const previewHtml = DOMPurify.sanitize(formik.values.content || '<p>متنی وارد نشده.</p>');
  const liveSlug = formik.values.slug.trim() || slugifyTitle(formik.values.title) || '...';
  const previewHref = previewToken ? shopPath(`/blog/preview/${previewToken}`) : '';

  return (
    <form className='grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]' onSubmit={formik.handleSubmit}>
      <div className='space-y-6'>
        <Tabs items={TABS} value={tab} onChange={setTab} />
        {formik.submitCount > 0 && firstError ? <Alert variant='error' title='بررسی فرم' message={String(firstError)} /> : null}
        {autoSavedAt ? <p className='text-xs text-gray-500'>ذخیره خودکار در ساعت {autoSavedAt}</p> : null}

        {tab === 'info' ? (
          <div className='grid gap-4 md:grid-cols-2'>
            <TextField
              label='عنوان'
              value={formik.values.title}
              onChange={(value) => void formik.setFieldValue('title', value)}
              hint={formik.touched.title ? formik.errors.title : undefined}
            />
            <div className='space-y-2'>
              <TextField
                label='نامک'
                value={formik.values.slug}
                onChange={(value) => {
                  setSlugLocked(true);
                  void formik.setFieldValue('slug', value);
                }}
                hint={slugHint || 'با تایپ عنوان پر می‌شود. تغییر مطلب منتشرشده ریدایرکت ۳۰۱ می‌سازد.'}
              />
              <p className='text-xs text-gray-500' dir='ltr'>
                charjino.ir/blog/{liveSlug}
              </p>
              <Button
                type='button'
                size='sm'
                variant='outline'
                onClick={() => {
                  setSlugLocked(true);
                  void formik.setFieldValue('slug', latinizeSlug(formik.values.title || formik.values.slug));
                }}
              >
                تبدیل به حروف لاتین
              </Button>
            </div>
            <div className='space-y-2'>
              <SelectField
                label='دسته اصلی'
                placeholder='انتخاب دسته'
                value={formik.values.categoryId}
                onChange={(value) => void formik.setFieldValue('categoryId', value)}
                options={catList.map((category) => ({
                  value: String(category.id),
                  label: category.isActive ? category.title : `${category.title} (غیرفعال)`
                }))}
              />
              <div
                className='flex gap-2'
                onKeyDown={(event) => {
                  if (event.key !== 'Enter') return;
                  event.preventDefault();
                  void addCategory();
                }}
              >
                <TextField
                  label='دسته جدید'
                  value={newCategory}
                  onChange={setNewCategory}
                  placeholder='ساخت سریع دسته'
                />
                <div className='flex items-end'>
                  <Button type='button' size='sm' loading={creatingCategory} onClick={() => void addCategory()}>
                    +
                  </Button>
                </div>
              </div>
              <p className='text-xs text-gray-500'>دسته برای پیش‌نویس اختیاری است و فقط برای انتشار لازم است.</p>
            </div>
            <SelectField
              label='نویسنده'
              placeholder='انتخاب نویسنده'
              value={formik.values.userId}
              onChange={(value) => void formik.setFieldValue('userId', value)}
              options={authors.map((author) => ({
                value: author.id,
                label: author.name || author.email || author.id
              }))}
            />
            <SelectField
              label='وضعیت'
              value={formik.values.status}
              onChange={(value) => void formik.setFieldValue('status', value)}
              options={[
                { value: 'draft', label: 'پیش‌نویس' },
                { value: 'published', label: 'منتشر' },
                { value: 'scheduled', label: 'زمان‌بندی' }
              ]}
            />
            <DateField
              label='تاریخ انتشار'
              value={formik.values.publishedAt}
              onChange={(value) => void formik.setFieldValue('publishedAt', value)}
              maxYear={1408}
            />
            <div
              className='space-y-2 md:col-span-2'
              onKeyDown={(event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                void addTag();
              }}
            >
              <TextField
                label='تگ جدید یا موجود'
                value={newTag}
                onChange={setNewTag}
                placeholder='بنویس و Enter بزن'
                hint='از لیست انتخاب کن یا نام جدید بساز.'
              />
              <div className='flex flex-wrap gap-2'>
                <Button
                  type='button'
                  size='sm'
                  variant='outline'
                  loading={creatingTag}
                  onClick={() => void addTag()}
                >
                  افزودن تگ
                </Button>
                {tagList.map((tag) => (
                  <button
                    key={tag.id}
                    type='button'
                    className='rounded-full bg-gray-100 px-3 py-1 text-xs dark:bg-gray-800'
                    onClick={() =>
                      void formik.setFieldValue(
                        'tagIds',
                        Array.from(new Set([...formik.values.tagIds, String(tag.id)]))
                      )
                    }
                  >
                    {tag.title}
                  </button>
                ))}
              </div>
              <div className='flex flex-wrap gap-2'>
                {formik.values.tagIds.map((id) => {
                  const tag = tagList.find((item) => String(item.id) === id);
                  return (
                    <button
                      key={id}
                      type='button'
                      className='rounded-full bg-brand-50 px-3 py-1 text-xs text-brand-600 dark:bg-gray-800'
                      onClick={() => void formik.setFieldValue('tagIds', formik.values.tagIds.filter((item) => item !== id))}
                    >
                      {tag?.title ?? id} ×
                    </button>
                  );
                })}
              </div>
            </div>
            <TextField
              label='شناسه محصولات مرتبط (با ویرگول)'
              value={formik.values.productIds}
              onChange={(value) => void formik.setFieldValue('productIds', value)}
            />
            <div className='md:col-span-2'>
              <TextareaField
                label='خلاصه'
                rows={3}
                placeholder='اگر خالی بماند از ابتدای متن ساخته می‌شود.'
                hint='اگر خالی باشد از ابتدای متن ساخته می‌شود؛ از توضیح متا کپی نمی‌شود.'
                value={formik.values.excerpt}
                onChange={(value) => void formik.setFieldValue('excerpt', value)}
              />
            </div>
            <div className='md:col-span-2'>
              <BlogCoverField
                label='تصویر شاخص'
                value={formik.values.cover}
                alt={formik.values.coverAlt}
                width={formik.values.coverWidth}
                height={formik.values.coverHeight}
                onChange={(next) => {
                  void formik.setFieldValue('cover', next.url);
                  if (next.alt !== undefined) void formik.setFieldValue('coverAlt', next.alt);
                  if (next.width) void formik.setFieldValue('coverWidth', next.width);
                  if (next.height) void formik.setFieldValue('coverHeight', next.height);
                }}
              />
            </div>
            <div className='md:col-span-2'>
              <TextareaField
                label='FAQ (هر خط: سؤال|جواب)'
                rows={4}
                placeholder={'هزینه چقدر است؟|تحویل دیجیتال است.'}
                hint='بعد از ذخیره و رفرش همین خطوط برمی‌گردند و در صفحه مطلب اسکیمای FAQPage ساخته می‌شود.'
                value={formik.values.faq}
                onChange={(value) => void formik.setFieldValue('faq', value)}
              />
            </div>
          </div>
        ) : null}

        {tab === 'content' ? (
          <RichTextField
            label='متن مطلب'
            value={formik.values.content}
            onChange={(value) => void formik.setFieldValue('content', value)}
            onBlur={() => formik.setFieldTouched('content', true)}
            hint={formik.touched.content ? formik.errors.content : 'H1 ممنوع است. هدینگ‌ها H2 تا H4.'}
          />
        ) : null}

        {tab === 'seo' ? (
          <div className='grid gap-4 md:grid-cols-2'>
            <TextField
              label='کلمه کلیدی اصلی'
              value={formik.values.focusKeyword}
              onChange={(value) => void formik.setFieldValue('focusKeyword', value)}
            />
            <TextField
              label='کلمات کلیدی فرعی'
              value={formik.values.secondaryKeywords}
              onChange={(value) => void formik.setFieldValue('secondaryKeywords', value)}
              hint='با ویرگول جدا کن.'
            />
            <TextField
              label='عنوان متا'
              value={formik.values.metaTitle}
              onChange={(value) => void formik.setFieldValue('metaTitle', value)}
              hint={`پیش‌فرض: ${formik.values.title || 'عنوان'} | شارژینو`}
            />
            <div className='md:col-span-2'>
              <TextareaField
                label='توضیح متا (۱۲۰ تا ۱۶۰)'
                rows={5}
                value={formik.values.metaDescription}
                onChange={(value) => void formik.setFieldValue('metaDescription', value)}
              />
            </div>
            <TextField
              label='عنوان Open Graph'
              value={formik.values.ogTitle}
              onChange={(value) => void formik.setFieldValue('ogTitle', value)}
              hint='اگر خالی باشد از عنوان متا استفاده می‌شود.'
            />
            <TextField
              label='توضیح Open Graph'
              value={formik.values.ogDescription}
              onChange={(value) => void formik.setFieldValue('ogDescription', value)}
            />
            <div className='md:col-span-2'>
              <BlogCoverField
                label='تصویر Open Graph'
                value={formik.values.ogImage}
                alt={formik.values.ogTitle || formik.values.title}
                role='inline'
                onChange={(next) => void formik.setFieldValue('ogImage', next.url)}
              />
            </div>
            <TextField
              label='عنوان Twitter Card'
              value={formik.values.twitterTitle}
              onChange={(value) => void formik.setFieldValue('twitterTitle', value)}
            />
            <TextField
              label='توضیح Twitter Card'
              value={formik.values.twitterDescription}
              onChange={(value) => void formik.setFieldValue('twitterDescription', value)}
            />
            <div className='md:col-span-2'>
              <BlogCoverField
                label='تصویر Twitter Card'
                value={formik.values.twitterImage}
                alt={formik.values.twitterTitle || formik.values.title}
                role='inline'
                onChange={(next) => void formik.setFieldValue('twitterImage', next.url)}
              />
            </div>
            <TextField
              label='Canonical (اختیاری)'
              value={formik.values.canonicalUrl}
              onChange={(value) => void formik.setFieldValue('canonicalUrl', value)}
            />
            <Checkbox
              label='noindex'
              checked={formik.values.noindex}
              onChange={(checked) => void formik.setFieldValue('noindex', checked)}
            />
          </div>
        ) : null}

        {tab === 'preview' ? (
          <article className='space-y-4 rounded-2xl border border-gray-200 p-5 dark:border-gray-800'>
            {formik.values.cover ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={adminMediaSrc(formik.values.cover)} alt={formik.values.coverAlt} className='max-h-64 w-full rounded-xl object-cover' />
            ) : null}
            <h1 className='text-2xl font-bold text-gray-900 dark:text-white'>{formik.values.title || 'بدون عنوان'}</h1>
            {formik.values.excerpt ? <p className='text-sm text-gray-600 dark:text-gray-300'>{formik.values.excerpt}</p> : null}
            <div className={PREVIEW_HTML_CLASS} dangerouslySetInnerHTML={{ __html: previewHtml }} />
          </article>
        ) : null}

        <div className='flex flex-wrap gap-3'>
          <Button type='submit' loading={formik.isSubmitting}>
            ذخیره پیش‌نویس
          </Button>
          <Button type='button' variant='outline' loading={formik.isSubmitting} onClick={() => void submitPublish()}>
            انتشار
          </Button>
          {previewHref ? (
            <a href={previewHref} target='_blank' rel='noreferrer' className='inline-flex items-center text-sm text-brand-500'>
              پیش‌نمایش در سایت
            </a>
          ) : (
            <p className='self-center text-xs text-gray-500'>بعد از اولین ذخیره، لینک پیش‌نمایش می‌آید.</p>
          )}
          {post?.revisions?.length ? (
            <SelectField
              label='بازگردانی نسخه'
              value=''
              onChange={(value) => {
                if (!value || !savedId) return;
                void restoreBlogRevision(savedId, Number(value)).then((result) => {
                  if (!result.ok) {
                    setMessage({ variant: 'error', text: result.message });
                    return;
                  }
                  setMessage({ variant: 'success', text: 'نسخه بازگردانی شد.' });
                  router.refresh();
                });
              }}
              options={(post.revisions ?? []).map((revision) => ({
                value: String(revision.id),
                label: revision.title || revision.createdAt || String(revision.id)
              }))}
            />
          ) : null}
        </div>
      </div>
      <BlogSeoChecklist
        values={{
          title: formik.values.title,
          slug: formik.values.slug,
          excerpt: formik.values.excerpt,
          content: formik.values.content,
          metaTitle: formik.values.metaTitle,
          metaDescription: formik.values.metaDescription,
          focusKeyword: formik.values.focusKeyword,
          cover: formik.values.cover,
          coverAlt: formik.values.coverAlt
        }}
      />
    </form>
  );
}

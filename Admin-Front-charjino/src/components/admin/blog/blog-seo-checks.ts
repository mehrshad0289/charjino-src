import { keywordMatchesSlug, slugLooksLatin } from './blog-slug';

export type SeoCheckStatus = 'ok' | 'warn' | 'info';

export type SeoCheckItem = {
  status: SeoCheckStatus;
  text: string;
};

export type SeoCheckInput = {
  title: string;
  slug: string;
  excerpt: string;
  content: string;
  metaTitle: string;
  metaDescription: string;
  focusKeyword: string;
  cover: string;
  coverAlt: string;
};

const INTERNAL_HOSTS = new Set(['charjino.ir', 'www.charjino.ir']);

export function words(html: string) {
  const text = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  return text ? text.split(' ').length : 0;
}

export function firstH2(html: string) {
  const match = html.match(/<h2\b[^>]*>([\s\S]*?)<\/h2>/i);
  return match ? match[1].replace(/<[^>]+>/g, '').trim() : '';
}

export function intro(html: string) {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 400);
}

export function headingSkip(html: string) {
  const levels = [...html.matchAll(/<h([2-4])\b/gi)].map((m) => Number(m[1]));
  for (let i = 1; i < levels.length; i += 1) {
    if (levels[i] > levels[i - 1] + 1) return true;
  }
  return false;
}

export function parseImgTags(html: string) {
  return [...html.matchAll(/<img\b([^>]*)>/gi)].map((m) => m[1] || '');
}

export function imgHasAlt(attrs: string) {
  const match = attrs.match(/\balt\s*=\s*("([^"]*)"|'([^']*)')/i);
  if (!match) return false;
  const value = (match[2] ?? match[3] ?? '').trim();
  return value.length > 0;
}

export function bodyImagesMissingAlt(html: string) {
  const imgs = parseImgTags(html);
  if (imgs.length === 0) return false;
  return imgs.some((attrs) => !imgHasAlt(attrs));
}

export function isInternalHref(href: string) {
  const value = href.trim();
  if (!value) return false;
  if (value.startsWith('/') && !value.startsWith('//')) return true;
  try {
    const url = new URL(value);
    return INTERNAL_HOSTS.has(url.hostname.toLowerCase());
  } catch {
    return false;
  }
}

export function hasInternalLink(html: string) {
  for (const match of html.matchAll(/<a\b([^>]*)>/gi)) {
    const attrs = match[1] || '';
    const hrefMatch = attrs.match(/\bhref\s*=\s*("([^"]*)"|'([^']*)')/i);
    const href = (hrefMatch?.[2] ?? hrefMatch?.[3] ?? '').trim();
    if (isInternalHref(href)) return true;
  }
  return false;
}

export function resolvedMetaTitle(values: Pick<SeoCheckInput, 'title' | 'metaTitle'>) {
  const custom = values.metaTitle.trim();
  if (custom) return { text: custom, fromDefault: false };
  const title = values.title.trim();
  return { text: title ? `${title} | شارژینو` : '', fromDefault: true };
}

export function altCheckOk(values: Pick<SeoCheckInput, 'content' | 'cover' | 'coverAlt'>) {
  const bodyOk = !bodyImagesMissingAlt(values.content);
  if (!values.cover.trim()) return bodyOk;
  return Boolean(values.coverAlt.trim()) && bodyOk;
}

export function buildSeoChecks(values: SeoCheckInput): SeoCheckItem[] {
  const meta = resolvedMetaTitle(values);
  const desc = values.metaDescription.trim() || values.excerpt.trim();
  const kw = values.focusKeyword.trim();
  const titleLenLabel = meta.fromDefault
    ? `عنوان متا حدود ۶۰ کاراکتر — از عنوان پیش‌فرض استفاده می‌شود (${meta.text.length})`
    : `عنوان متا حدود ۶۰ کاراکتر (${meta.text.length})`;
  const keywordMissing = { status: 'warn' as const, text: 'کلیدواژه را وارد کنید' };
  const slugLatin = slugLooksLatin(values.slug);
  const slugMatch = keywordMatchesSlug(kw, values.slug);
  let slugCheck: SeoCheckItem;
  if (!kw) {
    slugCheck = keywordMissing;
  } else if (slugMatch) {
    slugCheck = { status: 'ok', text: 'کلمه کلیدی در نامک' };
  } else if (slugLatin) {
    slugCheck = {
      status: 'info',
      text: 'نامک لاتین است؛ این چک اختیاری است (معادل لاتین کلیدواژه پذیرفته می‌شود)'
    };
  } else {
    slugCheck = { status: 'warn', text: 'کلمه کلیدی در نامک' };
  }

  return [
    {
      status: meta.text.length >= 45 && meta.text.length <= 65 ? 'ok' : 'warn',
      text: titleLenLabel
    },
    {
      status: desc.length >= 120 && desc.length <= 160 ? 'ok' : 'warn',
      text: `توضیح متا ۱۲۰ تا ۱۶۰ کاراکتر (${desc.length})`
    },
    !kw ? keywordMissing : { status: values.title.includes(kw) ? 'ok' : 'warn', text: 'کلمه کلیدی در عنوان' },
    !kw ? { ...keywordMissing } : { status: firstH2(values.content).includes(kw) ? 'ok' : 'warn', text: 'کلمه کلیدی در اولین H2' },
    slugCheck,
    !kw ? { ...keywordMissing } : { status: intro(values.content).includes(kw) ? 'ok' : 'warn', text: 'کلمه کلیدی در مقدمه' },
    { status: words(values.content) >= 300 ? 'ok' : 'warn', text: `تعداد کلمات ≥ ۳۰۰ (${words(values.content)})` },
    { status: hasInternalLink(values.content) ? 'ok' : 'warn', text: 'حداقل یک لینک داخلی' },
    { status: altCheckOk(values) ? 'ok' : 'warn', text: 'alt همه تصاویر' },
    { status: headingSkip(values.content) ? 'warn' : 'ok', text: 'پرش هدینگ نباشد' }
  ];
}

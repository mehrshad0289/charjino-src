const FA_LATIN: Record<string, string> = {
  آ: 'a',
  ا: 'a',
  أ: 'a',
  إ: 'e',
  ء: '',
  ب: 'b',
  پ: 'p',
  ت: 't',
  ث: 's',
  ج: 'j',
  چ: 'ch',
  ح: 'h',
  خ: 'kh',
  د: 'd',
  ذ: 'z',
  ر: 'r',
  ز: 'z',
  ژ: 'zh',
  س: 's',
  ش: 'sh',
  ص: 's',
  ض: 'z',
  ط: 't',
  ظ: 'z',
  ع: 'a',
  غ: 'gh',
  ف: 'f',
  ق: 'gh',
  ک: 'k',
  ك: 'k',
  گ: 'g',
  ل: 'l',
  م: 'm',
  ن: 'n',
  و: 'v',
  ه: 'h',
  ی: 'y',
  ي: 'y',
  ئ: 'y',
  ة: 'h',
  '۰': '0',
  '۱': '1',
  '۲': '2',
  '۳': '3',
  '۴': '4',
  '۵': '5',
  '۶': '6',
  '۷': '7',
  '۸': '8',
  '۹': '9',
  '٠': '0',
  '١': '1',
  '٢': '2',
  '٣': '3',
  '٤': '4',
  '٥': '5',
  '٦': '6',
  '٧': '7',
  '٨': '8',
  '٩': '9'
};

export function slugifyTitle(source: string): string {
  const trimmed = source.trim().toLowerCase();
  if (!trimmed) return '';
  return trimmed
    .replace(/[^\p{L}\p{N}\s_-]+/gu, '')
    .replace(/[\s_]+/gu, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 80);
}

export function latinizeSlug(source: string): string {
  const mapped = Array.from(source)
    .map((ch) => FA_LATIN[ch] ?? ch)
    .join('');
  return slugifyTitle(mapped);
}

export function slugLooksLatin(slug: string): boolean {
  const trimmed = slug.trim();
  if (!trimmed) return false;
  return /^[a-z0-9-]+$/i.test(trimmed) && /[a-z]/i.test(trimmed);
}

export function keywordMatchesSlug(keyword: string, slug: string): boolean {
  const kw = keyword.trim();
  const s = slug.trim().toLowerCase();
  if (!kw || !s) return false;
  if (s.includes(kw.toLowerCase())) return true;
  const latin = latinizeSlug(kw);
  return latin.length >= 2 && s.includes(latin);
}

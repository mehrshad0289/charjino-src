export const GA_UNKNOWN = 'نامعلوم';

export const SENSITIVE_QUERY_RE =
  /^(e-?mail|mail|phone|mobile|tel|sms|name|first[_-]?name|last[_-]?name|full[_-]?name|national[_-]?code|national[_-]?id|order(_?id|_?number|_?code)?|number|token|otp|pass(word)?|pin|authority|ref(_)?id|track(ing|_?id)?|user(_?id)?|player(_?id)?|phone[_-]?number|mobile[_-]?number|username|code)$/i;

const ID_SEGMENTS: Array<{ prefix: string[]; skip?: string }> = [
  { prefix: ['profile', 'orders'] },
  { prefix: ['profile', 'tickets'], skip: 'new' },
  { prefix: ['invoices'] },
  { prefix: ['orders'] },
  { prefix: ['support'] },
  { prefix: ['users'] },
];

export function isSensitiveGaQueryKey(key: string): boolean {
  return SENSITIVE_QUERY_RE.test(key.trim());
}

export function sanitizeGaPathname(pathname: string): string {
  const parts = pathname.split('/').filter((part, index) => index === 0 || part.length > 0);
  for (const rule of ID_SEGMENTS) {
    const start = parts[0] === '' ? 1 : 0;
    const matches = rule.prefix.every((seg, i) => parts[start + i] === seg);
    const idIndex = start + rule.prefix.length;
    const id = parts[idIndex];
    if (!matches || !id) continue;
    if (rule.skip && id === rule.skip) continue;
    parts[idIndex] = GA_UNKNOWN;
  }
  return parts.join('/') || '/';
}

export function sanitizeGaPageLocation(raw: string, base = 'https://charjino.ir'): string {
  let url: URL;
  try {
    url = new URL(raw, base);
  } catch {
    return raw || GA_UNKNOWN;
  }
  url.pathname = sanitizeGaPathname(url.pathname);
  const next = new URLSearchParams();
  url.searchParams.forEach((value, key) => {
    next.set(key, isSensitiveGaQueryKey(key) ? GA_UNKNOWN : value);
  });
  url.search = next.toString();
  url.hash = '';
  return url.toString();
}

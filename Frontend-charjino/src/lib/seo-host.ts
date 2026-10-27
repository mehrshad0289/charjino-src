const INDEXABLE_HOSTS = new Set(['charjino.ir', 'www.charjino.ir']);

export function hostnameFromHostHeader(hostHeader?: string | null) {
  const raw = (hostHeader ?? '').trim().toLowerCase();
  if (!raw) return '';
  if (raw.startsWith('[')) {
    const end = raw.indexOf(']');
    return end > 0 ? raw.slice(1, end) : raw;
  }
  return raw.split(':')[0] ?? '';
}

/** پشت nginx باید Host واقعی مشتری خوانده شود، نه hostname داخلی 127.0.0.1. */
export function seoHostFromRequest(request: {
  headers: { get(name: string): string | null };
  nextUrl?: { hostname?: string };
}) {
  const candidates = [
    request.headers.get('host'),
    request.headers.get('x-forwarded-host')?.split(',')[0]?.trim() ?? null,
    request.nextUrl?.hostname ?? null,
  ];
  for (const candidate of candidates) {
    if (isIndexableSeoHost(candidate)) {
      return hostnameFromHostHeader(candidate);
    }
  }
  return hostnameFromHostHeader(candidates.find(Boolean) ?? '');
}

/** فقط دامنهٔ نهایی فروشگاه ایندکس می‌شود؛ استیجینگ، Preview و localhost نه. */
export function isIndexableSeoHost(hostHeader?: string | null) {
  return INDEXABLE_HOSTS.has(hostnameFromHostHeader(hostHeader));
}

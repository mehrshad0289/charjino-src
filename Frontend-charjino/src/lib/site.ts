import { SOCIAL } from '@/lib/social';

export const BRAND_FA = 'شارژینو';
export const BRAND_EN = 'Charjino';

const SITE_URL_FALLBACK = 'https://charjino.ir';

function resolveSiteUrl() {
  const raw = (process.env.NEXT_PUBLIC_SITE_URL ?? SITE_URL_FALLBACK).replace(/\/+$/, '');
  const production = process.env.NODE_ENV === 'production' || process.env.VERCEL_ENV === 'production';
  if (!production) {
    return raw;
  }
  try {
    const host = new URL(raw).hostname;
    if (host === 'localhost' || host === '127.0.0.1') {
      return SITE_URL_FALLBACK;
    }
  } catch {
    return SITE_URL_FALLBACK;
  }
  return raw;
}

export const SITE_URL = resolveSiteUrl();

export function absoluteUrl(path = '/'): string {
  const normalized = path.startsWith('/') ? path : `/${path}`;
  if (normalized === '/') {
    return SITE_URL;
  }
  return `${SITE_URL}${normalized}`;
}

/** Relative canonical path. Drops utm_* and empty values; page=1 is omitted. */
export function canonicalPath(
  pathname: string,
  query: Record<string, string | number | undefined | null> = {},
): string {
  const path = pathname.startsWith('/') ? pathname : `/${pathname}`;
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (!key || key.toLowerCase().startsWith('utm_')) {
      continue;
    }
    if (value === undefined || value === null || value === '') {
      continue;
    }
    if (key === 'page' && Number(value) <= 1) {
      continue;
    }
    params.set(key, String(value));
  }
  const qs = params.toString();
  if (path === '/') {
    return qs ? `/?${qs}` : '/';
  }
  return qs ? `${path}?${qs}` : path;
}

export function canonicalMeta(path: string) {
  return {
    alternates: { canonical: path },
    openGraph: { url: path },
  } as const;
}

export const SOCIAL_LINKS = {
  instagram: SOCIAL.instagram.href,
  telegram: SOCIAL.telegram.href,
} as const;

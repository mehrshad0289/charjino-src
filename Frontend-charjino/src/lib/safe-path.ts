export function safeNextPath(raw: string | null | undefined, fallback = '/') {
  if (!raw) return fallback;
  const value = raw.trim();
  if (!value.startsWith('/') || value.startsWith('//') || value.includes('\\')) {
    return fallback;
  }
  if (value.includes('://')) return fallback;
  return value;
}

export function authHref(nextPath?: string | null) {
  const next = safeNextPath(nextPath);
  if (next === '/') return '/auth';
  return `/auth?next=${encodeURIComponent(next)}`;
}

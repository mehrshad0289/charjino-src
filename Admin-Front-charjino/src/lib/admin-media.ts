export function shopSiteUrl() {
  return (process.env.NEXT_PUBLIC_SHOP_URL || 'http://127.0.0.1:3001').replace(/\/+$/, '');
}

export function shopPath(path: string) {
  const suffix = path.startsWith('/') ? path : `/${path}`;
  return `${shopSiteUrl()}${suffix}`;
}

export function adminMediaSrc(src: string) {
  const raw = src.trim();
  if (!raw) return '';
  if (/^https?:\/\//i.test(raw) || raw.startsWith('data:')) {
    return raw;
  }
  if (raw.startsWith('/image/')) {
    const shop = (process.env.NEXT_PUBLIC_SHOP_URL || 'http://127.0.0.1:3001').replace(/\/+$/, '');
    return `${shop}${raw}`;
  }
  if (raw.startsWith('/storage/')) {
    const origin = (process.env.NEXT_PUBLIC_API_ORIGIN || 'https://api.charjino.ir').replace(/\/+$/, '');
    return `${origin}${raw}`;
  }
  return raw;
}

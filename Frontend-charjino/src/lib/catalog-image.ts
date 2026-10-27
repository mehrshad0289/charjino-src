export function catalogImageSrc(src: string | null | undefined) {
  const raw = (src ?? '').trim();
  if (!raw) return '/image/catalog/pubg-mobile.svg';
  if (raw.startsWith('/')) return raw;

  try {
    const url = new URL(raw);
    if (url.pathname.startsWith('/storage/')) {
      return `${url.pathname}${url.search}`;
    }
  } catch {
    return raw;
  }

  const appId = steamAppIdFromSrc(raw);
  if (appId > 0 && needsSteamCoverProxy(raw)) {
    return `/api/steam-cover/${appId}`;
  }

  return raw;
}

export function steamAppIdFromSrc(src: string) {
  const match = src.match(/\/apps\/(\d+)\//i);
  if (!match) return 0;
  const id = Number.parseInt(match[1], 10);
  return Number.isFinite(id) && id > 0 ? id : 0;
}

export function needsSteamCoverProxy(src: string) {
  if (/cloudflare\.steamstatic\.com/i.test(src)) return true;
  return /\/steam\/apps\/\d+\/header\.jpg/i.test(src);
}

export function isSteamHeader(src: string) {
  return /steamstatic\.com\/(?:steam\/apps|store_item_assets\/steam\/apps)\/\d+\/(?:[^/?#]+\/)?header\.jpg/i.test(
    src,
  );
}

export type CatalogCardFrame = 'wide' | 'square';

export function catalogCardFrame(thumbnails: Array<string | null | undefined>): CatalogCardFrame {
  const srcs = thumbnails.map((src) => (src ?? '').trim()).filter(Boolean);
  if (srcs.length === 0) return 'square';
  const steam = srcs.filter((src) => isSteamHeader(src)).length;
  return steam * 2 >= srcs.length ? 'wide' : 'square';
}

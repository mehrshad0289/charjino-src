import { getBackendUrl } from '@/lib/env';

export const runtime = 'nodejs';
export const revalidate = 86400;

const MAX_BYTES = 2_000_000;

type RouteProps = {
  params: Promise<{ appId: string }>;
};

function allowedImageUrl(raw: string) {
  try {
    const url = new URL(raw);
    if (url.protocol !== 'https:') return false;
    const host = url.hostname.toLowerCase();
    if (host.includes('cloudflare')) return false;
    return host.endsWith('.steamstatic.com') || host === 'steamcdn-a.akamaihd.net';
  } catch {
    return false;
  }
}

function iranSafeSteamUrl(raw: string) {
  return raw.replace(/cloudflare\.steamstatic\.com/gi, 'akamai.steamstatic.com').replace(
    /shared\.fastly\.steamstatic\.com/gi,
    'shared.akamai.steamstatic.com',
  );
}

async function resolveHeaderUrl(appId: string) {
  try {
    const response = await fetch(`${getBackendUrl()}/catalog/steam-cover/${appId}`, {
      headers: { Accept: 'application/json' },
      next: { revalidate: 86400 },
    });
    const payload = (await response.json()) as { data?: { url?: string } };
    const url = typeof payload?.data?.url === 'string' ? iranSafeSteamUrl(payload.data.url) : '';
    if (url && allowedImageUrl(url)) {
      return url;
    }
  } catch {
    // Laravel may be older than this route; fall through to Steam store.
  }

  try {
    const store = await fetch(
      `https://store.steampowered.com/api/appdetails?appids=${appId}&filters=basic`,
      {
        headers: { 'User-Agent': 'Mozilla/5.0 (compatible; Charjino/1.0)' },
        next: { revalidate: 86400 },
      },
    );
    const json = (await store.json()) as Record<string, { data?: { header_image?: string } }>;
    const header = iranSafeSteamUrl(json?.[appId]?.data?.header_image ?? '');
    if (header && allowedImageUrl(header)) {
      return header;
    }
  } catch {
    return '';
  }

  return '';
}

export async function GET(_request: Request, context: RouteProps) {
  const appId = (await context.params).appId;
  if (!/^\d{1,12}$/.test(appId)) {
    return new Response(null, { status: 404 });
  }

  const url = await resolveHeaderUrl(appId);
  if (!url) {
    return new Response(null, { status: 404 });
  }

  let image: Response;
  try {
    image = await fetch(url, {
      headers: { 'User-Agent': 'Mozilla/5.0 (compatible; Charjino/1.0)' },
      next: { revalidate: 86400 },
    });
  } catch {
    return new Response(null, { status: 502 });
  }
  if (!image.ok) {
    return new Response(null, { status: 404 });
  }

  const type = image.headers.get('content-type') ?? 'image/jpeg';
  if (!type.startsWith('image/')) {
    return new Response(null, { status: 404 });
  }

  const buf = Buffer.from(await image.arrayBuffer());
  if (buf.length === 0 || buf.length > MAX_BYTES) {
    return new Response(null, { status: 404 });
  }

  return new Response(buf, {
    status: 200,
    headers: {
      'Content-Type': type,
      'Cache-Control': 'public, max-age=86400, s-maxage=604800, stale-while-revalidate=86400',
    },
  });
}

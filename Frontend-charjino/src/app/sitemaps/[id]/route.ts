import { SITEMAP_CACHE_CONTROL } from '@/lib/sitemap/config';
import { listSitemapIds, sitemapXmlForId } from '@/lib/sitemap/engine';
import { parseSitemapFileId, sitemapFileId } from '@/lib/sitemap/ids';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

type RouteProps = {
  params: Promise<{ id: string }>;
};

export async function GET(_request: Request, context: RouteProps) {
  const parsed = parseSitemapFileId((await context.params).id);
  if (!parsed) {
    return new Response('Not Found', { status: 404 });
  }
  const id = sitemapFileId(parsed.kind, parsed.chunk);
  const ids = await listSitemapIds();
  if (!ids.includes(id) && parsed.kind !== 'categories') {
    return new Response('Not Found', { status: 404 });
  }
  try {
    const xml = await sitemapXmlForId(id);
    return new Response(xml, {
      status: 200,
      headers: {
        'Content-Type': 'application/xml; charset=utf-8',
        'Cache-Control': SITEMAP_CACHE_CONTROL,
      },
    });
  } catch (error) {
    console.error('sitemap chunk failed; serving last-good copy', id, error);
    const xml = await sitemapXmlForId(id).catch(() =>
      `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>\n`,
    );
    return new Response(xml, {
      status: 200,
      headers: {
        'Content-Type': 'application/xml; charset=utf-8',
        'Cache-Control': 'public, max-age=60',
      },
    });
  }
}

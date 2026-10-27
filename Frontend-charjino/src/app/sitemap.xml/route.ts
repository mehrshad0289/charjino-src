import { SITEMAP_CACHE_CONTROL } from '@/lib/sitemap/config';
import { fallbackSitemapIndexXml, sitemapIndexDocument } from '@/lib/sitemap/engine';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function GET() {
  try {
    const xml = await sitemapIndexDocument();
    return new Response(xml, {
      status: 200,
      headers: {
        'Content-Type': 'application/xml; charset=utf-8',
        'Cache-Control': SITEMAP_CACHE_CONTROL,
      },
    });
  } catch (error) {
    console.error('sitemap index failed; serving last-good fallback', error);
    return new Response(fallbackSitemapIndexXml(), {
      status: 200,
      headers: {
        'Content-Type': 'application/xml; charset=utf-8',
        'Cache-Control': 'public, max-age=60',
      },
    });
  }
}

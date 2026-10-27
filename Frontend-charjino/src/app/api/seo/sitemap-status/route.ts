import { NextResponse } from 'next/server';
import { secretMatches, sitemapSecret } from '@/lib/sitemap/config';
import { sitemapStatus } from '@/lib/sitemap/engine';

export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';

export async function GET(request: Request) {
  if (!sitemapSecret()) {
    return NextResponse.json({ success: false, message: 'REVALIDATE_SECRET is not set' }, { status: 503 });
  }
  const url = new URL(request.url);
  const provided =
    request.headers.get('x-revalidate-secret') ||
    request.headers.get('authorization')?.replace(/^Bearer\s+/i, '') ||
    url.searchParams.get('secret');
  if (!secretMatches(provided)) {
    return NextResponse.json({ success: false, message: 'unauthorized' }, { status: 401 });
  }
  const data = await sitemapStatus();
  return NextResponse.json({ success: true, data });
}

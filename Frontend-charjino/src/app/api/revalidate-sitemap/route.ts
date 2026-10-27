import { NextResponse } from 'next/server';
import { secretMatches, sitemapSecret } from '@/lib/sitemap/config';
import { requestSitemapRevalidate } from '@/lib/sitemap/engine';

export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';

function providedSecret(request: Request, body: Record<string, unknown>) {
  return (
    request.headers.get('x-revalidate-secret') ||
    request.headers.get('authorization')?.replace(/^Bearer\s+/i, '') ||
    (typeof body.secret === 'string' ? body.secret : null)
  );
}

export async function POST(request: Request) {
  if (!sitemapSecret()) {
    return NextResponse.json({ success: false, message: 'REVALIDATE_SECRET is not set' }, { status: 503 });
  }
  let body: Record<string, unknown> = {};
  try {
    body = (await request.json()) as Record<string, unknown>;
  } catch {
    body = {};
  }
  if (!secretMatches(providedSecret(request, body))) {
    return NextResponse.json({ success: false, message: 'unauthorized' }, { status: 401 });
  }
  const handle = typeof body.handle === 'string' ? body.handle.trim() : undefined;
  const action = typeof body.action === 'string' ? body.action.trim() : undefined;
  requestSitemapRevalidate({ handle, action });
  return NextResponse.json({ success: true, message: 'sitemap revalidate queued' });
}

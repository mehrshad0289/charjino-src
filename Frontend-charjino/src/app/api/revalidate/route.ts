import { revalidatePath, revalidateTag } from 'next/cache';
import { NextResponse } from 'next/server';
import {
  CATALOG_HOME_TAG,
  CATALOG_LIST_TAG,
  SITEMAP_TAG,
  isBlogCacheTag,
  isBuyGuideCacheTag,
  isProductCacheTag,
  productCacheTag,
} from '@/lib/catalog-cache';
import { ROUTES } from '@/lib/routes';
import { secretMatches, sitemapSecret } from '@/lib/sitemap/config';
import { requestSitemapRevalidate } from '@/lib/sitemap/engine';

export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';

const ALWAYS_TAGS = [CATALOG_HOME_TAG, CATALOG_LIST_TAG, SITEMAP_TAG] as const;
const ALWAYS_TAG_SET = new Set<string>(ALWAYS_TAGS);

function providedSecret(request: Request, body: Record<string, unknown>) {
  return (
    request.headers.get('x-revalidate-secret') ||
    request.headers.get('authorization')?.replace(/^Bearer\s+/i, '') ||
    (typeof body.secret === 'string' ? body.secret : null)
  );
}

function expireTag(tag: string) {
  revalidateTag(tag, { expire: 0 });
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

  const handle = typeof body.handle === 'string' ? body.handle.trim() : '';
  const action = typeof body.action === 'string' ? body.action.trim() : undefined;
  const tags = new Set<string>(ALWAYS_TAGS);

  if (handle) {
    tags.add(productCacheTag(handle));
  }
  if (Array.isArray(body.tags)) {
    for (const value of body.tags) {
      if (typeof value !== 'string') {
        continue;
      }
      const tag = value.trim();
      if (ALWAYS_TAG_SET.has(tag) || isProductCacheTag(tag) || isBuyGuideCacheTag(tag) || isBlogCacheTag(tag)) {
        tags.add(tag);
      }
    }
  }

  for (const tag of tags) {
    expireTag(tag);
  }

  revalidatePath(ROUTES.home);
  revalidatePath(ROUTES.categories);
  if ([...tags].some((tag) => isBlogCacheTag(tag))) {
    revalidatePath('/blog', 'layout');
    revalidatePath('/blog/sitemap.xml');
    revalidatePath('/blog/feed.xml');
  }
  if (handle) {
    revalidatePath(ROUTES.catalog(handle));
  }

  requestSitemapRevalidate({ handle: handle || undefined, action });

  return NextResponse.json({
    success: true,
    revalidated: [...tags],
  });
}

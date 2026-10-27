import { NextResponse, type NextRequest } from 'next/server';
import {
  ACCESS_TOKEN_MAX_AGE,
  AUTH_COOKIE_BASE_OPTIONS,
} from '@/lib/auth-cookie-options';
import { requestNewAccessToken, RefreshAuthError } from '@/lib/request-access-token';
import { ACCESS_TOKEN_COOKIE, REFRESH_TOKEN_COOKIE } from '@/lib/constants';
import { browseShouldNoindex, categoryFromBrowsePath, parseBrowseQuery } from '@/lib/catalog-browse';
import { isPublicPageCachePath, PAGE_CACHE_CONTROL } from '@/lib/page-cache';
import { SITEMAP_CACHE_CONTROL } from '@/lib/sitemap/config';
import { resolveCatalogPath } from '@/lib/catalog-resolve';
import { isIndexableSeoHost, seoHostFromRequest } from '@/lib/seo-host';

function isCatalogBrowsePath(pathname: string) {
  return pathname === '/catalog/category' || pathname.startsWith('/catalog/category/');
}

function browseShouldNoindexFromRequest(request: NextRequest) {
  const params = request.nextUrl.searchParams;
  if (params.has('category')) {
    return true;
  }
  return browseShouldNoindex(
    parseBrowseQuery({
      category: categoryFromBrowsePath(request.nextUrl.pathname),
      q: params.get('q') ?? '',
      platform: params.get('platform') ?? '',
      region: params.get('region') ?? '',
      page: params.get('page') ?? '1',
      sort: params.get('sort') ?? '',
    }),
  );
}

function categoryQueryRedirect(request: NextRequest): NextResponse | null {
  if (request.nextUrl.pathname !== '/catalog/category') {
    return null;
  }
  const category = request.nextUrl.searchParams.get('category')?.trim();
  if (!category || category.includes('/') || category.includes('..')) {
    return null;
  }
  const dest = request.nextUrl.clone();
  dest.pathname = `/catalog/category/${category}`;
  dest.searchParams.delete('category');
  return NextResponse.redirect(dest, 301);
}

function applySeoHeaders(request: NextRequest, response: NextResponse) {
  const path = request.nextUrl.pathname;
  if (path === '/sitemap.xml' || /^\/sitemap\/[a-z0-9-]+\.xml$/.test(path)) {
    response.headers.set('Cache-Control', SITEMAP_CACHE_CONTROL);
  } else if (
    isPublicPageCachePath(path) &&
    response.status < 400 &&
    !response.headers.get('set-cookie')
  ) {
    response.headers.set('Cache-Control', PAGE_CACHE_CONTROL);
  }
  if (!isIndexableSeoHost(seoHostFromRequest(request))) {
    response.headers.set('X-Robots-Tag', 'noindex');
  } else if (isCatalogBrowsePath(path) && browseShouldNoindexFromRequest(request)) {
    response.headers.set('X-Robots-Tag', 'noindex, follow');
  }
  return response;
}

function decodeJwtExp(token: string): number | null {
  try {
    const segment = token.split('.')[1];
    if (!segment) return null;

    let normalized = segment.replace(/-/g, '+').replace(/_/g, '/');
    while (normalized.length % 4 !== 0) {
      normalized += '=';
    }

    const payload = JSON.parse(atob(normalized)) as { exp?: unknown };
    return typeof payload.exp === 'number' ? payload.exp : null;
  } catch {
    return null;
  }
}

function needsAccessRefresh(accessToken: string | undefined): boolean {
  if (!accessToken) return true;
  const exp = decodeJwtExp(accessToken);
  if (exp === null) return true;
  return exp * 1000 <= Date.now() + 30_000;
}

function buildRequestHeadersWithAccessToken(
  request: NextRequest,
  accessToken: string,
) {
  const headers = new Headers(request.headers);
  const cookies = request.cookies
    .getAll()
    .filter((cookie) => cookie.name !== ACCESS_TOKEN_COOKIE)
    .map((cookie) => `${cookie.name}=${cookie.value}`);

  cookies.push(`${ACCESS_TOKEN_COOKIE}=${accessToken}`);
  headers.set('cookie', cookies.join('; '));
  return headers;
}

function isOutOfRangePage(request: NextRequest) {
  const raw = request.nextUrl.searchParams.get('page');
  if (!raw) {
    return false;
  }
  const page = Number.parseInt(raw, 10);
  return !Number.isFinite(page) || page < 1 || page > 10_000;
}

const SHORT_ERROR_CACHE = 'private, no-store, max-age=0';

async function applyCatalogResolve(request: NextRequest): Promise<NextResponse | null> {
  const path = request.nextUrl.pathname;
  const match = /^\/catalog\/([^/]+)$/.exec(path);
  if (!match || match[1] === 'category') {
    return null;
  }
  const resolved = await resolveCatalogPath(path);
  if (resolved.status === 'redirect' && resolved.target) {
    const dest = resolved.target.startsWith('http')
      ? resolved.target
      : new URL(resolved.target, request.nextUrl.origin).toString();
    return NextResponse.redirect(dest, 301);
  }
  if (resolved.status === 'gone') {
    return new NextResponse('Gone', {
      status: 410,
      headers: {
        'Cache-Control': SHORT_ERROR_CACHE,
        'X-Robots-Tag': 'noindex',
        'Content-Type': 'text/plain; charset=utf-8',
      },
    });
  }
  return null;
}

export async function proxy(request: NextRequest) {
  const categoryRedirect = categoryQueryRedirect(request);
  if (categoryRedirect) {
    return applySeoHeaders(request, categoryRedirect);
  }
  const catalogResponse = await applyCatalogResolve(request);
  if (catalogResponse) {
    return applySeoHeaders(request, catalogResponse);
  }
  if (isOutOfRangePage(request)) {
    const dest = request.nextUrl.clone();
    dest.pathname = '/catalog/category/_out-of-range';
    dest.search = '';
    if (dest.hostname === 'localhost' || dest.hostname === '127.0.0.1') {
      dest.protocol = 'http:';
    }
    const notFound = NextResponse.rewrite(dest);
    notFound.headers.set('x-robots-tag', 'noindex');
    return applySeoHeaders(request, notFound);
  }

  const refreshToken = request.cookies.get(REFRESH_TOKEN_COOKIE)?.value;
  if (!refreshToken) {
    return applySeoHeaders(request, NextResponse.next());
  }

  const accessToken = request.cookies.get(ACCESS_TOKEN_COOKIE)?.value;
  if (!needsAccessRefresh(accessToken)) {
    return applySeoHeaders(request, NextResponse.next());
  }

  try {
    const nextAccessToken = await requestNewAccessToken(refreshToken);
    const response = NextResponse.next({
      request: {
        headers: buildRequestHeadersWithAccessToken(request, nextAccessToken),
      },
    });

    response.cookies.set(ACCESS_TOKEN_COOKIE, nextAccessToken, {
      ...AUTH_COOKIE_BASE_OPTIONS,
      maxAge: ACCESS_TOKEN_MAX_AGE,
    });

    return applySeoHeaders(request, response);
  } catch (error) {
    const isRefreshAuthError =
      error instanceof RefreshAuthError ||
      (error instanceof Error && error.name === 'RefreshAuthError');

    if (!isRefreshAuthError) {
      return applySeoHeaders(request, NextResponse.next());
    }

    const response = NextResponse.next();
    response.cookies.delete({ name: ACCESS_TOKEN_COOKIE, path: '/' });
    response.cookies.delete({ name: REFRESH_TOKEN_COOKIE, path: '/' });
    return applySeoHeaders(request, response);
  }
}

export const config = {
  matcher: ['/((?!api|_next/static|_next/image|favicon.ico|favicon-16x16.png|favicon-32x32.png|apple-touch-icon.png|icon-192.png|icon-512.png|icon-maskable-512.png|site.webmanifest|og-image.png|twitter-image.png|image/|icons/|storage/).*)'],
};

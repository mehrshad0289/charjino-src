import { NextResponse, type NextRequest } from 'next/server';
import {
  ACCESS_TOKEN_MAX_AGE,
  AUTH_COOKIE_BASE_OPTIONS
} from '@/lib/auth-cookie-options';
import { requestNewAccessToken, RefreshAuthError } from '@/lib/request-access-token';
import { ACCESS_TOKEN_COOKIE, REFRESH_TOKEN_COOKIE } from '@/lib/constants';

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

function buildRequestHeadersWithAccessToken(request: NextRequest, accessToken: string) {
  const headers = new Headers(request.headers);
  const cookies = request.cookies
    .getAll()
    .filter((cookie) => cookie.name !== ACCESS_TOKEN_COOKIE)
    .map((cookie) => `${cookie.name}=${cookie.value}`);

  cookies.push(`${ACCESS_TOKEN_COOKIE}=${accessToken}`);
  headers.set('cookie', cookies.join('; '));
  return headers;
}

function clearAuthAndRedirect(request: NextRequest) {
  const url = request.nextUrl.clone();
  url.pathname = '/signin';
  const response = NextResponse.redirect(url);
  response.cookies.delete({ name: ACCESS_TOKEN_COOKIE, path: '/' });
  response.cookies.delete({ name: REFRESH_TOKEN_COOKIE, path: '/' });
  return response;
}

export async function proxy(request: NextRequest) {
  if (
    request.nextUrl.pathname.startsWith('/signin') ||
    request.nextUrl.pathname.startsWith('/forgot-password') ||
    request.nextUrl.pathname.startsWith('/reset-password')
  ) {
    return NextResponse.next();
  }

  const refreshToken = request.cookies.get(REFRESH_TOKEN_COOKIE)?.value;
  if (!refreshToken) {
    return NextResponse.next();
  }

  const accessToken = request.cookies.get(ACCESS_TOKEN_COOKIE)?.value;
  if (!needsAccessRefresh(accessToken)) {
    return NextResponse.next();
  }

  try {
    const nextAccessToken = await requestNewAccessToken(refreshToken);
    const response = NextResponse.next({
      request: {
        headers: buildRequestHeadersWithAccessToken(request, nextAccessToken)
      }
    });

    response.cookies.set(ACCESS_TOKEN_COOKIE, nextAccessToken, {
      ...AUTH_COOKIE_BASE_OPTIONS,
      maxAge: ACCESS_TOKEN_MAX_AGE
    });

    return response;
  } catch (error) {
    const isRefreshAuthError =
      error instanceof RefreshAuthError ||
      (error instanceof Error && error.name === 'RefreshAuthError');

    if (!isRefreshAuthError) {
      return NextResponse.next();
    }

    return clearAuthAndRedirect(request);
  }
}

export const config = {
  matcher: ['/((?!api|_next/static|_next/image|favicon.ico|favicon-16x16.png|favicon-32x32.png|apple-touch-icon.png|icon-192.png|icon-512.png|icon-maskable-512.png|site.webmanifest|images/|storage/).*)']
};

import { cookies } from 'next/headers';
import { ACCESS_TOKEN_COOKIE, REFRESH_TOKEN_COOKIE } from '@/lib/constants';
import {
  ACCESS_TOKEN_MAX_AGE,
  AUTH_COOKIE_BASE_OPTIONS,
  REFRESH_TOKEN_MAX_AGE,
} from '@/lib/auth-cookie-options';

export async function getAccessToken() {
  const store = await cookies();
  return store.get(ACCESS_TOKEN_COOKIE)?.value;
}

export async function getRefreshToken() {
  const store = await cookies();
  return store.get(REFRESH_TOKEN_COOKIE)?.value;
}

export async function setAccessToken(access: string) {
  const store = await cookies();
  store.set(ACCESS_TOKEN_COOKIE, access, {
    ...AUTH_COOKIE_BASE_OPTIONS,
    maxAge: ACCESS_TOKEN_MAX_AGE,
  });
}

export async function setAuthTokens(access: string, refresh: string) {
  const store = await cookies();

  store.set(ACCESS_TOKEN_COOKIE, access, {
    ...AUTH_COOKIE_BASE_OPTIONS,
    maxAge: ACCESS_TOKEN_MAX_AGE,
  });

  store.set(REFRESH_TOKEN_COOKIE, refresh, {
    ...AUTH_COOKIE_BASE_OPTIONS,
    maxAge: REFRESH_TOKEN_MAX_AGE,
  });
}

export async function clearAuthCookies() {
  const store = await cookies();
  store.delete({ name: ACCESS_TOKEN_COOKIE, path: '/' });
  store.delete({ name: REFRESH_TOKEN_COOKIE, path: '/' });
}

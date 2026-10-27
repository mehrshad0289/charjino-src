'use server';

import { AUTH_COPY } from '@/data/auth';
import { clearAuthCookies, getAccessToken, getRefreshToken, setAccessToken, setAuthTokens } from '@/lib/auth-cookies';
import { apiPaths } from '@/lib/api-paths';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { requestNewAccessToken, RefreshAuthError } from '@/lib/request-access-token';
import type { AdminLoginResponse, AuthUser, SignInResult } from '@/types/auth';
import { redirect } from 'next/navigation';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function toActionMessage(error: unknown) {
  if (error instanceof ApiError && error.message) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return 'درخواست انجام نشد';
}

export async function requestAdminPasswordReset(email: string) {
  const trimmed = email.trim().toLowerCase();
  if (!trimmed) return { ok: false as const, message: AUTH_COPY.requiredFields };
  if (!EMAIL_PATTERN.test(trimmed)) return { ok: false as const, message: AUTH_COPY.invalidEmail };
  try {
    const data = await serverFetch<{ message?: string }>(apiPaths.adminForgotPassword, {
      method: 'POST',
      auth: false,
      body: JSON.stringify({ email: trimmed })
    });
    return { ok: true as const, message: data.message || AUTH_COPY.forgotSent };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function resetAdminPassword(email: string, token: string, password: string) {
  const trimmed = email.trim().toLowerCase();
  if (!trimmed || !token || !password) return { ok: false as const, message: AUTH_COPY.requiredFields };
  if (!EMAIL_PATTERN.test(trimmed)) return { ok: false as const, message: AUTH_COPY.invalidEmail };
  if (password.length < 8) return { ok: false as const, message: 'رمز باید حداقل ۸ کاراکتر باشد.' };
  try {
    const data = await serverFetch<{ message?: string }>(apiPaths.adminResetPassword, {
      method: 'POST',
      auth: false,
      body: JSON.stringify({ email: trimmed, token, password })
    });
    return { ok: true as const, message: data.message || AUTH_COPY.resetSuccess };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function signIn(email: string, password: string, remember: boolean): Promise<SignInResult> {
  const trimmed = email.trim().toLowerCase();
  if (!trimmed || !password) {
    return { ok: false, message: AUTH_COPY.requiredFields };
  }
  if (!EMAIL_PATTERN.test(trimmed)) {
    return { ok: false, message: AUTH_COPY.invalidEmail };
  }

  try {
    const data = await serverFetch<AdminLoginResponse>(apiPaths.adminLogin, {
      method: 'POST',
      auth: false,
      body: JSON.stringify({ email: trimmed, password })
    });

    if (!data.access_token || !data.user?.email) {
      return { ok: false, message: 'درخواست انجام نشد' };
    }

    if (data.refresh_token) {
      await setAuthTokens(data.access_token, data.refresh_token);
    } else {
      await setAccessToken(data.access_token);
    }

    return { ok: true, user: { id: data.user.id, email: data.user.email, role: 'admin' } };
  } catch (error) {
    return { ok: false, message: toActionMessage(error) };
  }
}

export async function refreshAccessToken(): Promise<void> {
  const refreshToken = await getRefreshToken();

  if (!refreshToken) {
    throw new ApiError('توکن بازیابی موجود نیست', 401);
  }

  try {
    const accessToken = await requestNewAccessToken(refreshToken);
    await setAccessToken(accessToken);
  } catch (error) {
    const isRefreshAuthError =
      error instanceof RefreshAuthError ||
      (error instanceof Error && error.name === 'RefreshAuthError');
    if (isRefreshAuthError) {
      await clearAuthCookies();
    }
    throw error;
  }
}

export async function logout() {
  try {
    await serverFetch<{ message: string }>(apiPaths.logout, { method: 'POST' });
  } catch {
    // Cookieهای محلی حتی اگر بک‌اند خطا بدهد باید پاک شوند.
  } finally {
    await clearAuthCookies();
    redirect('/signin');
  }
}

export async function getSessionUser(): Promise<AuthUser | null> {
  const token = await getAccessToken();
  if (!token) return null;

  try {
    const user = await serverFetch<AuthUser & { email?: string | null; role?: string }>(apiPaths.me);
    if (user.role !== 'admin' || !user.email) return null;
    return { id: user.id, email: user.email, role: 'admin' };
  } catch {
    return null;
  }
}

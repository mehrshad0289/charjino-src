'use server';

import { revalidatePath } from 'next/cache';
import {
  clearAuthCookies,
  getAccessToken,
  getRefreshToken,
  setAccessToken,
  setAuthTokens,
} from '@/lib/auth-cookies';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { requestNewAccessToken, RefreshAuthError } from '@/lib/request-access-token';
import { normalizeIranianMobile } from '@/services/validation.service';
import type { AuthUser, VerifyOtpResponse } from '@/types/auth';

type RequestOtpResult = {
  message: string;
};

function toActionMessage(error: unknown) {
  if (error instanceof ApiError && error.message) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return 'درخواست انجام نشد';
}

function isRefreshAuthError(error: unknown) {
  return (
    error instanceof RefreshAuthError ||
    (error instanceof Error && error.name === 'RefreshAuthError')
  );
}

export async function requestOtp(rawPhone: string) {
  try {
    const phoneNumber = normalizeIranianMobile(rawPhone);
    if (!/^09\d{9}$/.test(phoneNumber)) {
      return { ok: false as const, message: 'شماره همراه معتبر نیست.' };
    }
    const data = await serverFetch<RequestOtpResult>('/auth/request-otp', {
      method: 'POST',
      auth: false,
      body: JSON.stringify({ phoneNumber }),
    });

    return { ok: true as const, message: data.message };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function verifyOtp(rawPhone: string, code: string) {
  try {
    const phoneNumber = normalizeIranianMobile(rawPhone);
    if (!/^09\d{9}$/.test(phoneNumber)) {
      return { ok: false as const, message: 'شماره همراه معتبر نیست.' };
    }
    const data = await serverFetch<VerifyOtpResponse>('/auth/verify-otp', {
      method: 'POST',
      auth: false,
      body: JSON.stringify({ phoneNumber, code }),
    });

    if (!data.access_token || !data.refresh_token) {
      return { ok: false as const, message: 'درخواست انجام نشد' };
    }

    await setAuthTokens(data.access_token, data.refresh_token);

    return { ok: true as const, user: data.user };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
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
    if (isRefreshAuthError(error)) {
      await clearAuthCookies();
    }

    throw error;
  }
}

export async function logout() {
  try {
    await serverFetch<{ message: string }>('/auth/logout', {
      method: 'POST',
    });
  } catch {
    // Cookieهای محلی حتی اگر بک‌اند خطا بدهد باید پاک شوند.
  } finally {
    await clearAuthCookies();
  }
}

export async function getSessionUser(): Promise<AuthUser | null> {
  const accessToken = await getAccessToken();
  if (!accessToken) return null;

  try {
    return await serverFetch<AuthUser>('/auth/me');
  } catch {
    return null;
  }
}

export async function updateDisplayName(rawName: string) {
  const name = rawName.trim();
  if (name.length < 2) {
    return { ok: false as const, message: 'نام نمایشی کوتاه است.' };
  }
  if (name.length > 40) {
    return { ok: false as const, message: 'نام نمایشی طولانی است.' };
  }

  try {
    const user = await serverFetch<AuthUser>('/auth/profile', {
      method: 'PATCH',
      body: JSON.stringify({ name }),
    });
    revalidatePath('/profile');
    revalidatePath('/profile/account');
    return { ok: true as const, user };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

'use client';

import { useCallback, useState } from 'react';
import { getSessionUser as fetchSessionUser, logout, requestOtp, verifyOtp } from '@/actions/auth.actions';
import { notify } from '@/lib/toast';
import type { AuthUser } from '@/types/auth';

export type AuthStep = 'phone' | 'otp';

function toUiError(error: unknown) {
  if (error instanceof Error && error.message.trim()) {
    return error.message;
  }

  return 'درخواست انجام نشد';
}

export function useAuth() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [step, setStep] = useState<AuthStep>('phone');
  const [user, setUser] = useState<AuthUser | null>(null);

  const handleRequestOtp = useCallback(async (phone: string) => {
    setLoading(true);
    setError(null);

    try {
      const result = await requestOtp(phone);
      if (!result.ok) {
        setError(result.message);
        notify.error(result.message);
        return false;
      }

      setStep('otp');
      notify.success('کد ورود ارسال شد.');
      return true;
    } catch (caught) {
      const text = toUiError(caught);
      setError(text);
      notify.error(text);
      return false;
    } finally {
      setLoading(false);
    }
  }, []);

  const handleVerifyOtp = useCallback(async (phone: string, code: string) => {
    setLoading(true);
    setError(null);

    try {
      const result = await verifyOtp(phone, code);
      if (!result.ok) {
        setError(result.message);
        notify.error(result.message);
        return false;
      }

      setUser(result.user);
      notify.success('ورود انجام شد.');
      return true;
    } catch (caught) {
      const text = toUiError(caught);
      setError(text);
      notify.error(text);
      return false;
    } finally {
      setLoading(false);
    }
  }, []);

  const handleGetSessionUser = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const sessionUser = await fetchSessionUser();
      setUser(sessionUser);
      return sessionUser;
    } catch (caught) {
      const text = toUiError(caught);
      setError(text);
      notify.error(text);
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  const handleLogout = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      await logout();
      setUser(null);
      setStep('phone');
      return true;
    } catch (caught) {
      setUser(null);
      setStep('phone');
      const text = toUiError(caught);
      setError(text);
      notify.error(text);
      return false;
    } finally {
      setLoading(false);
    }
  }, []);

  return {
    loading,
    error,
    step,
    user,
    setStep,
    requestOtp: handleRequestOtp,
    verifyOtp: handleVerifyOtp,
    getSessionUser: handleGetSessionUser,
    logout: handleLogout,
    clearError: () => setError(null),
  };
}

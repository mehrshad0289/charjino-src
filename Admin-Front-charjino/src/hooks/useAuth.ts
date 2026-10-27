'use client';

import { signIn, logout } from '@/actions/auth.actions';
import { notify } from '@/lib/toast';
import { useRouter } from 'next/navigation';
import { useCallback, useState } from 'react';

function toUiError(error: unknown) {
  if (error instanceof Error && error.message.trim()) {
    return error.message;
  }
  return 'درخواست انجام نشد';
}

export function useAuth() {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSignIn = useCallback(
    async (email: string, password: string, remember: boolean) => {
      setLoading(true);
      setError(null);

      try {
        const result = await signIn(email, password, remember);
        if (!result.ok) {
          setError(result.message);
          notify.error(result.message);
          return false;
        }
        notify.success('ورود به پنل انجام شد.');
        router.push('/');
        router.refresh();
        return true;
      } catch (caught) {
        const text = toUiError(caught);
        setError(text);
        notify.error(text);
        return false;
      } finally {
        setLoading(false);
      }
    },
    [router]
  );

  const handleLogout = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      await logout();
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

  return {
    loading,
    error,
    signIn: handleSignIn,
    logout: handleLogout,
    clearError: () => setError(null)
  };
}

'use client';

import { useRef, useState } from 'react';

import { proxyFazerCards } from '@/actions/admin.actions';
import { useModal } from '@/hooks/useModal';
import { useToastMessage } from '@/hooks/use-toast-message';
import type { FcMethod } from '@/lib/fazer-cards-endpoints';
import { toFaDigits } from '@/lib/fa';

export type FcLastCall = {
  method: string;
  path: string;
  ok: boolean;
  message?: string;
  payload?: unknown;
};

export type InvokeOpts = {
  query?: Record<string, string>;
  body?: Record<string, unknown>;
  needsConfirm?: boolean;
  confirmTitle?: string;
  confirmMessage?: string;
};

function callKey(method: string, path: string) {
  return `${method}:${path}`;
}

export function useFazerCardsProxy() {
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [, setMessage] = useToastMessage();
  const [last, setLast] = useState<FcLastCall | null>(null);
  const confirmModal = useModal();
  const [confirmCopy, setConfirmCopy] = useState({
    title: 'تأیید عملیات خطرناک',
    message: 'این کار از والت دلاری کم می‌کند یا تنظیم حساس را عوض می‌کند.'
  });
  const pending = useRef<null | (() => Promise<unknown>)>(null);

  async function send(
    method: FcMethod,
    path: string,
    opts?: { query?: Record<string, string>; body?: Record<string, unknown> }
  ) {
    setBusyKey(callKey(method, path));
    try {
      const result = await proxyFazerCards(method, path, opts);
      if (!result.ok) {
        setLast({ method, path, ok: false, message: result.message });
        setMessage({ variant: 'error', text: result.message });
        return null;
      }
      const data = result.data.data as Record<string, unknown> | undefined;
      const warning = typeof data?.warning === 'string' ? data.warning : null;
      const unsupported = data?.validation === 'unsupported';
      setLast({ method, path, ok: true, payload: result.data });
      setMessage({
        variant: unsupported ? 'warning' : 'success',
        text: unsupported
          ? warning || 'اعتبارسنجی برای این دسته پشتیبانی نمی‌شود.'
          : `${method} /${path} · HTTP ${toFaDigits(result.data.status)} · ${toFaDigits(result.data.latencyMs)}ms`
      });
      return result.data;
    } finally {
      setBusyKey(null);
    }
  }

  async function invoke(method: FcMethod, path: string, opts?: InvokeOpts) {
    const run = () =>
      send(method, path, {
        query: {
          ...(opts?.query ?? {}),
          ...(method === 'DELETE' && opts?.needsConfirm ? { confirm: 'true' } : {})
        },
        body:
          method === 'GET' || method === 'DELETE'
            ? undefined
            : { ...(opts?.body ?? {}), ...(opts?.needsConfirm ? { confirm: true } : {}) }
      });

    if (!opts?.needsConfirm) {
      return run();
    }
    pending.current = run;
    setConfirmCopy({
      title: opts.confirmTitle || 'تأیید عملیات خطرناک',
      message:
        opts.confirmMessage ||
        'این کار از والت دلاری کم می‌کند، پلن را عوض می‌کند، یا secret وب‌هوک / ورود FunPay را تغییر می‌دهد.'
    });
    confirmModal.openModal();
    return null;
  }

  async function confirmPending() {
    const run = pending.current;
    pending.current = null;
    confirmModal.closeModal();
    if (run) await run();
  }

  return {
    busy: busyKey !== null,
    busyKey,
    isBusy: (method: FcMethod, path: string) => busyKey === callKey(method, path),
    last,
    invoke,
    confirmPending,
    confirmModal,
    confirmCopy
  };
}

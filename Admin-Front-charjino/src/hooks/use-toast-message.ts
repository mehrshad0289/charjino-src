'use client';

import { useCallback, useEffect, useState } from 'react';
import { notify } from '@/lib/toast';

export type FlashVariant = 'success' | 'error' | 'warning' | 'info';

export type FlashMessage = {
  variant: FlashVariant;
  text: string;
};

export function useToastMessage(initial: FlashMessage | null = null) {
  const [message, setState] = useState<FlashMessage | null>(initial);

  useEffect(() => {
    if (!initial) return;
    notify[initial.variant](initial.text);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const setMessage = useCallback((next: FlashMessage | null, options?: { toast?: boolean }) => {
    setState(next);
    if (!next || options?.toast === false) return;
    notify[next.variant](next.text);
  }, []);

  return [message, setMessage] as const;
}

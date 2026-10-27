'use client';

import { useCallback, useState } from 'react';

export function useBusyAction() {
  const [key, setKey] = useState<string | null>(null);

  const run = useCallback(async <T,>(action: string, fn: () => Promise<T>) => {
    setKey(action);
    try {
      return await fn();
    } finally {
      setKey(null);
    }
  }, []);

  return {
    key,
    busy: key !== null,
    is: (action: string) => key === action,
    run
  };
}

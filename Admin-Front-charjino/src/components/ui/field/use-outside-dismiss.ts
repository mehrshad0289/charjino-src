'use client';

import { useEffect, useRef, type RefObject } from 'react';

export function useOutsideDismiss(open: boolean, rootRef: RefObject<HTMLElement | null>, onDismiss: () => void) {
  const onDismissRef = useRef(onDismiss);
  onDismissRef.current = onDismiss;

  useEffect(() => {
    if (!open) return;

    function handlePointerDown(event: PointerEvent) {
      if (rootRef.current?.contains(event.target as Node)) return;
      onDismissRef.current();
    }

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') onDismissRef.current();
    }

    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [open, rootRef]);
}

'use client';

import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Button, CloseIcon } from '@/components/ui';
import { lockPageScroll } from '@/components/ui/drawer/lock-page-scroll';

function focusablesOf(root: HTMLElement) {
  return Array.from(
    root.querySelectorAll<HTMLElement>(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((node) => !node.hasAttribute('hidden') && node.tabIndex !== -1);
}

function restoreBuyGuide() {
  const section = document.getElementById('buy-guide');
  const slot = document.getElementById('buy-guide-slot');
  if (section && slot && section.parentElement !== slot) {
    slot.appendChild(section);
  }
}

export function BuyGuideTrigger({ title = 'راهنمای خرید' }: { title?: string }) {
  const [open, setOpen] = useState(false);
  const [ready, setReady] = useState(false);
  const hostRef = useRef<HTMLDivElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const openerRef = useRef<HTMLElement | null>(null);
  const titleId = useId();

  useEffect(() => {
    setReady(true);
  }, []);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('guide') || window.location.hash === '#buy-guide') {
      setOpen(true);
    }
  }, []);

  function close() {
    restoreBuyGuide();
    setOpen(false);
  }

  useLayoutEffect(() => {
    if (!open) return;
    const section = document.getElementById('buy-guide');
    const host = hostRef.current;
    if (!section || !host) return;
    host.appendChild(section);
    return () => {
      restoreBuyGuide();
    };
  }, [open]);

  useEffect(() => {
    if (!open) return;
    openerRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const unlock = lockPageScroll();
    const focusRoot = dialogRef.current ?? hostRef.current;
    if (focusRoot) {
      focusablesOf(focusRoot)[0]?.focus();
    }

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        close();
        return;
      }
      if (event.key !== 'Tab' || !focusRoot) return;
      const nodes = focusablesOf(focusRoot);
      if (nodes.length === 0) return;
      const firstNode = nodes[0];
      const lastNode = nodes[nodes.length - 1];
      if (event.shiftKey && document.activeElement === firstNode) {
        event.preventDefault();
        lastNode.focus();
      } else if (!event.shiftKey && document.activeElement === lastNode) {
        event.preventDefault();
        firstNode.focus();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
      unlock();
      openerRef.current?.focus();
    };
  }, [open]);

  return (
    <>
      <Button type="button" variant="outline" fullWidth onClick={() => setOpen(true)}>
        {title}
      </Button>
      {ready && open
        ? createPortal(
            <div className="fixed inset-0 z-[80] flex items-end justify-center sm:items-center sm:p-4">
              <button
                type="button"
                aria-label="بستن راهنما"
                className="absolute inset-0 bg-black/50"
                onClick={close}
              />
              <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                className="relative z-[81] flex max-h-[92svh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl border border-border bg-card shadow-modal sm:rounded-2xl"
              >
                <div className="flex items-center justify-between border-b border-border/60 px-4 py-3">
                  <h2 id={titleId} className="text-sm font-semibold text-text">
                    {title}
                  </h2>
                  <button
                    type="button"
                    aria-label="بستن"
                    className="inline-flex size-8 items-center justify-center rounded-md text-text/70 hover:bg-text/5"
                    onClick={close}
                  >
                    <CloseIcon size={18} />
                  </button>
                </div>
                <div ref={hostRef} className="min-h-0 min-w-0 flex-1 overflow-y-auto overflow-x-hidden p-3" />
              </div>
            </div>,
            document.body,
          )
        : null}
    </>
  );
}

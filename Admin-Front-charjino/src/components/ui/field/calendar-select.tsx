'use client';

import { ChevronDownIcon } from '@/components/ui/icon';
import { useEffect, useRef, type ReactNode } from 'react';

import { useOutsideDismiss } from './use-outside-dismiss';

type CalendarSelectProps = {
  label: string;
  open: boolean;
  ariaLabel: string;
  onToggle: () => void;
  children: ReactNode;
};

export function CalendarSelect({ label, open, ariaLabel, onToggle, children }: CalendarSelectProps) {
  const wrapRef = useRef<HTMLDivElement>(null);
  useOutsideDismiss(open, wrapRef, onToggle);

  useEffect(() => {
    if (!open) return;
    const menu = wrapRef.current?.querySelector('[data-calendar-menu]');
    const active = menu?.querySelector<HTMLElement>('[data-active="true"]');
    if (!menu || !active) return;
    menu.scrollTop = active.offsetTop - menu.clientHeight / 2 + active.offsetHeight / 2;
  }, [open]);

  return (
    <div className='relative' ref={wrapRef}>
      <button
        type='button'
        className='flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-800 aria-expanded:border-brand-300 dark:border-gray-800 dark:bg-gray-900 dark:text-white/90'
        aria-label={ariaLabel}
        aria-expanded={open}
        onClick={onToggle}
      >
        {label}
        <ChevronDownIcon size={16} className={open ? 'rotate-180 text-gray-400' : 'text-gray-400'} />
      </button>
      {open ? (
        <div
          className='absolute inset-x-0 top-[calc(100%+8px)] z-30 max-h-55 min-w-full overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark'
          data-calendar-menu
        >
          {children}
        </div>
      ) : null}
    </div>
  );
}

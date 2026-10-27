'use client';

import { useEffect, useRef, type ReactNode } from 'react';
import { ChevronIcon } from '../icon';
import { MENU_CLASS } from './classNames';
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
    <div className="relative" ref={wrapRef}>
      <button
        type="button"
        className="flex h-8 cursor-pointer items-center gap-1.5 rounded-sm border border-border bg-card px-[10px] font-sans text-[13px] text-text aria-expanded:border-primary-600"
        aria-label={ariaLabel}
        aria-expanded={open}
        onClick={onToggle}
      >
        {label}
        <ChevronIcon size={12} up={open} />
      </button>
      {open ? (
        <div
          className={`${MENU_CLASS} inset-x-auto z-30 max-h-[220px] min-w-full overflow-y-auto`}
          data-calendar-menu
        >
          {children}
        </div>
      ) : null}
    </div>
  );
}

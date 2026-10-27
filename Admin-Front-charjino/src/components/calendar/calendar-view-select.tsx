'use client';

import { CALENDAR_VIEW_OPTIONS } from '@/data/calendar';
import { useClickOutside } from '@/hooks/useClickOutside';
import { cn } from '@/lib/cn';
import { useRef, useState } from 'react';
import { createPortal } from 'react-dom';

type CalendarViewSelectProps = {
  currentView: string;
  onViewChange: (viewKey: string) => void;
  portalNode: Element | null;
};

export function CalendarViewSelect({ currentView, onViewChange, portalNode }: CalendarViewSelectProps) {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useClickOutside(dropdownRef, () => setIsOpen(false));

  const activeOption =
    CALENDAR_VIEW_OPTIONS.find((view) => view.key === currentView) ||
    CALENDAR_VIEW_OPTIONS.find((view) => view.key === 'dayGridMonth') ||
    CALENDAR_VIEW_OPTIONS[1];

  function handleSelect(viewKey: string) {
    onViewChange(viewKey);
    setIsOpen(false);
  }

  if (!portalNode) return null;

  return createPortal(
    <div className='calendar-view-dropdown relative' ref={dropdownRef}>
      <button
        type='button'
        onClick={() => setIsOpen((prev) => !prev)}
        className='calendar-view-btn flex h-9 w-full min-w-18 items-center justify-center gap-1 rounded-lg border border-gray-300 ps-2.5 pe-1.5 text-xs font-medium text-gray-700 shadow-xs sm:min-w-20 sm:gap-1.5 sm:ps-3 sm:pe-2 sm:text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400'
        aria-expanded={isOpen}
        aria-haspopup='listbox'
      >
        <span className='calendar-view-label'>{activeOption.label}</span>
        <svg
          xmlns='http://www.w3.org/2000/svg'
          width='16'
          height='16'
          viewBox='0 0 24 24'
          fill='none'
          stroke='currentColor'
          strokeWidth='2'
          strokeLinecap='round'
          strokeLinejoin='round'
          className={cn(
            'calendar-view-chevron h-4 w-4 transition-transform duration-200 sm:h-4.5 sm:w-4.5',
            isOpen ? 'rotate-180' : ''
          )}
        >
          <path d='m6 9 6 6 6-6' />
        </svg>
      </button>

      {isOpen ? (
        <div className='calendar-view-menu absolute inset-e-0 z-50 mt-1.5 w-36 max-w-[calc(100vw-32px)] space-y-0.5 rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg sm:w-38 dark:border-gray-700 dark:bg-gray-900'>
          {CALENDAR_VIEW_OPTIONS.map((view) => (
            <button
              key={view.key}
              type='button'
              data-view-key={view.key}
              onClick={() => handleSelect(view.key)}
              className={cn(
                'calendar-view-option w-full rounded-lg px-2.5 py-1.5 text-start text-xs text-gray-700 hover:bg-gray-100 sm:text-sm dark:text-gray-300 dark:hover:bg-white/5',
                currentView === view.key ? 'bg-gray-100 font-medium dark:bg-white/5' : 'font-normal'
              )}
            >
              {view.label}
            </button>
          ))}
        </div>
      ) : null}
    </div>,
    portalNode
  );
}

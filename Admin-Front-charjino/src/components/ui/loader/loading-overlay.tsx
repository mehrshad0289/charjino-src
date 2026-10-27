'use client';

import { Spinner } from './spinner';

type LoadingOverlayProps = {
  open: boolean;
  label?: string;
};

export function LoadingOverlay({ open, label = 'لطفاً صبر کن...' }: LoadingOverlayProps) {
  if (!open) return null;

  return (
    <div className='fixed inset-0 z-[80] flex items-center justify-center bg-black/45 backdrop-blur-[2px]'>
      <div className='flex flex-col items-center gap-3 rounded-2xl bg-white px-8 py-7 shadow-theme-lg dark:bg-gray-900'>
        <Spinner size={48} />
        <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{label}</p>
      </div>
    </div>
  );
}

'use client';

import { Spinner } from './spinner';

type LoadingOverlayProps = {
  open: boolean;
  label?: string;
};

export function LoadingOverlay({ open, label = 'لطفاً صبر کن...' }: LoadingOverlayProps) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[80] flex items-center justify-center bg-black/45 backdrop-blur-[2px]">
      <div className="flex flex-col items-center gap-3 rounded-2xl bg-card px-8 py-7 shadow-modal ring-1 ring-border">
        <Spinner size={48} />
        <p className="text-sm font-medium text-text">{label}</p>
      </div>
    </div>
  );
}

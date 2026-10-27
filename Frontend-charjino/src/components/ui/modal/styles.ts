export const MODAL_OVERLAY =
  'fixed inset-0 z-[70] border-0 bg-black/5 p-0 backdrop-blur-[2px] transition-opacity duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] motion-reduce:transition-none';

export const MODAL_SHELL =
  'pointer-events-none fixed inset-0 z-[71] flex justify-center px-4 pt-[79px]';

export const MODAL_PANEL =
  'pointer-events-auto flex w-full max-w-2xl max-h-[min(90svh,640px)] flex-col overflow-hidden rounded-xl border border-border bg-card shadow-modal transition-[opacity,transform] duration-300 ease-out motion-reduce:transition-none';

export const MODAL_HEADER =
  'flex h-12 shrink-0 items-center justify-between border-b border-border/40 px-4';

export const MODAL_TITLE =
  'font-sans text-sm font-semibold leading-none text-text';

export const MODAL_CLOSE =
  'inline-flex size-6 shrink-0 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-text';

export const MODAL_BODY =
  'flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain p-2';

export const MODAL_FOOTER =
  'flex shrink-0 flex-col gap-3 border-t border-border/40 p-4';

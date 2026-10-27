export const DRAWER_OVERLAY =
  'fixed inset-0 z-[60] border-0 bg-black/40 p-0 transition-opacity duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] motion-reduce:transition-none';

export const DRAWER_PANEL =
  'fixed inset-y-0 right-0 z-[61] flex h-svh w-full flex-col bg-paper shadow-drawer transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] motion-reduce:transition-none lg:w-[330px] lg:max-w-[330px]';

export const DRAWER_HEADER =
  'flex h-[67px] shrink-0 items-center justify-between border-b border-gray-200 px-4 pt-5 pb-4';

export const DRAWER_TITLE =
  'font-sans text-xl font-bold leading-none text-gray-800';

export const DRAWER_CLOSE =
  'inline-flex size-6 shrink-0 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-gray-900';

export const DRAWER_BODY =
  'flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto overscroll-contain p-5';

export const DRAWER_FOOTER =
  'flex shrink-0 lg:flex-col gap-3 border-t border-gray-200 p-5';

export type SwitchSize = 'S' | 'M' | 'L';

export const SWITCH_TRACK: Record<SwitchSize, string> = {
  S: 'h-5 w-9',
  M: 'h-[22px] w-10',
  L: 'h-6 w-11',
};

export const SWITCH_THUMB: Record<SwitchSize, string> = {
  S: 'size-4',
  M: 'size-[18px]',
  L: 'size-5',
};

export const SWITCH_THUMB_ON: Record<SwitchSize, string> = {
  S: 'translate-x-4',
  M: 'translate-x-[18px]',
  L: 'translate-x-5',
};

export const SWITCH_TRACK_ALIGN: Record<SwitchSize, string> = {
  S: 'mt-0.5',
  M: 'mt-px',
  L: 'mt-0',
};

export const SWITCH_ROW = 'inline-flex items-start gap-3';

export const SWITCH_TEXT = 'flex min-w-0 flex-col gap-0.5';

export const SWITCH_TITLE =
  'font-sans text-base font-normal leading-6 tracking-normal text-gray-900';

export const SWITCH_COPY =
  'font-sans text-sm font-normal leading-5 tracking-normal text-gray-500';

import type { FieldState } from './types';

export function fieldClass(state: FieldState) {
  return [
    'relative flex w-full max-w-full flex-col gap-2 text-start',
    state === 'disabled' ? 'pointer-events-none' : '',
  ]
    .filter(Boolean)
    .join(' ');
}

export function inputClass(state: FieldState, filled = false, extra: string[] = []) {
  const canInteract = state !== 'disabled' && state !== 'error';
  const classes = [
    'group/input flex h-[38px] w-full min-w-0 cursor-text items-center justify-between box-border rounded-md border-0 bg-gray-300 px-3 py-2 font-sans text-xs font-normal leading-none text-text text-start outline-none',
    canInteract ? 'focus-within:ring-1 focus-within:ring-primary-600' : '',
    ...extra,
  ];
  if (state === 'hover') classes.push('ring-1 ring-gray-400');
  if (state === 'disabled') classes.push('bg-gray-200 text-gray-400');
  if (state === 'error') classes.push('ring-1 ring-error-500');
  if (filled) classes.push('text-text');
  return classes.join(' ');
}

export function phoneRowClass(state: FieldState) {
  const classes = ['group/phone flex w-full min-w-0 flex-row gap-2 [direction:ltr]'];
  if (state === 'hover') classes.push('[&>*]:ring-1 [&>*]:ring-gray-400');
  if (state === 'disabled') {
    classes.push('[&>*]:bg-gray-200 [&>*]:text-gray-400');
  }
  if (state === 'error') classes.push('[&>*]:ring-1 [&>*]:ring-error-500');
  return classes.join(' ');
}

export const INPUT_INNER_CLASS =
  'w-full border-0 bg-transparent [font:inherit] text-inherit text-start outline-none placeholder:text-text/55';

export const CLEAR_BUTTON_CLASS =
  'flex size-6 shrink-0 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-text disabled:text-gray-400';

export const WRAP_CLASS = 'relative w-full min-w-0';

export const MENU_SURFACE_CLASS =
  'z-[80] rounded-xl border border-border bg-card p-1 shadow-popover';

export const MENU_CLASS = `absolute inset-x-0 top-[calc(100%+8px)] ${MENU_SURFACE_CLASS}`;

export const MENU_LIST_CLASS = 'min-h-0 flex-1 overflow-y-auto overscroll-contain py-0.5';

export const MENU_SEARCH_CLASS =
  'mb-1 flex h-9 w-full shrink-0 items-center gap-2 rounded-lg border border-border bg-card px-2.5 text-sm text-text';

export const MENU_ITEM_CLASS =
  'flex w-full cursor-pointer items-center gap-2 rounded-lg border-0 bg-transparent px-3 py-2 font-sans text-sm text-text text-start hover:bg-text/8';

export const MENU_ITEM_ACTIVE_CLASS = 'bg-text/8';

export function shopControlClass(filled = false, extra: string[] = []) {
  return [
    'group/input flex h-11 w-full min-w-0 cursor-pointer items-center justify-between box-border rounded-md border border-border bg-card px-3 text-sm font-normal leading-none text-text text-start shadow-card outline-none',
    'focus-visible:border-primary-600 focus-visible:ring-2 focus-visible:ring-primary-600/20',
    filled ? 'text-text' : 'text-text/55',
    ...extra,
  ].join(' ');
}

export function shopTextareaClass(invalid = false, extra: string[] = []) {
  return [
    'min-h-[7.5rem] w-full resize-y rounded-xl border bg-card px-3 py-3 text-sm leading-7 text-text shadow-card outline-none placeholder:text-text/40',
    invalid
      ? 'border-error-500 focus:border-error-500 focus:ring-2 focus:ring-error-500/20'
      : 'border-border focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20',
    ...extra,
  ].join(' ');
}

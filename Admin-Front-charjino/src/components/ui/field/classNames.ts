import type { FieldState } from './types';

export function fieldClass() {
  return 'relative';
}

export function labelClass() {
  return 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
}

export function hintClass(state: FieldState) {
  if (state === 'error') return 'mt-1.5 text-xs text-error-500';
  if (state === 'success') return 'mt-1.5 text-xs text-success-500';
  return 'mt-1.5 text-xs text-gray-500 dark:text-gray-400';
}

export function inputClass(state: FieldState, extra = '') {
  const base =
    'h-11 w-full rounded-lg border appearance-none ps-4 pe-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';

  if (state === 'disabled') {
    return `${base} text-gray-500 border-gray-300 opacity-40 bg-gray-100 cursor-not-allowed dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700 ${extra}`;
  }

  if (state === 'error') {
    return `${base} border-error-500 focus:border-error-300 focus:ring-error-500/20 dark:text-error-400 dark:border-error-500 dark:focus:border-error-800 ${extra}`;
  }

  if (state === 'success') {
    return `${base} border-success-500 focus:border-success-300 focus:ring-success-500/20 dark:text-success-400 dark:border-success-500 dark:focus:border-success-800 ${extra}`;
  }

  const hover =
    state === 'hover'
      ? 'border-brand-300 ring-3 ring-brand-500/20'
      : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:focus:border-brand-800';

  return `${base} bg-transparent text-gray-800 dark:text-white/90 ${hover} ${extra}`;
}

export function textareaClass(state: FieldState, extra = '') {
  const base = `w-full rounded-lg border px-4 py-2.5 text-sm shadow-theme-xs focus:outline-hidden ${extra}`;

  if (state === 'disabled') {
    return `${base} bg-gray-100 opacity-50 text-gray-500 border-gray-300 cursor-not-allowed dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700`;
  }

  if (state === 'error') {
    return `${base} bg-transparent text-gray-400 border-gray-300 focus:border-error-300 focus:ring-3 focus:ring-error-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-error-800`;
  }

  return `${base} bg-transparent text-gray-400 border-gray-300 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800`;
}

export function selectClass(filled: boolean, extra = '') {
  return [
    'h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pe-11 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800',
    filled ? 'text-gray-800 dark:text-white/90' : 'text-gray-400 dark:text-gray-400',
    extra
  ]
    .filter(Boolean)
    .join(' ');
}

export const FILE_INPUT_CLASS =
  'focus:border-ring-brand-300 h-11 w-full overflow-hidden rounded-lg border border-gray-300 bg-transparent text-sm text-gray-500 shadow-theme-xs transition-colors file:me-5 file:border-collapse file:cursor-pointer file:rounded-s-lg file:border-0 file:border-e file:border-solid file:border-gray-200 file:bg-gray-50 file:py-3 file:ps-3.5 file:pe-3 file:text-sm file:text-gray-700 placeholder:text-gray-400 hover:file:bg-gray-100 focus:outline-hidden focus:file:ring-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:file:border-gray-800 dark:file:bg-white/3 dark:file:text-gray-400 dark:placeholder:text-gray-400';

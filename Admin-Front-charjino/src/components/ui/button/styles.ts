import type { ButtonSize, ButtonVariant } from './button-root';

const BASE_CLASSES =
  'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition';

const SIZE_CLASSES: Record<ButtonSize, string> = {
  sm: 'px-4 py-3 text-sm',
  md: 'px-5 py-3.5 text-sm'
};

const VARIANT_CLASSES: Record<ButtonVariant, string> = {
  primary: 'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300',
  outline:
    'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/3 dark:hover:text-gray-300'
};

type ButtonClassOptions = {
  size: ButtonSize;
  variant: ButtonVariant;
  disabled: boolean;
  loading?: boolean;
  className: string;
};

export function buttonClassName({ size, variant, disabled, loading = false, className }: ButtonClassOptions) {
  return [
    BASE_CLASSES,
    SIZE_CLASSES[size],
    VARIANT_CLASSES[variant],
    disabled ? 'cursor-not-allowed opacity-50' : '',
    loading ? 'cursor-wait' : '',
    className
  ]
    .filter(Boolean)
    .join(' ');
}

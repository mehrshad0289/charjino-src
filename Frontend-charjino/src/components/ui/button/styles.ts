import type {
  ButtonColor,
  ButtonPreview,
  ButtonSize,
  ButtonStyle,
} from './button-root';

const BASE_CLASSES =
  'm-0 inline-flex w-auto max-w-full cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg border box-border font-sans font-semibold leading-none no-underline transition-all duration-300 ease-out focus:outline-none active:scale-95';

const SIZE_CLASSES: Record<ButtonSize, Record<ButtonStyle, string>> = {
  L: {
    fill: 'min-h-10 px-4 py-2 text-sm',
    outline: 'min-h-10 px-4 py-2 text-sm',
    light: 'min-h-10 px-4 py-2 text-sm',
    text: 'min-h-10 px-0 py-2 text-sm',
  },
  M: {
    fill: 'min-h-8 px-3 py-2 text-xs',
    outline: 'min-h-8 px-3 py-2 text-xs',
    light: 'min-h-8 px-3 py-2 text-xs',
    text: 'min-h-8 px-0 py-1 text-xs',
  },
};

const COLOR_CLASSES: Record<ButtonStyle, Record<ButtonColor, string>> = {
  fill: {
    primary: 'border-transparent bg-primary-600 text-white',
    secondary: 'border-transparent bg-secondary-600 text-white',
    error: 'border-transparent bg-error-600 text-white',
    success: 'border-transparent bg-success-700 text-white',
  },
  outline: {
    primary: 'border-border/60 bg-transparent text-text',
    secondary: 'border-border/60 bg-transparent text-text',
    error: 'border-error-600 bg-transparent text-error-500',
    success: 'border-success-700 bg-transparent text-success-500',
  },
  light: {
    primary: 'border-transparent bg-card text-text',
    secondary: 'border-transparent bg-card text-text',
    error: 'border-transparent bg-error-900 text-error-200',
    success: 'border-transparent bg-success-900 text-success-200',
  },
  text: {
    primary: 'border-transparent bg-transparent text-primary-500',
    secondary: 'border-transparent bg-transparent text-secondary-500',
    error: 'border-transparent bg-transparent text-error-500',
    success: 'border-transparent bg-transparent text-success-500',
  },
};

const HOVER_CLASSES: Record<ButtonStyle, Record<ButtonColor, string>> = {
  fill: {
    primary: 'hover:bg-primary-600/75',
    secondary: 'hover:bg-secondary-700',
    error: 'hover:bg-error-700',
    success: 'hover:bg-success-800',
  },
  outline: {
    primary: 'hover:bg-text/5',
    secondary: 'hover:bg-text/5',
    error: 'hover:border-error-700 hover:text-error-400',
    success: 'hover:border-success-800 hover:text-success-400',
  },
  light: {
    primary: 'hover:bg-text/5',
    secondary: 'hover:bg-text/5',
    error: '',
    success: '',
  },
  text: {
    primary: 'hover:text-primary-400',
    secondary: 'hover:text-secondary-400',
    error: 'hover:text-error-400',
    success: 'hover:text-success-400',
  },
};

const PREVIEW_HOVER_CLASSES: Record<ButtonStyle, Record<ButtonColor, string>> = {
  fill: {
    primary: 'bg-primary-600/75',
    secondary: 'bg-secondary-700',
    error: 'bg-error-700',
    success: 'bg-success-800',
  },
  outline: {
    primary: 'bg-surface/50',
    secondary: 'bg-surface/50',
    error: 'border-error-700 text-error-400',
    success: 'border-success-800 text-success-400',
  },
  light: { primary: '', secondary: '', error: '', success: '' },
  text: {
    primary: 'text-primary-400',
    secondary: 'text-secondary-400',
    error: 'text-error-400',
    success: 'text-success-400',
  },
};

type ButtonClassOptions = {
  color: ButtonColor;
  variant: ButtonStyle;
  size: ButtonSize;
  previewState: ButtonPreview;
  disabled: boolean;
  loading: boolean;
  fullWidth: boolean;
  className: string;
};

export function buttonClassName({
  color,
  variant,
  size,
  previewState,
  disabled,
  loading,
  fullWidth,
  className,
}: ButtonClassOptions) {
  const disabledClasses =
    variant === 'outline'
      ? '!border-gray-200 !bg-transparent !text-gray-400'
      : variant === 'text'
        ? '!text-gray-400'
        : '!bg-gray-200 !text-gray-400';

  return [
    BASE_CLASSES,
    SIZE_CLASSES[size][variant],
    COLOR_CLASSES[variant][color],
    !disabled && !loading ? HOVER_CLASSES[variant][color] : '',
    previewState === 'hover' ? PREVIEW_HOVER_CLASSES[variant][color] : '',
    disabled ? `${disabledClasses} cursor-not-allowed` : '',
    loading ? 'cursor-wait' : '',
    fullWidth ? 'w-full' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ');
}

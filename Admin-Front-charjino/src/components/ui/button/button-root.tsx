import type { ReactNode } from 'react';

import { buttonClassName } from './styles';

export type ButtonSize = 'sm' | 'md';
export type ButtonVariant = 'primary' | 'outline';

type ButtonProps = {
  children: ReactNode;
  size?: ButtonSize;
  variant?: ButtonVariant;
  startIcon?: ReactNode;
  endIcon?: ReactNode;
  disabled?: boolean;
  loading?: boolean;
  className?: string;
  type?: 'button' | 'submit';
  onClick?: () => void;
};

function ButtonSpinner() {
  return (
    <span
      className='inline-block size-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent'
      aria-hidden
    />
  );
}

export function Button({
  children,
  size = 'md',
  variant = 'primary',
  startIcon,
  endIcon,
  disabled = false,
  loading = false,
  className = '',
  type = 'button',
  onClick
}: ButtonProps) {
  const isDisabled = disabled || loading;
  return (
    <button
      type={type}
      className={buttonClassName({ size, variant, disabled: isDisabled, loading, className })}
      onClick={onClick}
      disabled={isDisabled}
      aria-busy={loading || undefined}
    >
      {loading ? <ButtonSpinner /> : startIcon ? <span className='flex items-center'>{startIcon}</span> : null}
      {children}
      {!loading && endIcon ? <span className='flex items-center'>{endIcon}</span> : null}
    </button>
  );
}

import type { ReactNode } from 'react';
import { fieldClass } from './classNames';
import type { FieldState } from './types';

type FieldShellProps = {
  as?: 'div' | 'label';
  label?: string;
  hint?: string;
  previewState?: FieldState;
  compact?: boolean;
  className?: string;
  footer?: ReactNode;
  children: ReactNode;
};

export function Field({
  as = 'div',
  label,
  hint,
  previewState = 'default',
  compact = false,
  className,
  footer,
  children,
}: FieldShellProps) {
  const Tag = as;
  const isError = previewState === 'error';
  const isDisabled = previewState === 'disabled';
  const labelClass = compact
    ? `text-xs font-medium leading-none ${isDisabled ? 'text-text/40' : 'text-text/70'}`
    : `h-5 text-base font-bold leading-none ${isDisabled ? 'text-gray-500' : 'text-text'}`;
  const hintClass = `min-h-[19px] text-xs font-normal leading-none ${
    isError ? 'text-error-600' : isDisabled ? 'text-gray-500' : 'text-gray-600'
  }`;

  return (
    <Tag className={`${fieldClass(previewState)} ${className ?? ''}`.trim()}>
      {label ? <span className={labelClass}>{label}</span> : null}
      {children}
      {footer ??
        (hint ? (
          <span className={hintClass}>{hint}</span>
        ) : null)}
    </Tag>
  );
}

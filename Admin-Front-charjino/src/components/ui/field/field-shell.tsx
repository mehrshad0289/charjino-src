import type { ReactNode } from 'react';

import { fieldClass, hintClass, labelClass } from './classNames';
import type { FieldState } from './types';

type FieldShellProps = {
  htmlFor?: string;
  label?: string;
  hint?: string;
  previewState?: FieldState;
  footer?: ReactNode;
  children: ReactNode;
};

export function Field({
  htmlFor,
  label,
  hint,
  previewState = 'default',
  footer,
  children
}: FieldShellProps) {
  return (
    <div className={fieldClass()}>
      {label ? (
        <label htmlFor={htmlFor} className={labelClass()}>
          {label}
        </label>
      ) : null}
      {children}
      {footer ?? (hint ? <p className={hintClass(previewState)}>{hint}</p> : null)}
    </div>
  );
}

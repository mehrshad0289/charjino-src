'use client';

import {
  CHECKBOX_BOX,
  CHECKBOX_OFF,
  CHECKBOX_ON,
} from './styles';

export type CheckboxState = 'off' | 'on' | 'mixed';

export type CheckboxProps = {
  checked?: boolean;
  mixed?: boolean;
  disabled?: boolean;
  onChange?: (checked: boolean) => void;
  className?: string;
  'aria-label'?: string;
};

function CheckMark() {
  return (
    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden>
      <path
        d="M2 6.2l2.6 2.6L10 3.2"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function MixedMark() {
  return (
    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden>
      <path
        d="M2.5 6h7"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
      />
    </svg>
  );
}

function CheckboxMark({ state }: { state: CheckboxState }) {
  const on = state !== 'off';

  return (
    <span
      aria-hidden
      className={[CHECKBOX_BOX, on ? CHECKBOX_ON : CHECKBOX_OFF].join(' ')}
    >
      {state === 'on' ? <CheckMark /> : null}
      {state === 'mixed' ? <MixedMark /> : null}
    </span>
  );
}

export function Checkbox({
  checked = false,
  mixed = false,
  disabled = false,
  onChange,
  className = '',
  'aria-label': ariaLabel,
}: CheckboxProps) {
  const state: CheckboxState = mixed ? 'mixed' : checked ? 'on' : 'off';

  if (!onChange) {
    return <CheckboxMark state={state} />;
  }

  return (
    <button
      type="button"
      role="checkbox"
      aria-checked={mixed ? 'mixed' : checked}
      aria-label={ariaLabel ?? 'انتخاب'}
      disabled={disabled}
      onClick={() => onChange(state !== 'on')}
      className={[
        'inline-flex border-0 bg-transparent p-0',
        'cursor-pointer focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-100 disabled:cursor-not-allowed disabled:opacity-50',
        className,
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <CheckboxMark state={state} />
    </button>
  );
}

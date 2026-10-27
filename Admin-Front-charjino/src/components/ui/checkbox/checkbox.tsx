'use client';

import { CHECKBOX_BOX, CHECKBOX_INPUT, CHECKBOX_LABEL, CHECKBOX_MARK } from './styles';

export type CheckboxProps = {
  label?: string;
  checked: boolean;
  className?: string;
  id?: string;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
};

export function Checkbox({
  label,
  checked,
  id,
  onChange,
  className = '',
  disabled = false
}: CheckboxProps) {
  return (
    <label className={`group flex cursor-pointer items-center gap-3 ${disabled ? 'cursor-not-allowed opacity-60' : ''}`}>
      <div className={CHECKBOX_BOX}>
        <input
          id={id}
          type='checkbox'
          className={`${CHECKBOX_INPUT} ${className}`}
          checked={checked}
          onChange={(event) => onChange(event.target.checked)}
          disabled={disabled}
        />
        {checked ? (
          <svg
            className={CHECKBOX_MARK}
            xmlns='http://www.w3.org/2000/svg'
            width='14'
            height='14'
            viewBox='0 0 14 14'
            fill='none'
          >
            <path
              d='M11.6666 3.5L5.24992 9.91667L2.33325 7'
              stroke='white'
              strokeWidth='1.94437'
              strokeLinecap='round'
              strokeLinejoin='round'
            />
          </svg>
        ) : null}
        {disabled ? (
          <svg
            className={CHECKBOX_MARK}
            xmlns='http://www.w3.org/2000/svg'
            width='14'
            height='14'
            viewBox='0 0 14 14'
            fill='none'
          >
            <path
              d='M11.6666 3.5L5.24992 9.91667L2.33325 7'
              stroke='#E4E7EC'
              strokeWidth='2.33333'
              strokeLinecap='round'
              strokeLinejoin='round'
            />
          </svg>
        ) : null}
      </div>
      {label ? <span className={CHECKBOX_LABEL}>{label}</span> : null}
    </label>
  );
}

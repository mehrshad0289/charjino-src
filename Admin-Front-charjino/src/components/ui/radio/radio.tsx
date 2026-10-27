'use client';

import { RADIO_DOT_DISABLED, RADIO_DOT_OFF, RADIO_DOT_ON, RADIO_LABEL } from './styles';

export type RadioProps = {
  id: string;
  name: string;
  value: string;
  checked: boolean;
  label: string;
  onChange: (value: string) => void;
  className?: string;
  disabled?: boolean;
};

export function Radio({
  id,
  name,
  value,
  checked,
  label,
  onChange,
  className = '',
  disabled = false
}: RadioProps) {
  return (
    <label
      htmlFor={id}
      className={`${RADIO_LABEL} ${
        disabled ? 'cursor-not-allowed text-gray-300 dark:text-gray-600' : 'text-gray-700 dark:text-gray-400'
      } ${className}`}
    >
      <input
        id={id}
        name={name}
        type='radio'
        value={value}
        checked={checked}
        onChange={() => {
          if (!disabled) onChange(value);
        }}
        className='sr-only'
        disabled={disabled}
      />
      <span
        className={`flex h-5 w-5 items-center justify-center rounded-full border-[1.25px] ${
          checked ? RADIO_DOT_ON : RADIO_DOT_OFF
        } ${disabled ? RADIO_DOT_DISABLED : ''}`}
      >
        <span className={`h-2 w-2 rounded-full bg-white ${checked ? 'block' : 'hidden'}`} />
      </span>
      {label}
    </label>
  );
}

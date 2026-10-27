'use client';

import { useState } from 'react';

import {
  SWITCH_ROW,
  SWITCH_THUMB,
  SWITCH_THUMB_ON,
  SWITCH_TRACK,
  type SwitchColor
} from './styles';

export type { SwitchColor };

export type SwitchProps = {
  label?: string;
  defaultChecked?: boolean;
  checked?: boolean;
  disabled?: boolean;
  onChange?: (checked: boolean) => void;
  color?: SwitchColor;
  className?: string;
};

export function Switch({
  label,
  defaultChecked = false,
  checked,
  disabled = false,
  onChange,
  color = 'blue',
  className = ''
}: SwitchProps) {
  const [internal, setInternal] = useState(defaultChecked);
  const isControlled = checked !== undefined;
  const on = isControlled ? checked : internal;

  function toggle() {
    if (disabled) return;
    const next = !on;
    if (!isControlled) setInternal(next);
    onChange?.(next);
  }

  const trackOn =
    color === 'blue' ? 'bg-brand-500' : 'bg-gray-800 dark:bg-white/10';
  const trackOff = 'bg-gray-200 dark:bg-white/10';
  const trackClass = disabled
    ? 'bg-gray-100 dark:bg-gray-800'
    : on
      ? trackOn
      : trackOff;

  return (
    <button
      type='button'
      role='switch'
      aria-checked={on}
      aria-label={label ?? 'سوییچ'}
      disabled={disabled}
      onClick={toggle}
      className={[
        SWITCH_ROW,
        'border-0 bg-transparent p-0',
        disabled ? 'cursor-not-allowed text-gray-400' : 'text-gray-700 dark:text-gray-400',
        className
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <span dir='ltr' aria-hidden className={`${SWITCH_TRACK} ${trackClass}`}>
        <span className={`${SWITCH_THUMB} ${on ? SWITCH_THUMB_ON : 'translate-x-0'}`} />
      </span>
      {label}
    </button>
  );
}

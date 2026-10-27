'use client';

import { useState } from 'react';
import { toFaDigits } from '@/lib/fa';
import { toIranianMobileNational } from '@/services/validation.service';
import { hotjarSuppress } from '@/lib/hotjar';
import { phoneRowClass } from './classNames';
import { Field } from './field-shell';
import type { FieldProps } from './types';

function formatPhone(digits: string) {
  const a = digits.slice(0, 3);
  const b = digits.slice(3, 6);
  const c = digits.slice(6, 10);
  return toFaDigits([a, b, c].filter(Boolean).join(' '));
}

export function PhoneField({
  label = 'شماره همراه',
  hint,
  previewState = 'default',
  value,
  name,
  onChange,
  onBlur,
}: FieldProps) {
  const [inner, setInner] = useState('');
  const digits = value === undefined ? inner : toIranianMobileNational(value);
  const isDisabled = previewState === 'disabled';
  const canInteract = previewState !== 'disabled' && previewState !== 'error';
  const segmentClass = [
    'h-[38px] box-border rounded-md border-0 bg-gray-300 px-3 py-2 text-xs leading-none text-text',
    canInteract ? 'group-focus-within/phone:ring-1 group-focus-within/phone:ring-primary-600' : '',
  ]
    .filter(Boolean)
    .join(' ');

  function setDigits(next: string) {
    const sliced = toIranianMobileNational(next);
    if (value === undefined) setInner(sliced);
    onChange?.(sliced);
  }

  return (
    <Field label={label} hint={hint} previewState={previewState}>
      <div className={phoneRowClass(previewState)}>
        <div
          className={`${segmentClass} flex shrink-0 items-center justify-center whitespace-nowrap`}
        >
          +۹۸
        </div>
        <input
          {...hotjarSuppress}
          className={`${segmentClass} min-w-0 flex-1 font-sans tracking-[0.30em] text-start outline-none [direction:ltr] ${
            digits ? 'text-text' : 'text-text/70'
          }`}
          name={name}
          inputMode="numeric"
          placeholder="--- --- ----"
          value={formatPhone(digits)}
          disabled={isDisabled}
          onBlur={onBlur}
          onChange={(event) => setDigits(event.target.value)}
        />
      </div>
    </Field>
  );
}

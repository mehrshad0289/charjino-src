'use client';

import { useState } from 'react';
import { formatTomanInWords, formatTomanNumber, sanitizePriceInput } from '@/lib/price';
import { CloseIcon } from '../icon';
import { CLEAR_BUTTON_CLASS, INPUT_INNER_CLASS, inputClass } from './classNames';
import { hotjarSuppress } from '@/lib/hotjar';
import { Field } from './field-shell';
import type { FieldProps } from './types';

type PriceFieldProps = FieldProps & {
  placeholder?: string;
};

export function PriceField({
  label = 'قیمت',
  hint = 'تومان',
  placeholder = '۰',
  previewState = 'default',
  value,
  name,
  onChange,
  onBlur,
}: PriceFieldProps) {
  const [inner, setInner] = useState('');
  const raw = sanitizePriceInput(value === undefined ? inner : value);
  const isDisabled = previewState === 'disabled';
  const words = formatTomanInWords(raw);

  function setRaw(next: string) {
    const sanitized = sanitizePriceInput(next);
    if (value === undefined) setInner(sanitized);
    onChange?.(sanitized);
  }

  return (
    <Field as="label" label={label} hint={previewState === 'error' ? hint : words || hint} previewState={previewState}>
      <div className={inputClass(previewState, Boolean(raw))}>
        <input
          {...hotjarSuppress}
          className={INPUT_INNER_CLASS}
          name={name}
          inputMode="numeric"
          placeholder={placeholder}
          value={formatTomanNumber(raw)}
          disabled={isDisabled}
          onBlur={onBlur}
          onChange={(event) => setRaw(event.target.value)}
        />
        {raw ? (
          <button
            type="button"
            className={CLEAR_BUTTON_CLASS}
            aria-label="پاک کردن"
            disabled={isDisabled}
            onClick={() => setRaw('')}
          >
            <CloseIcon />
          </button>
        ) : null}
      </div>
    </Field>
  );
}

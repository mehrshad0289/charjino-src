'use client';

import { useState, type InputHTMLAttributes } from 'react';
import { CloseIcon } from '../icon';
import { CLEAR_BUTTON_CLASS, INPUT_INNER_CLASS, inputClass } from './classNames';
import { hotjarSuppress } from '@/lib/hotjar';
import { Field } from './field-shell';
import type { FieldProps } from './types';

type TextFieldProps = FieldProps & {
  placeholder?: string;
  inputMode?: InputHTMLAttributes<HTMLInputElement>['inputMode'];
  autoComplete?: string;
  maxLength?: number;
};

export function TextField({
  label,
  hint,
  placeholder = '',
  previewState = 'default',
  value,
  onChange,
  onBlur,
  inputMode,
  autoComplete,
  maxLength,
}: TextFieldProps) {
  const [inner, setInner] = useState('');
  const text = value === undefined ? inner : value;
  const isDisabled = previewState === 'disabled';

  function setText(next: string) {
    if (value === undefined) setInner(next);
    onChange?.(next);
  }

  return (
    <Field as="label" label={label} hint={hint} previewState={previewState}>
      <div className={inputClass(previewState, Boolean(text))}>
        <input
          {...hotjarSuppress}
          className={INPUT_INNER_CLASS}
          placeholder={placeholder}
          value={text}
          disabled={isDisabled}
          inputMode={inputMode}
          autoComplete={autoComplete}
          maxLength={maxLength}
          onBlur={onBlur}
          onChange={(event) => setText(event.target.value)}
        />
        {text ? (
          <button
            type="button"
            className={CLEAR_BUTTON_CLASS}
            aria-label="پاک کردن"
            disabled={isDisabled}
            onClick={() => setText('')}
          >
            <CloseIcon />
          </button>
        ) : null}
      </div>
    </Field>
  );
}

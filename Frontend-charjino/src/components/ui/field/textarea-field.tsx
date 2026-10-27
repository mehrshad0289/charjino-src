'use client';

import { useState } from 'react';
import { CloseIcon } from '../icon';
import { CLEAR_BUTTON_CLASS, INPUT_INNER_CLASS, inputClass } from './classNames';
import { hotjarSuppress } from '@/lib/hotjar';
import { Field } from './field-shell';
import type { FieldProps } from './types';

type TextareaFieldProps = FieldProps & {
  placeholder?: string;
  rows?: number;
  maxLength?: number;
  className?: string;
};

export function TextareaField({
  label,
  hint,
  placeholder = '',
  previewState = 'default',
  value,
  onChange,
  onBlur,
  rows = 5,
  maxLength,
  className = '',
}: TextareaFieldProps) {
  const [inner, setInner] = useState('');
  const text = value === undefined ? inner : value;
  const isDisabled = previewState === 'disabled';

  function setText(next: string) {
    if (value === undefined) setInner(next);
    onChange?.(next);
  }

  return (
    <Field as="label" label={label} hint={hint} previewState={previewState}>
      <div
        className={inputClass(previewState, Boolean(text), [
          '!h-auto min-h-[108px] items-start py-3',
          className,
        ])}
      >
        <textarea
          {...hotjarSuppress}
          className={`${INPUT_INNER_CLASS} min-h-[80px] resize-none`}
          placeholder={placeholder}
          value={text}
          disabled={isDisabled}
          rows={rows}
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

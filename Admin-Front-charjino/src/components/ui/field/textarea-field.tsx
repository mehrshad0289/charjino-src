'use client';

import { useState } from 'react';

import { textareaClass } from './classNames';
import { Field } from './field-shell';
import type { FieldProps } from './types';

type TextareaFieldProps = FieldProps & {
  placeholder?: string;
  rows?: number;
  className?: string;
};

export function TextareaField({
  label = 'Description',
  hint,
  placeholder = 'متن را بنویسید',
  previewState = 'default',
  value,
  onChange,
  onBlur,
  rows = 3,
  className = ''
}: TextareaFieldProps) {
  const [inner, setInner] = useState('');
  const text = value === undefined ? inner : value;
  const isDisabled = previewState === 'disabled';

  function setText(next: string) {
    if (value === undefined) setInner(next);
    onChange?.(next);
  }

  return (
    <Field label={label} hint={hint} previewState={previewState}>
      <textarea
        className={textareaClass(previewState, className)}
        placeholder={placeholder}
        value={text}
        disabled={isDisabled}
        rows={rows}
        onBlur={onBlur}
        onChange={(event) => setText(event.target.value)}
      />
    </Field>
  );
}

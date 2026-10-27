'use client';

import { useState, type InputHTMLAttributes } from 'react';

import { inputClass } from './classNames';
import { Field } from './field-shell';
import type { FieldProps } from './types';

type TextFieldProps = FieldProps & {
  id?: string;
  placeholder?: string;
  type?: InputHTMLAttributes<HTMLInputElement>['type'];
  autoComplete?: string;
  className?: string;
  dir?: InputHTMLAttributes<HTMLInputElement>['dir'];
};

export function TextField({
  id,
  label = 'Input',
  hint,
  placeholder = '',
  previewState = 'default',
  value,
  onChange,
  onBlur,
  type = 'text',
  autoComplete,
  className = '',
  name,
  dir
}: TextFieldProps) {
  const [inner, setInner] = useState('');
  const text = value === undefined ? inner : value;
  const isDisabled = previewState === 'disabled';

  function setText(next: string) {
    if (value === undefined) setInner(next);
    onChange?.(next);
  }

  return (
    <Field htmlFor={id} label={label} hint={hint} previewState={previewState}>
      <input
        id={id}
        name={name}
        type={type}
        className={inputClass(previewState, className)}
        placeholder={placeholder}
        dir={dir}
        value={text}
        disabled={isDisabled}
        autoComplete={autoComplete}
        onBlur={onBlur}
        onChange={(event) => setText(event.target.value)}
      />
    </Field>
  );
}

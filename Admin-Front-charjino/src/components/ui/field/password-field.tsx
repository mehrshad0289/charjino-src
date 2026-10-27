'use client';

import { useState } from 'react';

import { EyeCloseIcon, EyeIcon } from '../icon';
import { inputClass } from './classNames';
import { Field } from './field-shell';
import type { FieldProps } from './types';

type PasswordFieldProps = FieldProps & {
  id?: string;
  placeholder?: string;
  autoComplete?: string;
};

export function PasswordField({
  id = 'pass',
  label = 'Password Input',
  hint,
  placeholder = 'رمز عبور را وارد کنید',
  previewState = 'default',
  value,
  onChange,
  onBlur,
  name,
  autoComplete
}: PasswordFieldProps) {
  const [showPassword, setShowPassword] = useState(false);
  const [inner, setInner] = useState('');
  const text = value === undefined ? inner : value;
  const isDisabled = previewState === 'disabled';

  function setText(next: string) {
    if (value === undefined) setInner(next);
    onChange?.(next);
  }

  return (
    <Field htmlFor={id} label={label} hint={hint} previewState={previewState}>
      <div className='relative'>
        <input
          id={id}
          name={name}
          type={showPassword ? 'text' : 'password'}
          className={inputClass(previewState, 'pe-10')}
          placeholder={placeholder}
          value={text}
          disabled={isDisabled}
          autoComplete={autoComplete}
          onBlur={onBlur}
          onChange={(event) => setText(event.target.value)}
        />
        <button
          type='button'
          disabled={isDisabled}
          onClick={() => setShowPassword((current) => !current)}
          className='absolute end-4 top-1/2 z-30 -translate-y-1/2 cursor-pointer'
        >
          {showPassword ? (
            <EyeIcon className='fill-gray-500 dark:fill-gray-400' />
          ) : (
            <EyeCloseIcon className='fill-gray-500 dark:fill-gray-400' />
          )}
        </button>
      </div>
    </Field>
  );
}

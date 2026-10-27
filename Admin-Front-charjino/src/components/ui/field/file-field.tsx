'use client';

import { useState, type ChangeEvent } from 'react';

import { Field } from './field-shell';
import type { FieldProps } from './types';

type FileFieldProps = Omit<FieldProps, 'value' | 'onChange'> & {
  onChange?: (event: ChangeEvent<HTMLInputElement>) => void;
  className?: string;
  accept?: string;
};

export function FileField({
  label = 'بارگذاری فایل',
  hint,
  previewState = 'default',
  onChange,
  className = '',
  accept
}: FileFieldProps) {
  const [fileName, setFileName] = useState('');
  const isDisabled = previewState === 'disabled';

  function handleChange(event: ChangeEvent<HTMLInputElement>) {
    setFileName(event.target.files?.[0]?.name ?? '');
    onChange?.(event);
  }

  return (
    <Field label={label} hint={hint} previewState={previewState}>
      <label
        className={`flex h-11 w-full cursor-pointer items-center overflow-hidden rounded-lg border border-gray-300 bg-transparent text-sm shadow-theme-xs dark:border-gray-700 dark:bg-gray-900 ${isDisabled ? 'cursor-not-allowed opacity-50' : ''} ${className}`}
      >
        <span className='inline-flex h-full items-center border-e border-gray-200 bg-gray-50 px-3.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-white/3 dark:text-gray-300'>
          انتخاب فایل
        </span>
        <span className='truncate px-3 text-gray-500 dark:text-gray-400'>{fileName || 'فایلی انتخاب نشده'}</span>
        <input
          type='file'
          accept={accept}
          disabled={isDisabled}
          className='sr-only'
          onChange={handleChange}
        />
      </label>
    </Field>
  );
}

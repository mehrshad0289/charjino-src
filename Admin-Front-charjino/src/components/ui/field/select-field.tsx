'use client';

import { useState } from 'react';

import { ChevronDownIcon } from '../icon';
import { selectClass } from './classNames';
import { Field } from './field-shell';
import type { FieldProps } from './types';

export type SelectOption = {
  value: string;
  label: string;
};

const DEFAULT_OPTIONS: SelectOption[] = [
  { value: 'marketing', label: 'Marketing' },
  { value: 'template', label: 'Template' },
  { value: 'development', label: 'Development' }
];

type SelectFieldProps = FieldProps & {
  placeholder?: string;
  options?: readonly SelectOption[];
  className?: string;
};

export function SelectField({
  label = 'انتخاب',
  hint,
  placeholder = 'انتخاب کنید',
  previewState = 'default',
  options = DEFAULT_OPTIONS,
  value,
  onChange,
  className = ''
}: SelectFieldProps) {
  const [inner, setInner] = useState('');
  const selected = value === undefined ? inner : value;
  const isDisabled = previewState === 'disabled';

  function choose(next: string) {
    if (value === undefined) setInner(next);
    onChange?.(next);
  }

  return (
    <Field label={label} hint={hint} previewState={previewState}>
      <div className='relative'>
        <select
          className={selectClass(Boolean(selected), className)}
          value={selected}
          disabled={isDisabled}
          onChange={(event) => choose(event.target.value)}
        >
          <option value='' disabled className='text-gray-700 dark:bg-gray-900 dark:text-gray-400'>
            {placeholder}
          </option>
          {options.map((option) => (
            <option
              key={option.value}
              value={option.value}
              className='text-gray-700 dark:bg-gray-900 dark:text-gray-400'
            >
              {option.label}
            </option>
          ))}
        </select>
        <span className='inset-e-3 pointer-events-none absolute top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400'>
          <ChevronDownIcon />
        </span>
      </div>
    </Field>
  );
}

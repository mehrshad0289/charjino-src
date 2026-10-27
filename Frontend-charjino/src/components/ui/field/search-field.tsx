'use client';

import { useState } from 'react';
import { CloseIcon, SearchNormalIcon } from '../icon';
import { CLEAR_BUTTON_CLASS, INPUT_INNER_CLASS, inputClass } from './classNames';
import { hotjarSuppress } from '@/lib/hotjar';
import { Field } from './field-shell';
import type { FieldProps } from './types';

type SearchFieldProps = FieldProps & {
  placeholder?: string;
  className?: string;
  showIcon?: boolean;
};

export function SearchField({
  label,
  hint,
  placeholder = 'جستجو در آگهی ها، مناقصات و ...',
  previewState = 'default',
  value,
  name,
  className,
  showIcon = true,
  onChange,
  onBlur,
}: SearchFieldProps) {
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
        dir="rtl"
        className={inputClass(previewState, Boolean(text), [
          showIcon ? '!justify-start gap-2' : '',
          className ?? '',
        ])}
      >
        {showIcon ? (
          <span className="inline-flex shrink-0 text-gray-600">
            <SearchNormalIcon size={24} />
          </span>
        ) : null}
        <input
          {...hotjarSuppress}
          className={`${INPUT_INNER_CLASS} min-w-0 flex-1 ${showIcon ? '' : 'mr-2'}`}
          name={name}
          placeholder={placeholder}
          value={text}
          disabled={isDisabled}
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

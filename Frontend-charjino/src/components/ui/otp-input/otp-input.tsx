'use client';

import { useEffect, useRef, useState } from 'react';
import { onlyDigits, toFaDigits } from '@/lib/fa';
import { inputClass } from '../field/classNames';
import { hotjarSuppress } from '@/lib/hotjar';
import { Field } from '../field/field-shell';
import type { FieldState } from '../field/types';

type OtpInputProps = {
  label?: string;
  hint?: string;
  length?: number;
  value: string;
  previewState?: FieldState;
  autoFocus?: boolean;
  onChange: (value: string) => void;
};

export function OtpInput({
  label = 'کد تأیید',
  hint = 'کد را وارد کنید.',
  length = 6,
  value,
  previewState = 'default',
  autoFocus = true,
  onChange,
}: OtpInputProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [focused, setFocused] = useState(false);
  const code = onlyDigits(value).slice(0, length);
  const digits = Array.from({ length }, (_, index) => code[index] ?? '');
  const isDisabled = previewState === 'disabled';
  const activeIndex = code.length >= length ? length - 1 : code.length;

  useEffect(() => {
    if (!autoFocus || isDisabled) return;
    inputRef.current?.focus();
  }, [autoFocus, isDisabled, code]);

  return (
    <Field label={label} hint={hint} previewState={previewState}>
      <div
        className={inputClass(previewState, Boolean(code), [
          'relative !items-end gap-2 [direction:ltr]',
        ])}
        onClick={() => inputRef.current?.focus()}
      >
        <input
          {...hotjarSuppress}
          ref={inputRef}
          className="absolute inset-0 z-[1] h-full w-full cursor-text border-0 opacity-0"
          inputMode="numeric"
          autoComplete="one-time-code"
          maxLength={length}
          value={code}
          disabled={isDisabled}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          onChange={(event) => onChange(onlyDigits(event.target.value).slice(0, length))}
        />
        {digits.map((digit, index) => {
          const isActive = focused && index === activeIndex;
          return (
            <div className="flex flex-1 flex-col items-center gap-1" key={index}>
              <span
                className={`flex h-4 items-center justify-center text-sm font-normal leading-none ${
                  isDisabled ? 'text-gray-500' : 'text-gray-600'
                }`}
              >
                {digit ? toFaDigits(digit) : null}
                {isActive && !digit ? (
                  <span className="h-4 w-[1.5px] animate-[otp-blink_1s_step-end_infinite] bg-gray-900" />
                ) : null}
              </span>
              <span
                className={`w-full ${
                  isDisabled
                    ? 'h-px bg-gray-200'
                    : isActive
                      ? 'h-0.5 bg-gray-900'
                      : digit
                        ? 'h-px bg-gray-600'
                        : 'h-px bg-gray-300'
                }`}
              />
            </div>
          );
        })}
      </div>
    </Field>
  );
}

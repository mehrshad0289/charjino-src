'use client';

import { useRef, useState } from 'react';
import { onlyDigits, toFaDigits } from '@/lib/fa';
import { hotjarSuppress } from '@/lib/hotjar';
import { inputClass } from './classNames';
import { Field } from './field-shell';
import type { FieldProps } from './types';

type OtpFieldProps = FieldProps & {
  values?: string[];
  timer?: string;
};

const SLOTS = 7;

export function OtpField({
  label = 'کد تأیید',
  hint,
  previewState = 'default',
  values = [],
  timer,
}: OtpFieldProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [code, setCode] = useState(() => onlyDigits(values.join('')).slice(0, SLOTS));
  const [focused, setFocused] = useState(false);
  const isDisabled = previewState === 'disabled';
  const isError = previewState === 'error';
  const digits = Array.from({ length: SLOTS }, (_, index) => code[index] ?? '');
  const activeIndex = code.length >= SLOTS ? SLOTS - 1 : code.length;

  return (
    <Field
      label={label}
      previewState={previewState}
      footer={
        <div className="flex items-center justify-between [direction:rtl]">
          <span
            className={`h-[19px] text-xs font-normal leading-none ${
              isError ? 'text-error-600' : isDisabled ? 'text-gray-500' : 'text-gray-600'
            }`}
          >
            {hint}
          </span>
          {timer ? (
            <span className="text-xs text-gray-500 [direction:ltr]">{timer}</span>
          ) : null}
        </div>
      }
    >
      <div
        className={inputClass(previewState, false, [
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
          maxLength={SLOTS}
          value={code}
          disabled={isDisabled}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          onChange={(event) => setCode(onlyDigits(event.target.value).slice(0, SLOTS))}
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

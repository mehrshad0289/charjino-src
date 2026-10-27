'use client';

import { useEffect, useRef, useState } from 'react';
import { toFaDigits } from '@/lib/fa';
import {
  isoToJalaliParts,
  jalaliPartsToIso,
  jalaliToday,
} from '@/lib/jalali';
import { CalendarIcon } from '../icon';
import { INPUT_INNER_CLASS, WRAP_CLASS, inputClass } from './classNames';
import { Calendar, MONTHS, type CalendarDate } from './calendar';
import { Field } from './field-shell';
import type { FieldProps } from './types';
import { useOutsideDismiss } from './use-outside-dismiss';

type DateFieldProps = FieldProps & {
  placeholder?: string;
  open?: boolean;
  minYear?: number;
  maxYear?: number;
};

export function DateField({
  label = 'تاریخ تولد',
  hint,
  placeholder = 'تاریخ تولد خود را انتخاب کنید.',
  open = false,
  previewState = 'default',
  value,
  onChange,
  onBlur,
  minYear = 1300,
  maxYear,
}: DateFieldProps) {
  const today = jalaliToday();
  const isControlled = value !== undefined;
  const parsed = value ? isoToJalaliParts(value) : null;
  const [inner, setInner] = useState<CalendarDate | null>(
    open ? { year: 1405, monthIndex: 2, day: 27 } : null,
  );
  const selected = isControlled ? parsed : inner;
  const [isOpen, setIsOpen] = useState(open);
  const rootRef = useRef<HTMLDivElement>(null);
  const [monthIndex, setMonthIndex] = useState(
    selected?.monthIndex ?? today.jm - 1,
  );
  const [year, setYear] = useState(selected?.year ?? today.jy);
  const isDisabled = previewState === 'disabled';
  useOutsideDismiss(isOpen && !isDisabled, rootRef, () => setIsOpen(false));
  const display = selected
    ? `${toFaDigits(selected.day)} ${MONTHS[selected.monthIndex]} ${toFaDigits(selected.year)}`
    : placeholder;

  useEffect(() => {
    if (!selected) return;
    setMonthIndex(selected.monthIndex);
    setYear(selected.year);
  }, [selected?.year, selected?.monthIndex, selected?.day]);

  function goMonth(step: number) {
    const next = monthIndex + step;
    if (next < 0) {
      setMonthIndex(11);
      setYear((current) => current - 1);
      return;
    }
    if (next > 11) {
      setMonthIndex(0);
      setYear((current) => current + 1);
      return;
    }
    setMonthIndex(next);
  }

  function choose(date: CalendarDate) {
    if (!isControlled) setInner(date);
    onChange?.(jalaliPartsToIso(date.year, date.monthIndex, date.day));
    setYear(date.year);
    setMonthIndex(date.monthIndex);
    setIsOpen(false);
    onBlur?.();
  }

  return (
    <Field label={label} hint={hint} previewState={previewState}>
      <div ref={rootRef} className={WRAP_CLASS}>
        <button
          type="button"
          className={inputClass(previewState, Boolean(selected))}
          disabled={isDisabled}
          onClick={() => setIsOpen((current) => !current)}
          onBlur={() => {
            if (!isOpen) onBlur?.();
          }}
        >
          <span className={INPUT_INNER_CLASS}>{display}</span>
          <CalendarIcon />
        </button>
        {isOpen && !isDisabled ? (
          <Calendar
            monthIndex={monthIndex}
            year={year}
            selected={selected}
            minYear={minYear}
            maxYear={maxYear ?? today.jy}
            onPrev={() => goMonth(-1)}
            onNext={() => goMonth(1)}
            onMonthChange={setMonthIndex}
            onYearChange={setYear}
            onSelect={choose}
          />
        ) : null}
      </div>
    </Field>
  );
}

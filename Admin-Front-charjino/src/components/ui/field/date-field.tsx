'use client';

import { isoToJalaliParts, jalaliPartsToIso, jalaliToday } from '@/lib/jalali';
import { CalenderIcon } from '@/components/ui/icon';
import { useRef, useState } from 'react';

import {
  Calendar,
  dateValue,
  formatJalaliDate,
  formatJalaliRange,
  type CalendarDate,
  type CalendarMode
} from './calendar';
import { inputClass } from './classNames';
import { Field } from './field-shell';
import type { FieldProps } from './types';
import { useOutsideDismiss } from './use-outside-dismiss';

export type DateRangeValue = {
  start: string;
  end: string;
};

type DateFieldProps = FieldProps & {
  placeholder?: string;
  open?: boolean;
  minYear?: number;
  maxYear?: number;
  compact?: boolean;
  mode?: CalendarMode;
  rangeValue?: DateRangeValue;
  defaultRange?: DateRangeValue;
  onRangeChange?: (range: DateRangeValue) => void;
  id?: string;
};

function toCalendarDate(iso?: string) {
  return iso ? isoToJalaliParts(iso) : null;
}

function toIso(date: CalendarDate) {
  return jalaliPartsToIso(date.year, date.monthIndex, date.day);
}

function orderedRange(left: CalendarDate, right: CalendarDate): DateRangeValue {
  if (dateValue(left) <= dateValue(right)) {
    return { start: toIso(left), end: toIso(right) };
  }
  return { start: toIso(right), end: toIso(left) };
}

export function DateField({
  id,
  label,
  hint,
  placeholder,
  open = false,
  previewState = 'default',
  value,
  onChange,
  onBlur,
  minYear = 1300,
  maxYear,
  compact = false,
  mode = 'single',
  rangeValue,
  defaultRange,
  onRangeChange,
  name
}: DateFieldProps) {
  const today = jalaliToday();
  const isRange = mode === 'range';
  const fieldLabel = label ?? (isRange ? 'بازه تاریخ' : 'تاریخ');
  const fieldPlaceholder = placeholder ?? (isRange ? 'بازه تاریخ را انتخاب کنید' : 'تاریخ را انتخاب کنید');
  const isControlled = isRange ? rangeValue !== undefined : value !== undefined;
  const parsed = value ? isoToJalaliParts(value) : null;
  const [inner, setInner] = useState<CalendarDate | null>(null);
  const [innerRange, setInnerRange] = useState<{ start: CalendarDate | null; end: CalendarDate | null }>(() => ({
    start: toCalendarDate(defaultRange?.start),
    end: toCalendarDate(defaultRange?.end)
  }));
  const selected = isControlled ? parsed : inner;
  const committedStart = isRange
    ? isControlled
      ? toCalendarDate(rangeValue?.start)
      : innerRange.start
    : selected;
  const committedEnd = isRange
    ? isControlled
      ? toCalendarDate(rangeValue?.end)
      : innerRange.end
    : selected;
  const [draftStart, setDraftStart] = useState<CalendarDate | null>(committedStart);
  const [draftEnd, setDraftEnd] = useState<CalendarDate | null>(committedEnd);
  const [isOpen, setIsOpen] = useState(open);
  const rootRef = useRef<HTMLDivElement>(null);
  const viewDate = draftStart ?? committedStart;
  const [monthIndex, setMonthIndex] = useState(viewDate?.monthIndex ?? today.jm - 1);
  const [year, setYear] = useState(viewDate?.year ?? today.jy);
  const isDisabled = previewState === 'disabled';
  useOutsideDismiss(isOpen && !isDisabled, rootRef, () => {
    setDraftStart(committedStart);
    setDraftEnd(committedEnd);
    setIsOpen(false);
  });

  const display = isRange
    ? draftStart && !draftEnd
      ? `${formatJalaliDate(draftStart)} تا …`
      : committedStart && committedEnd
        ? formatJalaliRange(committedStart, committedEnd)
        : fieldPlaceholder
    : selected
      ? formatJalaliDate(selected)
      : fieldPlaceholder;

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

  function openCalendar() {
    setDraftStart(committedStart);
    setDraftEnd(committedEnd);
    if (committedStart) {
      setMonthIndex(committedStart.monthIndex);
      setYear(committedStart.year);
    }
    setIsOpen((current) => !current);
  }

  function choose(date: CalendarDate) {
    if (!isRange) {
      if (!isControlled) setInner(date);
      onChange?.(toIso(date));
      setYear(date.year);
      setMonthIndex(date.monthIndex);
      setIsOpen(false);
      onBlur?.();
      return;
    }

    if (!draftStart || draftEnd) {
      setDraftStart(date);
      setDraftEnd(null);
      return;
    }

    const range = orderedRange(draftStart, date);
    const startDate = toCalendarDate(range.start);
    const endDate = toCalendarDate(range.end);
    if (!startDate || !endDate) return;
    setDraftStart(startDate);
    setDraftEnd(endDate);
    if (!isControlled) setInnerRange({ start: startDate, end: endDate });
    onRangeChange?.(range);
    setIsOpen(false);
    onBlur?.();
  }

  const trigger = (
    <div ref={rootRef} className={compact ? 'relative' : 'relative w-full min-w-0'}>
      <button
        id={id}
        name={name}
        type='button'
        disabled={isDisabled}
        className={
          compact
            ? `relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 outline-none lg:h-11 lg:justify-start lg:ps-10 lg:pe-3 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 ${
                isRange ? 'lg:w-60' : 'lg:w-48'
              }`
            : `${inputClass(previewState, 'relative pe-11 text-start')} ${
                (isRange ? committedStart && committedEnd : selected) ? '' : 'text-gray-400 dark:text-gray-400'
              }`
        }
        onClick={openCalendar}
        onBlur={() => {
          if (!isOpen) onBlur?.();
        }}
      >
        <CalenderIcon
          className={
            compact
              ? 'size-6 text-gray-500 lg:absolute lg:inset-s-3 lg:top-1/2 lg:-translate-y-1/2 dark:text-gray-400'
              : 'pointer-events-none absolute inset-e-3 top-1/2 size-6 -translate-y-1/2 text-gray-500 dark:text-gray-400'
          }
        />
        <span className={compact ? 'hidden truncate lg:inline' : 'truncate'}>{display}</span>
      </button>
      {isOpen && !isDisabled ? (
        <Calendar
          monthIndex={monthIndex}
          year={year}
          selected={selected}
          rangeStart={isRange ? draftStart : null}
          rangeEnd={isRange ? draftEnd : null}
          mode={mode}
          minYear={minYear}
          maxYear={maxYear ?? today.jy}
          placement={compact ? 'end' : 'stretch'}
          onPrev={() => goMonth(-1)}
          onNext={() => goMonth(1)}
          onMonthChange={setMonthIndex}
          onYearChange={setYear}
          onSelect={choose}
        />
      ) : null}
    </div>
  );

  if (compact) return trigger;

  return (
    <Field htmlFor={id} label={fieldLabel} hint={hint} previewState={previewState}>
      {trigger}
    </Field>
  );
}

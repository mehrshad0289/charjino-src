'use client';

import { jalaliMonthGrid, jalaliToday, jalaliYearList, type JalaliCell } from '@/lib/jalali';
import { toFaDigits } from '@/lib/fa';
import { ChevronLeftIcon, ChevronRightIcon } from '@/components/ui/icon';
import { useState } from 'react';

import { CalendarSelect } from './calendar-select';

export const WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
export const MONTHS = [
  'فروردین',
  'اردیبهشت',
  'خرداد',
  'تیر',
  'مرداد',
  'شهریور',
  'مهر',
  'آبان',
  'آذر',
  'دی',
  'بهمن',
  'اسفند'
];

export type CalendarDate = {
  year: number;
  monthIndex: number;
  day: number;
};

export type CalendarMode = 'single' | 'range';

export function dateValue(date: CalendarDate) {
  return date.year * 10000 + date.monthIndex * 100 + date.day;
}

export function sameDate(left?: CalendarDate | null, right?: CalendarDate | null) {
  if (!left || !right) return false;
  return dateValue(left) === dateValue(right);
}

export function inDateRange(cell: CalendarDate, start: CalendarDate, end: CalendarDate) {
  const min = Math.min(dateValue(start), dateValue(end));
  const max = Math.max(dateValue(start), dateValue(end));
  const value = dateValue(cell);
  return value > min && value < max;
}

export function formatJalaliDate(date: CalendarDate) {
  return `${toFaDigits(date.day)} ${MONTHS[date.monthIndex]} ${toFaDigits(date.year)}`;
}

export function formatJalaliRange(start: CalendarDate, end: CalendarDate) {
  if (start.year === end.year && start.monthIndex === end.monthIndex) {
    return `${toFaDigits(start.day)} تا ${toFaDigits(end.day)} ${MONTHS[end.monthIndex]} ${toFaDigits(end.year)}`;
  }
  if (start.year === end.year) {
    return `${toFaDigits(start.day)} ${MONTHS[start.monthIndex]} تا ${toFaDigits(end.day)} ${MONTHS[end.monthIndex]} ${toFaDigits(end.year)}`;
  }
  return `${formatJalaliDate(start)} تا ${formatJalaliDate(end)}`;
}

type CalendarProps = {
  monthIndex: number;
  year: number;
  selected: CalendarDate | null;
  rangeStart?: CalendarDate | null;
  rangeEnd?: CalendarDate | null;
  mode?: CalendarMode;
  minYear?: number;
  maxYear?: number;
  placement?: 'stretch' | 'end';
  onPrev: () => void;
  onNext: () => void;
  onMonthChange: (monthIndex: number) => void;
  onYearChange: (year: number) => void;
  onSelect: (date: CalendarDate) => void;
};

type Picker = 'month' | 'year' | null;

export function Calendar({
  monthIndex,
  year,
  selected,
  rangeStart = null,
  rangeEnd = null,
  mode = 'single',
  minYear = 1300,
  maxYear,
  placement = 'stretch',
  onPrev,
  onNext,
  onMonthChange,
  onYearChange,
  onSelect
}: CalendarProps) {
  const [picker, setPicker] = useState<Picker>(null);
  const [hoverDate, setHoverDate] = useState<CalendarDate | null>(null);
  const years = jalaliYearList(minYear, maxYear);
  const cells = jalaliMonthGrid(year, monthIndex);
  const today = jalaliToday();
  const previewEnd = rangeEnd ?? (rangeStart ? hoverDate : null);
  const panelClass =
    placement === 'end'
      ? 'absolute end-0 z-40 mt-2 w-72 max-w-[calc(100vw-2rem)]'
      : 'absolute inset-x-0 z-40 mt-2 w-full min-w-0';

  function toggle(next: Picker) {
    setPicker((current) => (current === next ? null : next));
  }

  function isSelected(cell: JalaliCell) {
    return selected?.day === cell.day && selected?.monthIndex === cell.monthIndex && selected?.year === cell.year;
  }

  function isToday(cell: JalaliCell) {
    return cell.day === today.jd && cell.monthIndex === today.jm - 1 && cell.year === today.jy && !cell.muted;
  }

  function dayClass(cell: JalaliCell) {
    const date = { year: cell.year, monthIndex: cell.monthIndex, day: cell.day };
    const selectedDay =
      mode === 'range' ? sameDate(date, rangeStart) || sameDate(date, previewEnd) : isSelected(cell);
    const inRange = mode === 'range' && rangeStart && previewEnd ? inDateRange(date, rangeStart, previewEnd) : false;
    const todayDay = isToday(cell);

    if (selectedDay) return 'bg-brand-500 text-white hover:bg-brand-500';
    if (inRange) return 'bg-gray-50 text-gray-800 dark:bg-white/5 dark:text-white/90';
    if (todayDay) return 'text-brand-500 dark:text-brand-400';
    if (cell.muted) return 'text-gray-400 dark:text-gray-500';
    return 'text-gray-800 hover:bg-gray-50 dark:text-white/90 dark:hover:bg-white/5';
  }

  return (
    <div
      className={`${panelClass} rounded-xl border border-gray-200 bg-white p-5 text-gray-500 shadow-theme-lg dark:border-white/5 dark:bg-gray-dark dark:text-gray-400`}
      dir='rtl'
    >
      <div className='mb-4 flex items-center justify-between gap-2'>
        <button
          type='button'
          className='flex size-9 items-center justify-center rounded-lg border border-gray-200 bg-transparent text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-900'
          aria-label='ماه قبل'
          onClick={() => {
            setPicker(null);
            onPrev();
          }}
        >
          <ChevronRightIcon className='size-5' />
        </button>
        <div className='flex gap-2'>
          <CalendarSelect label={MONTHS[monthIndex]} open={picker === 'month'} ariaLabel='انتخاب ماه' onToggle={() => toggle('month')}>
            {MONTHS.map((month, index) => (
              <button
                key={month}
                type='button'
                className={`flex w-full cursor-pointer items-center rounded-lg border-0 bg-transparent px-3 py-2 text-start text-theme-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300 ${
                  index === monthIndex ? 'bg-gray-50 dark:bg-white/5' : ''
                }`}
                data-active={index === monthIndex}
                onClick={() => {
                  onMonthChange(index);
                  setPicker(null);
                }}
              >
                {month}
              </button>
            ))}
          </CalendarSelect>
          <CalendarSelect
            label={toFaDigits(year)}
            open={picker === 'year'}
            ariaLabel='انتخاب سال'
            onToggle={() => toggle('year')}
          >
            {years.map((item) => (
              <button
                key={item}
                type='button'
                className={`flex w-full cursor-pointer items-center rounded-lg border-0 bg-transparent px-3 py-2 text-start text-theme-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300 ${
                  item === year ? 'bg-gray-50 dark:bg-white/5' : ''
                }`}
                data-active={item === year}
                onClick={() => {
                  onYearChange(item);
                  setPicker(null);
                }}
              >
                {toFaDigits(item)}
              </button>
            ))}
          </CalendarSelect>
        </div>
        <button
          type='button'
          className='flex size-9 items-center justify-center rounded-lg border border-gray-200 bg-transparent text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-900'
          aria-label='ماه بعد'
          onClick={() => {
            setPicker(null);
            onNext();
          }}
        >
          <ChevronLeftIcon className='size-5' />
        </button>
      </div>
      <div className='mb-2 grid grid-cols-7 text-center'>
        {WEEKDAYS.map((day) => (
          <span key={day} className='h-8 text-theme-sm font-medium leading-8 text-gray-500 dark:text-gray-400'>
            {day}
          </span>
        ))}
      </div>
      <div className='grid grid-cols-7 text-center' onMouseLeave={() => setHoverDate(null)}>
        {cells.map((cell) => {
          const date = { year: cell.year, monthIndex: cell.monthIndex, day: cell.day };
          return (
            <button
              key={`${cell.year}-${cell.monthIndex}-${cell.day}-${cell.muted}`}
              type='button'
              className={`flex h-10 items-center justify-center rounded-lg text-theme-sm font-medium ${dayClass(cell)}`}
              onMouseEnter={() => setHoverDate(date)}
              onClick={() => onSelect(date)}
            >
              {toFaDigits(cell.day)}
            </button>
          );
        })}
      </div>
    </div>
  );
}

'use client';

import { useState } from 'react';
import { toFaDigits } from '@/lib/fa';
import { jalaliMonthGrid, jalaliYearList, type JalaliCell } from '@/lib/jalali';
import { ArrowLeftIcon, ArrowRightIcon } from '../icon';
import { MENU_ITEM_CLASS } from './classNames';
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
  'اسفند',
];

export type CalendarDate = {
  year: number;
  monthIndex: number;
  day: number;
};

type CalendarProps = {
  monthIndex: number;
  year: number;
  selected: CalendarDate | null;
  minYear?: number;
  maxYear?: number;
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
  minYear = 1300,
  maxYear,
  onPrev,
  onNext,
  onMonthChange,
  onYearChange,
  onSelect,
}: CalendarProps) {
  const [picker, setPicker] = useState<Picker>(null);
  const years = jalaliYearList(minYear, maxYear);
  const cells = jalaliMonthGrid(year, monthIndex);

  function toggle(next: Picker) {
    setPicker((current) => (current === next ? null : next));
  }

  function isSelected(cell: JalaliCell) {
    return (
      selected?.day === cell.day &&
      selected?.monthIndex === cell.monthIndex &&
      selected?.year === cell.year
    );
  }

  return (
    <div
      className="absolute inset-x-0 top-[calc(100%+8px)] z-20 w-full rounded-md border border-border bg-paper p-4 shadow-popover"
      dir="rtl"
    >
      <div className="mb-3 flex items-center justify-between gap-2">
        <button
          type="button"
          className="inline-flex size-6 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-gray-600"
          aria-label="ماه قبل"
          onClick={() => {
            setPicker(null);
            onPrev();
          }}
        >
          <ArrowRightIcon size={24} />
        </button>
        <div className="flex gap-2">
          <CalendarSelect
            label={MONTHS[monthIndex]}
            open={picker === 'month'}
            ariaLabel="انتخاب ماه"
            onToggle={() => toggle('month')}
          >
            {MONTHS.map((month, index) => (
              <button
                key={month}
                type="button"
                className={`${MENU_ITEM_CLASS} ${index === monthIndex ? 'bg-gray-50' : ''}`}
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
            ariaLabel="انتخاب سال"
            onToggle={() => toggle('year')}
          >
            {years.map((item) => (
              <button
                key={item}
                type="button"
                className={`${MENU_ITEM_CLASS} ${item === year ? 'bg-gray-50' : ''}`}
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
          type="button"
          className="inline-flex size-6 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-gray-600"
          aria-label="ماه بعد"
          onClick={() => {
            setPicker(null);
            onNext();
          }}
        >
          <ArrowLeftIcon size={24} />
        </button>
      </div>
      <div className="mb-1 grid grid-cols-7 gap-1 text-center">
        {WEEKDAYS.map((day) => (
          <span key={day} className="h-6 text-xs leading-6 text-gray-400">
            {day}
          </span>
        ))}
      </div>
      <div className="grid grid-cols-7 gap-1 text-center">
        {cells.map((cell) => (
          <button
            key={`${cell.year}-${cell.monthIndex}-${cell.day}-${cell.muted}`}
            type="button"
            className={[
              'h-8 cursor-pointer rounded-sm border-0 font-sans text-[13px]',
              isSelected(cell)
                ? 'bg-primary-600 text-white'
                : `bg-transparent hover:bg-gray-50 ${
                    cell.muted ? 'text-gray-300' : 'text-gray-600'
                  }`,
            ]
              .filter(Boolean)
              .join(' ')}
            onClick={() => onSelect(cell)}
          >
            {toFaDigits(cell.day)}
          </button>
        ))}
      </div>
    </div>
  );
}

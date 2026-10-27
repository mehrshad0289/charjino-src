'use client';

import { ComponentCard } from '@/components/ui/component-card';

import { DateField } from './date-field';

export function DateFieldBoard() {
  return (
    <div className='space-y-5 sm:space-y-6'>
      <ComponentCard title='یک تاریخ' desc='برای انتخاب یک روز.'>
        <div className='max-w-sm'>
          <DateField label='تاریخ' placeholder='تاریخ را انتخاب کنید' />
        </div>
      </ComponentCard>
      <ComponentCard title='بازه تاریخ' desc='ابتدا روز شروع و بعد روز پایان را بزنید. این حالت پیش‌فرض فیلترهای پنل است.'>
        <div className='max-w-sm'>
          <DateField mode='range' label='بازه تاریخ' placeholder='بازه تاریخ را انتخاب کنید' />
        </div>
      </ComponentCard>
    </div>
  );
}

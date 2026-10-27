'use client';

import { FORM_ELEMENTS_COPY, FORM_SELECT_OPTIONS } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { DateField, PasswordField, SelectField, TextField } from '@/components/ui/field';
import { inputClass } from '@/components/ui/field/classNames';
import { TimeIcon } from '@/components/ui/icon';

export function DefaultInputsCard() {
  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.defaultInputs}>
      <TextField id='form-input' label={FORM_ELEMENTS_COPY.input} placeholder={FORM_ELEMENTS_COPY.inputPlaceholder} />
      <TextField label={FORM_ELEMENTS_COPY.inputWithPlaceholder} placeholder={FORM_ELEMENTS_COPY.emailPlaceholder} />
      <SelectField
        label={FORM_ELEMENTS_COPY.selectInput}
        placeholder={FORM_ELEMENTS_COPY.selectPlaceholder}
        options={FORM_SELECT_OPTIONS}
      />
      <PasswordField id='form-pass' label={FORM_ELEMENTS_COPY.password} placeholder={FORM_ELEMENTS_COPY.passwordPlaceholder} />
      <DateField id='form-date' label={FORM_ELEMENTS_COPY.datePicker} placeholder={FORM_ELEMENTS_COPY.datePlaceholder} />
      <div>
        <label htmlFor='form-time' className='mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400'>
          {FORM_ELEMENTS_COPY.timePicker}
        </label>
        <div className='relative'>
          <input type='time' id='form-time' name='tm' className={`${inputClass('default')} pe-10 text-start`} />
          <span className='pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400'>
            <TimeIcon />
          </span>
        </div>
      </div>
      <div>
        <label className='mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400'>{FORM_ELEMENTS_COPY.payment}</label>
        <div className='relative'>
          <input type='text' placeholder={FORM_ELEMENTS_COPY.cardPlaceholder} className={`${inputClass('default')} ps-[62px]`} />
          <span className='absolute start-0 top-1/2 flex h-11 w-[46px] -translate-y-1/2 items-center justify-center border-e border-gray-200 dark:border-gray-800'>
            <svg width='20' height='20' viewBox='0 0 20 20' fill='none' aria-hidden>
              <circle cx='6.25' cy='10' r='5.625' fill='#E80B26' />
              <circle cx='13.75' cy='10' r='5.625' fill='#F59D31' />
              <path
                d='M10 14.1924C11.1508 13.1625 11.875 11.6657 11.875 9.99979C11.875 8.33383 11.1508 6.8371 10 5.80713C8.84918 6.8371 8.125 8.33383 8.125 9.99979C8.125 11.6657 8.84918 13.1625 10 14.1924Z'
                fill='#FC6020'
              />
            </svg>
          </span>
        </div>
      </div>
    </ComponentCard>
  );
}

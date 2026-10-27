'use client';

import { FORM_ELEMENTS_COPY, FORM_PHONE_COUNTRIES } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { inputClass } from '@/components/ui/field/classNames';
import { EnvelopeIcon } from '@/components/ui/icon';

import { CopyInput } from './copy-input';
import { PhoneInput } from './phone-input';
import { UrlPrefixInput } from './url-prefix-input';

export function InputGroupCard() {
  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.inputGroup}>
      <div>
        <label htmlFor='form-group-email' className='mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400'>
          {FORM_ELEMENTS_COPY.email}
        </label>
        <div className='relative'>
          <input
            id='form-group-email'
            placeholder={FORM_ELEMENTS_COPY.emailPlaceholder}
            type='text'
            className={`${inputClass('default')} ps-[62px]`}
          />
          <span className='absolute start-0 top-1/2 -translate-y-1/2 border-e border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400'>
            <EnvelopeIcon />
          </span>
        </div>
      </div>
      <div>
        <label className='mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400'>{FORM_ELEMENTS_COPY.phone}</label>
        <PhoneInput selectPosition='start' countries={FORM_PHONE_COUNTRIES} />
      </div>
      <div>
        <label className='mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400'>{FORM_ELEMENTS_COPY.website}</label>
        <PhoneInput selectPosition='end' countries={FORM_PHONE_COUNTRIES} />
      </div>
      <div>
        <label className='mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400'>{FORM_ELEMENTS_COPY.url}</label>
        <UrlPrefixInput />
      </div>
      <div>
        <label className='mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400'>{FORM_ELEMENTS_COPY.website}</label>
        <CopyInput />
      </div>
    </ComponentCard>
  );
}

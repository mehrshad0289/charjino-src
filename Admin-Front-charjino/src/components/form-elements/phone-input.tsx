'use client';

import { useState } from 'react';

type CountryCode = {
  code: string;
  label: string;
};

type PhoneInputProps = {
  countries: CountryCode[];
  placeholder?: string;
  onChange?: (phoneNumber: string) => void;
  selectPosition?: 'start' | 'end';
};

export function PhoneInput({
  countries,
  placeholder = '+۹۸ ۹۱۲ ۰۰۰ ۰۰۰۰',
  onChange,
  selectPosition = 'start'
}: PhoneInputProps) {
  const [selectedCountry, setSelectedCountry] = useState(countries[0]?.code ?? 'IR');
  const countryCodes = countries.reduce<Record<string, string>>((acc, item) => {
    acc[item.code] = item.label;
    return acc;
  }, {});
  const [phoneNumber, setPhoneNumber] = useState(countryCodes[selectedCountry] ?? '');

  const selectClass =
    selectPosition === 'start'
      ? 'h-full appearance-none rounded-s-lg border-0 border-e border-gray-200 bg-transparent bg-none py-3 ps-3.5 pe-8 leading-tight text-gray-700 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-800 dark:text-gray-400'
      : 'h-full appearance-none rounded-e-lg border-0 border-s border-gray-200 bg-transparent bg-none py-3 ps-3.5 pe-8 leading-tight text-gray-700 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden rtl:ps-8 rtl:pe-3.5 dark:border-gray-800 dark:text-gray-400';

  return (
    <div className='relative flex'>
      {selectPosition === 'start' ? (
        <div className='absolute inset-s-0 top-0 z-10 flex h-full items-center'>
          <select
            value={selectedCountry}
            onChange={(event) => {
              const next = event.target.value;
              setSelectedCountry(next);
              setPhoneNumber(countryCodes[next]);
              onChange?.(countryCodes[next]);
            }}
            className={selectClass}
          >
            {countries.map((country) => (
              <option key={country.code} value={country.code} className='text-gray-700 dark:bg-gray-900 dark:text-gray-400'>
                {country.code}
              </option>
            ))}
          </select>
          <div className='pointer-events-none absolute inset-e-3 inset-y-0 flex items-center text-gray-700 dark:text-gray-400'>
            <svg className='stroke-current' width='20' height='20' viewBox='0 0 20 20' fill='none'>
              <path
                d='M4.79175 7.396L10.0001 12.6043L15.2084 7.396'
                stroke='currentColor'
                strokeWidth='1.5'
                strokeLinecap='round'
                strokeLinejoin='round'
              />
            </svg>
          </div>
        </div>
      ) : null}
      <input
        type='tel'
        value={phoneNumber}
        onChange={(event) => {
          setPhoneNumber(event.target.value);
          onChange?.(event.target.value);
        }}
        placeholder={placeholder}
        className={`dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-3 text-start text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800 ${
          selectPosition === 'start' ? 'ps-4 pe-21' : 'ps-21 pe-4'
        }`}
      />
      {selectPosition === 'end' ? (
        <div className='absolute inset-e-0 top-0 z-10 flex h-full items-center'>
          <select
            value={selectedCountry}
            onChange={(event) => {
              const next = event.target.value;
              setSelectedCountry(next);
              setPhoneNumber(countryCodes[next]);
              onChange?.(countryCodes[next]);
            }}
            className={selectClass}
          >
            {countries.map((country) => (
              <option key={country.code} value={country.code} className='text-gray-700 dark:bg-gray-900 dark:text-gray-400'>
                {country.code}
              </option>
            ))}
          </select>
          <div className='pointer-events-none absolute inset-e-3 inset-y-0 flex items-center text-gray-700 rtl:inset-s-3 rtl:inset-e-auto dark:text-gray-400'>
            <svg className='stroke-current' width='20' height='20' viewBox='0 0 20 20' fill='none'>
              <path
                d='M4.79175 7.396L10.0001 12.6043L15.2084 7.396'
                stroke='currentColor'
                strokeWidth='1.5'
                strokeLinecap='round'
                strokeLinejoin='round'
              />
            </svg>
          </div>
        </div>
      ) : null}
    </div>
  );
}

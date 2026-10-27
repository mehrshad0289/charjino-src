'use client';

import { COMMON_COPY, DEMOGRAPHIC_REGIONS, ECOMMERCE_COPY } from '@/data/ecommerce';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { MoreDotIcon } from '@/components/ui/icon';
import { useState } from 'react';

import { CountryMap } from './country-map';

export function DemographicCard() {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <div className='rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/3'>
      <div className='flex justify-between'>
        <div>
          <h3 className='text-lg font-semibold text-gray-800 dark:text-white/90'>{ECOMMERCE_COPY.demographic}</h3>
          <p className='mt-1 text-theme-sm text-gray-500 dark:text-gray-400'>{ECOMMERCE_COPY.demographicSubtitle}</p>
        </div>
        <div className='relative h-fit'>
          <button type='button' onClick={() => setIsOpen(!isOpen)} className='dropdown-toggle'>
            <MoreDotIcon className='text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' />
          </button>
          <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className='w-40 p-2'>
            <DropdownItem
              onItemClick={() => setIsOpen(false)}
              className='flex w-full rounded-lg text-start font-normal text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300'
            >
              {COMMON_COPY.viewMore}
            </DropdownItem>
            <DropdownItem
              onItemClick={() => setIsOpen(false)}
              className='flex w-full rounded-lg text-start font-normal text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300'
            >
              {COMMON_COPY.delete}
            </DropdownItem>
          </Dropdown>
        </div>
      </div>
      <div className='border-gary-200 my-6 overflow-hidden rounded-2xl border bg-gray-50 px-4 py-6 sm:px-6 dark:border-gray-800 dark:bg-gray-900'>
        <div
          id='mapOne'
          className='mapOne map-btn -mx-4 -my-6 h-53 w-63 2xsm:w-76.75 xsm:w-89.5 sm:-mx-6 md:w-167 lg:w-158.5 xl:w-98.25 2xl:w-138.5'
        >
          <CountryMap />
        </div>
      </div>
      <div className='space-y-5'>
        {DEMOGRAPHIC_REGIONS.map((region) => (
          <div key={region.id} className='flex items-center justify-between'>
            <div className='flex items-center gap-3'>
              <div className='w-full max-w-8 items-center rounded-full'>
                <span className='block aspect-square w-full rounded-full bg-brand-500' />
              </div>
              <div>
                <p className='text-theme-sm font-semibold text-gray-800 dark:text-white/90'>{region.name}</p>
                <span className='block text-theme-xs text-gray-500 dark:text-gray-400'>
                  {ECOMMERCE_COPY.customersCount(region.count)}
                </span>
              </div>
            </div>
            <div className='flex w-full max-w-35 items-center gap-3'>
              <div className='relative block h-2 w-full max-w-25 rounded-sm bg-gray-200 dark:bg-gray-800'>
                <div
                  className={`absolute top-0 left-0 flex h-full items-center justify-center rounded-sm bg-brand-500 text-xs font-medium text-white ${region.barClass}`}
                />
              </div>
              <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{region.percent}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

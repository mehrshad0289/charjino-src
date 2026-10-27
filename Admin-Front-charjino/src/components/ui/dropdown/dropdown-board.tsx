'use client';

import { COMMON_COPY } from '@/data/ecommerce';
import { ComponentCard } from '@/components/ui/component-card';
import { MoreDotIcon } from '@/components/ui/icon';
import { useState } from 'react';
import { Dropdown } from './dropdown';
import { DropdownItem } from './dropdown-item';

export function DropdownBoard() {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <ComponentCard title={COMMON_COPY.dropdown}>
      <div className='flex justify-center'>
        <div className='relative h-40 w-40'>
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
    </ComponentCard>
  );
}

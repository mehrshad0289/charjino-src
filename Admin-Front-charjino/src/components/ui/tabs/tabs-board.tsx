'use client';

import { COMMON_COPY, ECOMMERCE_COPY } from '@/data/ecommerce';
import { ComponentCard } from '@/components/ui/component-card';
import { useState } from 'react';
import { Tabs } from './tabs';

const TAB_ITEMS = [
  { id: 'optionOne', label: ECOMMERCE_COPY.monthly },
  { id: 'optionTwo', label: ECOMMERCE_COPY.quarterly, className: 'py-1.5 rtl:min-w-20' },
  { id: 'optionThree', label: ECOMMERCE_COPY.annually }
];

export function TabsBoard() {
  const [selected, setSelected] = useState('optionOne');

  return (
    <ComponentCard title={COMMON_COPY.tabs}>
      <div className='flex justify-center'>
        <Tabs items={TAB_ITEMS} value={selected} onChange={setSelected} />
      </div>
    </ComponentCard>
  );
}

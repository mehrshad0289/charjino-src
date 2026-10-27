'use client';

import { ECOMMERCE_COPY } from '@/data/ecommerce';
import { Tabs } from '@/components/ui/tabs';
import { useState } from 'react';

const CHART_TAB_ITEMS = [
  { id: 'optionOne', label: ECOMMERCE_COPY.monthly },
  { id: 'optionTwo', label: ECOMMERCE_COPY.quarterly, className: 'py-1.5 rtl:min-w-20' },
  { id: 'optionThree', label: ECOMMERCE_COPY.annually }
];

export function ChartTab() {
  const [selected, setSelected] = useState('optionOne');

  return <Tabs items={CHART_TAB_ITEMS} value={selected} onChange={setSelected} />;
}

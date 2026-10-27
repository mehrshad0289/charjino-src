'use client';

import { FORM_ELEMENTS_COPY } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { Checkbox } from '@/components/ui/checkbox';
import { useState } from 'react';

export function CheckboxCard() {
  const [isChecked, setIsChecked] = useState(false);
  const [isCheckedTwo, setIsCheckedTwo] = useState(true);

  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.checkbox}>
      <div className='flex items-center gap-4'>
        <div className='flex items-center gap-3'>
          <Checkbox checked={isChecked} onChange={setIsChecked} />
          <span className='block text-sm font-medium text-gray-700 dark:text-gray-400'>{FORM_ELEMENTS_COPY.default}</span>
        </div>
        <Checkbox checked={isCheckedTwo} onChange={setIsCheckedTwo} label={FORM_ELEMENTS_COPY.checked} />
        <Checkbox checked={false} onChange={() => undefined} disabled label={FORM_ELEMENTS_COPY.disabled} />
      </div>
    </ComponentCard>
  );
}

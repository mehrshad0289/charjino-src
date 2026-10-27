'use client';

import { FORM_ELEMENTS_COPY } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { Radio } from '@/components/ui/radio';
import { useState } from 'react';

export function RadioButtonsCard() {
  const [selectedValue, setSelectedValue] = useState('option2');

  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.radios}>
      <div className='flex flex-wrap items-center gap-8'>
        <Radio
          id='form-radio1'
          name='form-group1'
          value='option1'
          checked={selectedValue === 'option1'}
          onChange={setSelectedValue}
          label={FORM_ELEMENTS_COPY.default}
        />
        <Radio
          id='form-radio2'
          name='form-group1'
          value='option2'
          checked={selectedValue === 'option2'}
          onChange={setSelectedValue}
          label={FORM_ELEMENTS_COPY.selected}
        />
        <Radio
          id='form-radio3'
          name='form-group1'
          value='option3'
          checked={selectedValue === 'option3'}
          onChange={setSelectedValue}
          label={FORM_ELEMENTS_COPY.disabled}
          disabled
        />
      </div>
    </ComponentCard>
  );
}

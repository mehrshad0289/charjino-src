'use client';

import { FORM_ELEMENTS_COPY } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { Switch } from '@/components/ui/switch';

export function ToggleSwitchCard() {
  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.toggle}>
      <div className='flex gap-4'>
        <Switch label={FORM_ELEMENTS_COPY.default} defaultChecked />
        <Switch label={FORM_ELEMENTS_COPY.checked} defaultChecked />
        <Switch label={FORM_ELEMENTS_COPY.disabled} disabled />
      </div>
      <div className='flex gap-4'>
        <Switch label={FORM_ELEMENTS_COPY.default} defaultChecked color='gray' />
        <Switch label={FORM_ELEMENTS_COPY.checked} defaultChecked color='gray' />
        <Switch label={FORM_ELEMENTS_COPY.disabled} disabled color='gray' />
      </div>
    </ComponentCard>
  );
}

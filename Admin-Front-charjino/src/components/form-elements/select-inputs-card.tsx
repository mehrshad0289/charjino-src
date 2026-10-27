'use client';

import { FORM_ELEMENTS_COPY, FORM_MULTI_OPTIONS, FORM_SELECT_OPTIONS } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { SelectField } from '@/components/ui/field';

import { MultiSelect } from './multi-select';

export function SelectInputsCard() {
  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.selectInputs}>
      <SelectField
        label={FORM_ELEMENTS_COPY.selectInput}
        placeholder={FORM_ELEMENTS_COPY.selectPlaceholder}
        options={FORM_SELECT_OPTIONS}
      />
      <MultiSelect
        label={FORM_ELEMENTS_COPY.multiSelect}
        options={FORM_MULTI_OPTIONS}
        defaultSelected={['1', '3']}
        placeholder={FORM_ELEMENTS_COPY.multiPlaceholder}
      />
    </ComponentCard>
  );
}

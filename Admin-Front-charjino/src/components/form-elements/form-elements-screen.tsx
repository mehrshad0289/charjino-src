import { FORM_ELEMENTS_COPY } from '@/data/form-elements';
import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';

import { CheckboxCard } from './checkbox-card';
import { DefaultInputsCard } from './default-inputs-card';
import { DropzoneCard } from './dropzone-card';
import { FileInputCard } from './file-input-card';
import { InputGroupCard } from './input-group-card';
import { InputStatesCard } from './input-states-card';
import { RadioButtonsCard } from './radio-buttons-card';
import { SelectInputsCard } from './select-inputs-card';
import { TextAreaInputCard } from './textarea-input-card';
import { ToggleSwitchCard } from './toggle-switch-card';

export function FormElementsScreen() {
  return (
    <div>
      <PageBreadcrumb pageTitle={FORM_ELEMENTS_COPY.pageTitle} />
      <div className='grid grid-cols-1 gap-6 xl:grid-cols-2'>
        <div className='space-y-6'>
          <DefaultInputsCard />
          <SelectInputsCard />
          <TextAreaInputCard />
          <InputStatesCard />
        </div>
        <div className='space-y-6'>
          <InputGroupCard />
          <FileInputCard />
          <CheckboxCard />
          <RadioButtonsCard />
          <ToggleSwitchCard />
          <DropzoneCard />
        </div>
      </div>
    </div>
  );
}

'use client';

import { FORM_ELEMENTS_COPY } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { FileField } from '@/components/ui/field';

export function FileInputCard() {
  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.fileInput}>
      <FileField label={FORM_ELEMENTS_COPY.uploadFile} />
    </ComponentCard>
  );
}

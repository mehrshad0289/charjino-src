'use client';

import { FORM_ELEMENTS_COPY } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { TextareaField } from '@/components/ui/field';
import { useState } from 'react';

export function TextAreaInputCard() {
  const [message, setMessage] = useState('');
  const [messageTwo, setMessageTwo] = useState('');

  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.textareaTitle}>
      <TextareaField
        label={FORM_ELEMENTS_COPY.description}
        placeholder={FORM_ELEMENTS_COPY.textareaPlaceholder}
        value={message}
        onChange={setMessage}
        rows={6}
      />
      <TextareaField
        label={FORM_ELEMENTS_COPY.description}
        placeholder={FORM_ELEMENTS_COPY.textareaPlaceholder}
        previewState='disabled'
        rows={6}
      />
      <TextareaField
        label={FORM_ELEMENTS_COPY.description}
        placeholder={FORM_ELEMENTS_COPY.textareaPlaceholder}
        value={messageTwo}
        onChange={setMessageTwo}
        previewState='error'
        hint={FORM_ELEMENTS_COPY.textareaHint}
        rows={6}
      />
    </ComponentCard>
  );
}

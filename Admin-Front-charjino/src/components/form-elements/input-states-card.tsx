'use client';

import { FORM_ELEMENTS_COPY } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { TextField } from '@/components/ui/field';
import { useState } from 'react';

const EMAIL_PATTERN = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

export function InputStatesCard() {
  const [email, setEmail] = useState('');
  const [emailTwo, setEmailTwo] = useState('');
  const error = email.length > 0 && !EMAIL_PATTERN.test(email);
  const success = emailTwo.length > 0 && EMAIL_PATTERN.test(emailTwo);

  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.inputStates} desc={FORM_ELEMENTS_COPY.inputStatesDesc}>
      <TextField
        type='email'
        label={FORM_ELEMENTS_COPY.email}
        value={email}
        onChange={setEmail}
        placeholder={FORM_ELEMENTS_COPY.emailPlaceholder}
        previewState={error ? 'error' : 'default'}
        hint={error ? FORM_ELEMENTS_COPY.emailHintError : ''}
      />
      <TextField
        type='email'
        label={FORM_ELEMENTS_COPY.email}
        value={emailTwo}
        onChange={setEmailTwo}
        placeholder={FORM_ELEMENTS_COPY.emailPlaceholder}
        previewState={success ? 'success' : 'default'}
        hint={success ? FORM_ELEMENTS_COPY.emailHintSuccess : ''}
      />
      <TextField
        label={FORM_ELEMENTS_COPY.email}
        value={FORM_ELEMENTS_COPY.disabledEmail}
        previewState='disabled'
        placeholder={FORM_ELEMENTS_COPY.emailPlaceholder}
      />
    </ComponentCard>
  );
}

'use client';

import { Checkbox } from '../checkbox';
import { Radio } from '../radio';
import { DateField } from './date-field';
import { FileField } from './file-field';
import { PasswordField } from './password-field';
import { SelectField } from './select-field';
import { TextField } from './text-field';
import { TextareaField } from './textarea-field';

const COLUMN_CLASS = 'flex min-w-0 w-full flex-col gap-6';
const TITLE_CLASS = 'text-[13px] font-semibold text-gray-600';

export function InputBoard() {
  return (
    <div className='grid grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))] items-start gap-x-[clamp(16px,3vw,32px)] gap-y-[clamp(24px,5vw,48px)]'>
      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>Text Field</h2>
        <TextField previewState='default' placeholder='Regular Input' />
        <TextField previewState='hover' placeholder='info@gmail.com' />
        <TextField
          previewState='error'
          placeholder='Enter your email'
          hint='This is an invalid email address.'
        />
        <TextField
          previewState='success'
          placeholder='Enter your email'
          hint='This is an success message.'
        />
        <TextField previewState='disabled' placeholder='Disabled email' value='disabled@example.com' />
        <DateField label='تاریخ' placeholder='تاریخ را انتخاب کنید' />
        <DateField mode='range' label='بازه تاریخ' placeholder='بازه تاریخ را انتخاب کنید' />
      </section>

      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>Password / Select / File</h2>
        <PasswordField />
        <SelectField />
        <FileField />
      </section>

      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>Textarea</h2>
        <TextareaField previewState='default' />
        <TextareaField previewState='error' hint='Please enter a valid message.' />
        <TextareaField previewState='disabled' />
      </section>

      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>Checkbox / Radio</h2>
        <Checkbox label='Default' checked={false} onChange={() => undefined} />
        <Checkbox label='Checked' checked onChange={() => undefined} />
        <Checkbox label='Disabled' checked disabled onChange={() => undefined} />
        <Radio id='radio-a' name='demo-radio-off' value='a' checked={false} label='Default' onChange={() => undefined} />
        <Radio id='radio-b' name='demo-radio-on' value='b' checked label='Selected' onChange={() => undefined} />
        <Radio
          id='radio-c'
          name='demo-radio-disabled'
          value='c'
          checked={false}
          disabled
          label='Disabled'
          onChange={() => undefined}
        />
      </section>
    </div>
  );
}

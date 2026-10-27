import { DateField } from './date-field';
import { OtpField } from './otp-field';
import { PhoneField } from './phone-field';
import { PriceField } from './price-field';
import { SelectField } from './select-field';
import { TextField } from './text-field';

const STATES = ['default', 'hover', 'disabled', 'error'] as const;
const COLUMN_CLASS = 'flex min-w-0 w-full flex-col gap-6';
const TITLE_CLASS = 'text-[13px] font-semibold text-gray-600';

export function InputBoard() {
  return (
    <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))] items-start gap-x-[clamp(16px,3vw,32px)] gap-y-[clamp(24px,5vw,48px)]">
      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>Text Field</h2>
        {STATES.map((state) => (
          <TextField key={state} previewState={state} />
        ))}
      </section>

      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>OTP</h2>
        <OtpField previewState="default" />
        <OtpField previewState="hover" />
        <OtpField previewState="default" values={['۱', '۱', '۱', '۱']} timer="۱:۳۶" />
        <OtpField previewState="disabled" />
        <OtpField previewState="error" />
      </section>

      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>number</h2>
        {STATES.map((state) => (
          <PhoneField key={state} previewState={state} />
        ))}
      </section>

      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>تاریخ تولد</h2>
        <DateField previewState="default" />
        <DateField previewState="hover" open />
        <DateField previewState="disabled" />
        <DateField previewState="error" />
      </section>

      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>قیمت</h2>
        {STATES.map((state) => (
          <PriceField key={state} previewState={state} />
        ))}
      </section>

      <section className={COLUMN_CLASS}>
        <h2 className={TITLE_CLASS}>Dropdown</h2>
        <SelectField previewState="default" hint="Hint text" />
        <SelectField previewState="hover" open hint="Hint text" />
        <SelectField previewState="disabled" hint="Hint text" />
        <SelectField previewState="error" hint="Hint text" />
      </section>
    </div>
  );
}

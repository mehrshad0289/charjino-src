import * as Yup from 'yup';
import { onlyDigits } from '@/lib/fa';

/**
 * هر دو فرمت را می‌پذیرد: `9361240289` و `09361240289`.
 * صفر اول، پیش‌شماره ۹۸ و فاصله/ارقام فارسی حذف می‌شوند.
 * خروجی فیلد ورود: حداکثر ۱۰ رقم بدون صفر (کنار +۹۸).
 */
export function toIranianMobileNational(value: string) {
  let digits = onlyDigits(value);

  if (digits.startsWith('0098')) digits = digits.slice(4);
  else if (digits.startsWith('98') && digits.length >= 12) digits = digits.slice(2);

  if (digits.startsWith('0')) digits = digits.slice(1);

  return digits.slice(0, 10);
}

/** فرمت API بک‌اند: `09XXXXXXXXX` */
export function normalizeIranianMobile(value: string) {
  const national = toIranianMobileNational(value);
  if (/^9\d{9}$/.test(national)) return `0${national}`;
  return national;
}

export const iranianMobileSchema = Yup.string()
  .required('شماره همراه خود را وارد کنید.')
  .test('iranian-mobile', 'شماره همراه معتبر نیست.', (value) =>
    /^09\d{9}$/.test(normalizeIranianMobile(value ?? '')),
  );

export const phoneLoginSchema = Yup.object({
  phone: iranianMobileSchema,
});

export type PhoneLoginValues = {
  phone: string;
};

export function isIranianNationalCode(value: string) {
  const code = onlyDigits(value);
  return code.length === 10 && iranianNationalCodeError(value) === null;
}

/** پیام خطای کد ملی ایران؛ اگر خالی یا معتبر باشد null. */
export function iranianNationalCodeError(value: string): string | null {
  const code = onlyDigits(value);
  if (!code) return null;
  if (code.length !== 10) {
    return `کد ملی باید ۱۰ رقم باشد. شما ${code.length} رقم وارد کرده‌اید.`;
  }
  if (/^(\d)\1{9}$/.test(code)) {
    return 'کد ملی نمی‌تواند هر ده رقم یکسان باشد.';
  }

  const check = Number(code[9]);
  const sum = code
    .slice(0, 9)
    .split('')
    .reduce((total, digit, index) => total + Number(digit) * (10 - index), 0);
  const remainder = sum % 11;
  const expected = remainder < 2 ? remainder : 11 - remainder;
  if (check !== expected) {
    return 'این کد ملی از نظر رقم کنترل صحیح نیست.';
  }
  return null;
}

export const profileIdentitySchema = Yup.object({
  fullName: Yup.string().trim().max(100, 'نام حداکثر ۱۰۰ نویسه است.'),
  nationalCode: Yup.string().test('national-code', function (value) {
    const message = iranianNationalCodeError(value ?? '');
    if (!message) return true;
    return this.createError({ message });
  }),
  birthDate: Yup.string().test('birth-date', 'تاریخ تولد معتبر نیست.', (value) => {
    if (!value) return true;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    if (
      date.getFullYear() !== year ||
      date.getMonth() !== month - 1 ||
      date.getDate() !== day
    ) {
      return false;
    }
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (date > today) return false;
    return year >= 1921;
  }),
  gender: Yup.mixed<'male' | 'female' | ''>().oneOf(['male', 'female', '']),
  provinceId: Yup.number().nullable(),
  cityId: Yup.number()
    .nullable()
    .test('city-required', 'شهر را انتخاب کنید.', function (value) {
      if (this.parent.provinceId == null) return true;
      return value != null;
    }),
  address: Yup.string().trim().max(500, 'آدرس حداکثر ۵۰۰ نویسه است.'),
});

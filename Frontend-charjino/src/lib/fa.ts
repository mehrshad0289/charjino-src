import { gregorianToJalali } from '@/lib/jalali';

const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

export function toFaDigits(value: number | string) {
  return String(value).replace(/\d/g, (digit) => FA_DIGITS[Number(digit)]);
}

export function toEnDigits(value: string) {
  return value
    .replace(/[۰-۹]/g, (digit) => String(FA_DIGITS.indexOf(digit)))
    .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
}

export function onlyDigits(value: string) {
  return toEnDigits(value).replace(/\D/g, '');
}

const JALALI_MONTHS = [
  'فروردین',
  'اردیبهشت',
  'خرداد',
  'تیر',
  'مرداد',
  'شهریور',
  'مهر',
  'آبان',
  'آذر',
  'دی',
  'بهمن',
  'اسفند',
];

export function formatFaDate(iso?: string | null) {
  if (!iso) return '';
  const match = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
  const date = match ? null : new Date(iso);
  const gy = match ? Number(match[1]) : date?.getFullYear();
  const gm = match ? Number(match[2]) : date ? date.getMonth() + 1 : undefined;
  const gd = match ? Number(match[3]) : date?.getDate();
  if (!gy || !gm || !gd || (date && Number.isNaN(date.getTime()))) return '';
  const jalali = gregorianToJalali(gy, gm, gd);
  const month = JALALI_MONTHS[jalali.jm - 1];
  if (!month) return '';
  return `${toFaDigits(jalali.jd)} ${month} ${toFaDigits(jalali.jy)}`;
}

export function formatFaDateTime(iso?: string | null) {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '—';
  const jalali = gregorianToJalali(date.getFullYear(), date.getMonth() + 1, date.getDate());
  const time = `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
  return toFaDigits(`${jalali.jy}/${jalali.jm}/${jalali.jd} ${time}`);
}

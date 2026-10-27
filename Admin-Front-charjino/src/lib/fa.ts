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

export function formatToman(value: number | string | null | undefined) {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount)) return '—';
  return `${amount.toLocaleString('fa-IR')} تومان`;
}

export function formatUsd(value: number | string | null | undefined) {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount) || amount <= 0) return '—';
  return `${amount.toLocaleString('fa-IR', { minimumFractionDigits: 2, maximumFractionDigits: 6 })} دلار`;
}

export function formatFaDate(value: string | null | undefined) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fa-IR');
}

import { onlyDigits } from './fa';

const MAX_PRICE_DIGITS = 12;

export function sanitizePriceInput(value: string | number | null | undefined): string {
  const digits = onlyDigits(String(value ?? '')).slice(0, MAX_PRICE_DIGITS);
  return digits.replace(/^0+(?=\d)/, '');
}

export function formatTomanNumber(value: string | number | null | undefined): string {
  const normalized = sanitizePriceInput(value);
  return normalized ? Number(normalized).toLocaleString('fa-IR') : '';
}

export function tomanPrice(value: unknown): number {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value > 0 ? Math.round(value) : 0;
  }
  if (typeof value === 'string') {
    const digits = onlyDigits(value);
    if (!digits) return 0;
    const amount = Number(digits);
    return Number.isFinite(amount) && amount > 0 ? Math.round(amount) : 0;
  }
  return 0;
}

/** API storefront prices are Toman. Google Product snippets need ISO 4217 IRR (rial = toman × 10). */
export function irrFromToman(toman: number): number {
  return toman > 0 ? toman * 10 : 0;
}

export function hasTomanPrice(value: unknown): boolean {
  return tomanPrice(value) > 0;
}

export function formatFromToman(value: unknown): string | null {
  const price = tomanPrice(value);
  if (price <= 0) return null;
  return `از ${formatTomanNumber(price)} تومان`;
}

export function formatTomanInWords(value: string | number | null | undefined): string {
  const normalized = sanitizePriceInput(value);
  if (!normalized) return '';

  const amount = Number(normalized);
  if (!Number.isFinite(amount) || amount <= 0) return '';

  const ones = [
    '',
    'یک',
    'دو',
    'سه',
    'چهار',
    'پنج',
    'شش',
    'هفت',
    'هشت',
    'نه',
    'ده',
    'یازده',
    'دوازده',
    'سیزده',
    'چهارده',
    'پانزده',
    'شانزده',
    'هفده',
    'هجده',
    'نوزده',
  ];
  const tens = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
  const hundreds = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];

  function chunk(num: number): string {
    if (num < 20) return ones[num] ?? '';
    if (num < 100) {
      const ten = tens[Math.floor(num / 10)] ?? '';
      const one = ones[num % 10] ?? '';
      return one ? `${ten} و ${one}` : ten;
    }
    const hundred = hundreds[Math.floor(num / 100)] ?? '';
    const rest = chunk(num % 100);
    return rest ? `${hundred} و ${rest}` : hundred;
  }

  const parts = [
    { value: Math.floor(amount / 1_000_000_000), suffix: 'میلیارد' },
    { value: Math.floor((amount % 1_000_000_000) / 1_000_000), suffix: 'میلیون' },
    { value: Math.floor((amount % 1_000_000) / 1_000), suffix: 'هزار' },
    { value: amount % 1_000, suffix: '' },
  ]
    .filter((part) => part.value > 0)
    .map((part) => `${chunk(part.value)}${part.suffix ? ` ${part.suffix}` : ''}`);

  return parts.length ? `${parts.join(' و ')} تومان` : '';
}

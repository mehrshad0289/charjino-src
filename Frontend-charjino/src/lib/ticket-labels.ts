export const TICKET_STATUS_LABEL: Record<string, string> = {
  open: 'باز',
  answered: 'پاسخ‌داده‌شده',
  closed: 'بسته‌شده',
};

export const TICKET_CATEGORY_LABEL: Record<string, string> = {
  order: 'سفارش',
  payment: 'پرداخت',
  delivery: 'تحویل',
  account: 'حساب',
  other: 'سایر',
};

export const TICKET_CATEGORY_OPTIONS = [
  { value: 'order', label: 'سفارش' },
  { value: 'payment', label: 'پرداخت' },
  { value: 'delivery', label: 'تحویل' },
  { value: 'account', label: 'حساب' },
  { value: 'other', label: 'سایر' },
] as const;

export function ticketStatusLabel(status: string) {
  return TICKET_STATUS_LABEL[status] ?? status;
}

export function ticketCategoryLabel(category: string) {
  return TICKET_CATEGORY_LABEL[category] ?? category;
}

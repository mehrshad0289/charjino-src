import { format } from 'date-fns-jalali';
import { faIR } from 'date-fns-jalali/locale/fa-IR';

export function formatBlogJalali(iso?: string | null) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  try {
    return format(date, 'd MMMM yyyy', { locale: faIR });
  } catch {
    return '';
  }
}

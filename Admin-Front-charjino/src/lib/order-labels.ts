import type { BadgeColor } from '@/components/ui';

export const ORDER_STATUS_LABEL: Record<string, string> = {
  draft: 'پیش‌نویس',
  pending_payment: 'منتظر پرداخت',
  awaiting_payment: 'منتظر پرداخت',
  paid: 'پرداخت‌شده',
  submitting: 'در حال ارسال',
  submitted: 'ارسال‌شده',
  fulfilling: 'در حال انجام',
  processing: 'در حال انجام',
  delivered: 'تکمیل',
  completed: 'تکمیل',
  partial: 'نیاز به بررسی',
  needs_review: 'نیاز به بررسی',
  failed: 'ناموفق',
  refund_pending: 'بازگشت در انتظار',
  refunded: 'برگشت‌خورده',
  cancelled: 'لغو',
  expired: 'منقضی'
};

export const ORDER_PIPELINE = [
  'draft',
  'awaiting_payment',
  'paid',
  'submitting',
  'processing',
  'completed'
] as const;

export const TIMELINE_LABEL: Record<string, string> = {
  created: 'ایجاد سفارش',
  pending_payment: 'منتظر پرداخت',
  awaiting_payment: 'منتظر پرداخت',
  paid: 'پرداخت تأیید شد',
  fulfilling: 'ارسال به تأمین',
  processing: 'در حال انجام',
  delivered: 'تکمیل شد',
  completed: 'تکمیل شد',
  failed: 'تحویل ناموفق',
  cancelled: 'لغو',
  expired: 'منقضی',
  refunded: 'بازگشت وجه ثبت شد',
  refund_note: 'یادداشت پشتیبانی',
  retry_fulfillment: 'تلاش دوبارهٔ تحویل',
  redeliver: 'تحویل دوباره',
  webhook: 'رویداد تأمین',
  transition: 'تغییر وضعیت',
  admin_reveal: 'نمایش کد توسط ادمین'
};

export const PAYMENT_STATUS_LABEL: Record<string, string> = {
  created: 'ساخته شد',
  redirected: 'هدایت به درگاه',
  verify_pending: 'در انتظار تأیید',
  verified: 'تأیید شد',
  failed: 'ناموفق',
  expired: 'منقضی',
  review: 'نیاز به بررسی'
};

export const FULFILLMENT_STATUS_LABEL: Record<string, string> = {
  not_started: 'شروع نشده',
  queued: 'صف',
  sending: 'در حال ارسال',
  created: 'ساخته‌شده در تأمین',
  submitted: 'ارسال شده',
  pending: 'در انتظار تأمین',
  processing: 'در حال انجام',
  completed: 'تکمیل',
  partial: 'ناقص',
  refunded: 'برگشت تأمین',
  refund: 'برگشت تأمین',
  failed: 'ناموفق',
  unknown: 'نامشخص',
  abandoned: 'ثبت نشده'
};

export function orderStatusColor(status: string): BadgeColor {
  if (status === 'delivered' || status === 'completed') return 'success';
  if (status === 'failed' || status === 'expired') return 'error';
  if (
    status === 'fulfilling' ||
    status === 'processing' ||
    status === 'submitting' ||
    status === 'pending_payment' ||
    status === 'awaiting_payment' ||
    status === 'paid' ||
    status === 'needs_review' ||
    status === 'partial' ||
    status === 'refund_pending'
  ) {
    return 'warning';
  }
  if (status === 'refunded' || status === 'cancelled') return 'light';
  return 'info';
}

export function orderStatusLabel(status: string) {
  return ORDER_STATUS_LABEL[status] ?? status;
}

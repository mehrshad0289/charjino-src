export const ORDER_STATUS_LABEL: Record<string, string> = {
  draft: 'پیش‌نویس',
  pending_payment: 'در انتظار پرداخت',
  awaiting_payment: 'در انتظار پرداخت',
  paid: 'پرداخت شد؛ در صف انجام',
  submitting: 'در حال انجام',
  submitted: 'در حال انجام',
  fulfilling: 'در حال انجام',
  processing: 'در حال انجام',
  delivered: 'تکمیل شد',
  completed: 'تکمیل شد',
  partial: 'در حال بررسی توسط پشتیبانی شارژینو',
  needs_review: 'در حال بررسی توسط پشتیبانی شارژینو',
  failed: 'ناموفق؛ بازگشت وجه در جریان است',
  refund_pending: 'در حال بازگشت وجه',
  refunded: 'وجه بازگشت داده شد',
  cancelled: 'لغو شد',
  expired: 'مهلت پرداخت تمام شد',
};

export const TIMELINE_LABEL: Record<string, string> = {
  created: 'سفارش ثبت شد',
  pay_started: 'پرداخت',
  paid: 'پرداخت تأیید شد',
  payment_failed: 'پرداخت ناموفق بود',
  expired: 'مهلت پرداخت تمام شد',
  SUBMIT: 'ارسال به تأمین‌کننده',
  FULFILL: 'ارسال به تأمین‌کننده',
  fulfill_failed: 'ناموفق',
  fulfilling: 'در حال انجام',
  processing: 'در حال انجام',
  submitted: 'ارسال شد',
  delivered: 'تکمیل شد',
  completed: 'تکمیل شد',
  transition: 'به‌روزرسانی وضعیت',
};

export function orderStatusLabel(status: string, statusLabelFa?: string | null) {
  if (statusLabelFa?.trim()) return statusLabelFa.trim();
  return ORDER_STATUS_LABEL[status] ?? status;
}

export function timelineLabel(type: string) {
  return TIMELINE_LABEL[type] ?? type;
}

export function isPayableStatus(status: string) {
  return status === 'draft' || status === 'pending_payment' || status === 'awaiting_payment';
}

export function isTerminalStatus(status: string) {
  return [
    'completed',
    'delivered',
    'failed',
    'refunded',
    'cancelled',
    'expired',
    'needs_review',
    'partial',
    'refund_pending',
  ].includes(status);
}

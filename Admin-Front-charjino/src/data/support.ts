import type { SupportTabId, TicketStatus } from '@/types/support';
import type { BadgeColor } from '@/components/ui/badge';
import { toFaDigits } from '@/lib/fa';

export const SUPPORT_COPY = {
  listTitle: 'لیست تیکت‌ها',
  replyTitle: 'پاسخ تیکت',
  ticketsCard: 'تیکت‌های پشتیبانی',
  ticketsCardDesc: 'تیکت‌های ثبت‌شده از فروشگاه',
  total: 'کل تیکت‌ها',
  open: 'باز',
  answered: 'پاسخ‌داده‌شده',
  closed: 'بسته‌شده',
  ticketId: 'شماره تیکت',
  ticketHeading: 'تیکت',
  requestedBy: 'درخواست‌دهنده',
  subject: 'موضوع',
  createDate: 'تاریخ ایجاد',
  status: 'وضعیت',
  viewMore: 'مشاهده و پاسخ',
  filter: 'فیلتر',
  showing: (from: number, to: number, total: number) =>
    `نمایش ${toFaDigits(from)} تا ${toFaDigits(to)} از ${toFaDigits(total)}`,
  pageOf: (page: number, pages: number) => `صفحه ${toFaDigits(page)} از ${toFaDigits(pages)}`,
  ticketNo: (number: string) => `#${toFaDigits(number)}`,
  details: 'جزئیات تیکت',
  customer: 'مشتری',
  phone: 'موبایل',
  orderNumber: 'شماره سفارش',
  category: 'دسته‌بندی',
  created: 'ایجاد شده',
  replyPlaceholder: 'پاسخ خود را بنویسید...',
  reply: 'ارسال پاسخ',
  fromTeam: 'از تیم پشتیبانی',
  statuses: {
    open: 'باز',
    answered: 'پاسخ‌داده‌شده',
    closed: 'بسته‌شده',
  } satisfies Record<TicketStatus, string>,
  tabs: [
    { id: 'all', label: 'همه' },
    { id: 'open', label: 'باز' },
    { id: 'answered', label: 'پاسخ‌داده‌شده' },
    { id: 'closed', label: 'بسته‌شده' },
  ] satisfies { id: SupportTabId; label: string }[],
  closedHint: 'این تیکت بسته شده است. برای پاسخ، ابتدا وضعیت را «باز» کنید.',
} as const;

export const SUPPORT_STATUS_COLOR: Record<TicketStatus, BadgeColor> = {
  open: 'warning',
  answered: 'primary',
  closed: 'success',
};

export const SUPPORT_PAGE_SIZE = 10;

export const SUPPORT_AGENT = {
  name: 'پشتیبانی شارژینو',
  email: 'support@charjino.ir',
  avatar: '/images/user/owner.png',
} as const;

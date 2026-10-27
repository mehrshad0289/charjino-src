import { SUPPORT_AGENT } from '@/data/support';
import { formatFaDate } from '@/lib/fa';
import type { AdminTicket, AdminTicketMessage } from '@/types/tickets';
import type { SupportTicket, TicketMessage } from '@/types/support';

const DEFAULT_AVATAR = '/images/user/user-01.jpg';

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

export function ticketCategoryLabel(category: string) {
  return TICKET_CATEGORY_LABEL[category] ?? category;
}

export function presentSupportTicket(row: AdminTicket): SupportTicket {
  const phone = row.userPhoneNumber?.trim() || '';
  const name = row.userName?.trim() || (phone ? phone : 'کاربر');

  return {
    id: row.id,
    number: row.number,
    subject: row.subject,
    status: (row.status as SupportTicket['status']) ?? 'open',
    category: ticketCategoryLabel(String(row.category ?? 'other')),
    orderNumber: row.orderNumber ?? null,
    createdLabel: formatFaDate(row.createdAt),
    headerTime: formatFaDate(row.updatedAt ?? row.createdAt),
    createdAt: row.createdAt ?? '',
    requester: {
      name,
      phone,
      avatar: DEFAULT_AVATAR,
    },
  };
}

export function presentTicketMessages(ticket: AdminTicket): TicketMessage[] {
  const rows = ticket.messages ?? [];
  const phone = ticket.userPhoneNumber?.trim() || '';
  const customerName = ticket.userName?.trim() || phone || 'کاربر';

  return rows.map((message: AdminTicketMessage) => {
    const isStaff = message.sender === 'staff';

    return {
      id: String(message.id),
      authorName: isStaff ? SUPPORT_AGENT.name : customerName,
      authorEmail: isStaff ? SUPPORT_AGENT.email : phone || undefined,
      avatar: isStaff ? SUPPORT_AGENT.avatar : DEFAULT_AVATAR,
      role: isStaff ? 'agent' : 'customer',
      timeLabel: formatFaDate(message.createdAt),
      body: message.body,
    };
  });
}

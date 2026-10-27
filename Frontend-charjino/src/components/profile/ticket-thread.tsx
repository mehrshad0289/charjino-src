import Link from 'next/link';
import { formatFaDateTime } from '@/lib/fa';
import { ROUTES } from '@/lib/routes';
import { ticketCategoryLabel, ticketStatusLabel } from '@/lib/ticket-labels';
import type { SupportTicket } from '@/types/profile';
import { ProfileBack, ProfileHeading } from './profile-chrome';
import { ProfileSubnav } from './profile-subnav';
import { TicketReplyForm } from './ticket-reply-form';
import styles from './profile.module.css';

export function TicketThread({ ticket }: { ticket: SupportTicket }) {
  const closed = ticket.status === 'closed';
  const messages = ticket.messages ?? [];

  return (
    <>
      <ProfileSubnav />
      <ProfileBack href={ROUTES.profileTickets} label="بازگشت به تیکت‌ها" />
      <ProfileHeading title={ticket.subject} />
      <div className={styles.panel}>
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-text/45">شماره</dt>
            <dd className="mt-1 font-semibold">{ticket.number}</dd>
          </div>
          <div>
            <dt className="text-text/45">وضعیت</dt>
            <dd className="mt-1 text-primary-600">{ticketStatusLabel(ticket.status)}</dd>
          </div>
          <div>
            <dt className="text-text/45">دسته</dt>
            <dd className="mt-1">{ticketCategoryLabel(ticket.category ?? 'other')}</dd>
          </div>
          {ticket.orderNumber ? (
            <div>
              <dt className="text-text/45">سفارش</dt>
              <dd className="mt-1">
                <Link href={ROUTES.profileOrder(ticket.orderNumber)} className="text-primary-600">
                  {ticket.orderNumber}
                </Link>
              </dd>
            </div>
          ) : null}
        </dl>
      </div>
      <div className={`${styles.panel} flex flex-col gap-3`}>
        {messages.length === 0 ? (
          <p className="text-sm text-text/50">هنوز پیامی نیست.</p>
        ) : (
          messages.map((message) => (
            <div
              key={message.id}
              className={message.sender === 'staff' ? styles.bubbleStaff : styles.bubbleUser}
            >
              <p className="mb-1 text-[11px] text-text/45">
                {message.sender === 'staff' ? 'پشتیبانی' : 'شما'} · {formatFaDateTime(message.createdAt)}
              </p>
              <p>{message.body}</p>
            </div>
          ))
        )}
      </div>
      {closed ? (
        <p className="text-sm text-text/55">این تیکت بسته شده است.</p>
      ) : (
        <div className={styles.panel}>
          <TicketReplyForm ticketId={ticket.id} />
        </div>
      )}
    </>
  );
}

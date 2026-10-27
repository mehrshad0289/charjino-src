import Link from 'next/link';
import { Button } from '@/components/ui';
import { formatFaDateTime } from '@/lib/fa';
import { ROUTES } from '@/lib/routes';
import { ticketStatusLabel } from '@/lib/ticket-labels';
import type { SupportTicket } from '@/types/profile';
import { ProfileEmpty, ProfileHeading, ProfilePager } from './profile-chrome';
import { ProfileSubnav } from './profile-subnav';
import styles from './profile.module.css';

type ProfileTicketsListProps = {
  tickets: SupportTicket[];
  page: number;
  totalPages: number;
  listError?: string | null;
};

export function ProfileTicketsList({
  tickets,
  page,
  totalPages,
  listError = null,
}: ProfileTicketsListProps) {
  return (
    <>
      <ProfileSubnav />
      <ProfileHeading
        title="تیکت‌ها"
        action={
          <Button href={ROUTES.profileTicketNew} size="M">
            تیکت جدید
          </Button>
        }
      />
      {listError ? (
        <p className="text-sm text-error-200">{listError}</p>
      ) : tickets.length === 0 ? (
        <ProfileEmpty>هنوز تیکتی ثبت نشده است.</ProfileEmpty>
      ) : (
        <ul className="flex flex-col gap-3">
          {tickets.map((ticket) => (
            <li key={ticket.id}>
              <Link href={ROUTES.profileTicket(ticket.id)} className={styles.row}>
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="font-semibold text-text">{ticket.subject}</span>
                  <span className="text-sm text-primary-600">{ticketStatusLabel(ticket.status)}</span>
                </div>
                <p className="text-sm text-text/60">{ticket.number}</p>
                <p className="text-xs text-text/45">{formatFaDateTime(ticket.updatedAt ?? ticket.createdAt)}</p>
              </Link>
            </li>
          ))}
        </ul>
      )}
      <ProfilePager
        page={page}
        totalPages={totalPages}
        hrefFor={(next) => (next > 1 ? `${ROUTES.profileTickets}?page=${next}` : ROUTES.profileTickets)}
      />
    </>
  );
}

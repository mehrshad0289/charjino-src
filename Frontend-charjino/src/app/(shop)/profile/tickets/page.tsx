import type { Metadata } from 'next';
import { listMyTickets } from '@/actions/tickets.actions';
import { ProfileTicketsList } from '@/components/profile';
import { requireSessionUser } from '@/lib/require-session';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'تیکت‌های من',
  description: PAGE_DESCRIPTIONS.profileTickets,
  path: ROUTES.profileTickets,
  noindex: true,
});

type ProfileTicketsPageProps = {
  searchParams: Promise<{ page?: string }>;
};

export default async function ProfileTicketsPage({ searchParams }: ProfileTicketsPageProps) {
  await requireSessionUser(ROUTES.profileTickets);
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const list = await listMyTickets(page);

  return (
    <ProfileTicketsList
      tickets={list.ok ? list.data.items : []}
      page={page}
      totalPages={list.ok ? list.data.meta.totalPages : 1}
      listError={list.ok ? null : list.message}
    />
  );
}

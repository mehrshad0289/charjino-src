import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getMyTicket } from '@/actions/tickets.actions';
import { TicketThread } from '@/components/profile';
import { requireSessionUser } from '@/lib/require-session';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

type ProfileTicketPageProps = {
  params: Promise<{ id: string }>;
};

export async function generateMetadata({ params }: ProfileTicketPageProps): Promise<Metadata> {
  const { id } = await params;
  return buildMetadata({
    title: `تیکت ${id}`,
    description: PAGE_DESCRIPTIONS.profileTickets,
    path: ROUTES.profileTicket(id),
    noindex: true,
  });
}

export default async function ProfileTicketPage({ params }: ProfileTicketPageProps) {
  const { id } = await params;
  await requireSessionUser(ROUTES.profileTicket(id));
  const result = await getMyTicket(id);
  if (!result.ok) {
    if ('notFound' in result && result.notFound) {
      notFound();
    }
    return (
      <div className="flex flex-col gap-3">
        <h1 className="text-2xl font-bold">درخواست انجام نشد</h1>
        <p className="text-sm text-text/60">{result.message}</p>
      </div>
    );
  }

  return <TicketThread ticket={result.data} />;
}

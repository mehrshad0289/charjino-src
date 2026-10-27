import type { Metadata } from 'next';
import { TicketCreateForm } from '@/components/profile';
import { requireSessionUser } from '@/lib/require-session';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'تیکت جدید',
  description: PAGE_DESCRIPTIONS.profileTickets,
  path: ROUTES.profileTicketNew,
  noindex: true,
});

export default async function ProfileTicketNewPage() {
  await requireSessionUser(ROUTES.profileTicketNew);
  return <TicketCreateForm />;
}

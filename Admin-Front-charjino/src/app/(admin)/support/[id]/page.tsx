import { getAdminTicket } from '@/actions/tickets.actions';
import { SupportReplyScreen } from '@/components/support';
import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';
import { SUPPORT_COPY } from '@/data/support';
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

export const dynamic = 'force-dynamic';

type SupportReplyPageProps = {
  params: Promise<{ id: string }>;
};

export async function generateMetadata({ params }: SupportReplyPageProps): Promise<Metadata> {
  const { id } = await params;
  const result = await getAdminTicket(id);
  if (!result.ok) {
    return { title: SUPPORT_COPY.replyTitle };
  }
  return {
    title: `${SUPPORT_COPY.replyTitle} — ${result.ticket.subject}`,
  };
}

export default async function SupportReplyPage({ params }: SupportReplyPageProps) {
  const { id } = await params;
  const result = await getAdminTicket(id);
  if (!result.ok) {
    if (result.notFound) notFound();
  }

  if (!result.ok) {
    return (
      <div>
        <PageBreadcrumb pageTitle={SUPPORT_COPY.replyTitle} />
        <p className='text-theme-sm text-error-500'>{result.message}</p>
      </div>
    );
  }

  return (
    <div>
      <PageBreadcrumb pageTitle={SUPPORT_COPY.replyTitle} />
      <SupportReplyScreen ticket={result.ticket} />
    </div>
  );
}

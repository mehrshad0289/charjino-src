import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { getSessionUser } from '@/actions/auth.actions';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';
import { authHref } from '@/lib/safe-path';

export const dynamic = 'force-dynamic';

type InvoiceDetailPageProps = {
  params: Promise<{ invoice: string }>;
  searchParams: Promise<{ from?: string; error?: string }>;
};

export async function generateMetadata({ params }: InvoiceDetailPageProps): Promise<Metadata> {
  const { invoice } = await params;
  return buildMetadata({
    title: `سفارش ${invoice}`,
    description: PAGE_DESCRIPTIONS.invoice,
    path: ROUTES.invoice(invoice),
    noindex: true,
  });
}

export default async function InvoiceDetailPage({ params, searchParams }: InvoiceDetailPageProps) {
  const { invoice } = await params;
  const query = await searchParams;
  const user = await getSessionUser();
  if (!user) {
    redirect(authHref(ROUTES.invoice(invoice)));
  }

  const next = new URLSearchParams();
  if (query.from) next.set('from', query.from);
  if (query.error) next.set('error', query.error);
  const qs = next.toString();
  redirect(`${ROUTES.profileOrder(invoice)}${qs ? `?${qs}` : ''}`);
}

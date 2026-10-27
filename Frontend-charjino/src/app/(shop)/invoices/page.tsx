import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { getSessionUser } from '@/actions/auth.actions';
import { getShopHealth } from '@/actions/checkout.actions';
import { InvoicesScreen } from '@/components/invoices';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';
import { anySupplierLive } from '@/lib/shop-live-order';

export const dynamic = 'force-dynamic';

type InvoicesPageProps = {
  searchParams: Promise<{ page?: string }>;
};

export default async function InvoicesPage({ searchParams }: InvoicesPageProps) {
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const [user, health] = await Promise.all([getSessionUser(), getShopHealth()]);
  if (user) {
    redirect(ROUTES.profileOrders);
  }

  return (
    <InvoicesScreen
      signedIn={false}
      orders={[]}
      page={page}
      totalPages={1}
      liveOrderEnabled={anySupplierLive(health.ok ? health.data : undefined)}
      listError={null}
    />
  );
}

export const metadata: Metadata = buildMetadata({
  title: 'پیگیری سفارش',
  description: PAGE_DESCRIPTIONS.invoices,
  path: ROUTES.invoices,
  noindex: true,
});

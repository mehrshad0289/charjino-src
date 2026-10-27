import type { Metadata } from 'next';
import { listMyOrders } from '@/actions/checkout.actions';
import { ProfileOrdersList } from '@/components/profile';
import { requireSessionUser } from '@/lib/require-session';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'سفارش‌های من',
  description: PAGE_DESCRIPTIONS.profileOrders,
  path: ROUTES.profileOrders,
  noindex: true,
});

type ProfileOrdersPageProps = {
  searchParams: Promise<{ page?: string }>;
};

export default async function ProfileOrdersPage({ searchParams }: ProfileOrdersPageProps) {
  await requireSessionUser(ROUTES.profileOrders);
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const list = await listMyOrders(page);

  return (
    <ProfileOrdersList
      orders={list.ok ? list.data.items : []}
      page={page}
      totalPages={list.ok ? list.data.meta.totalPages : 1}
      listError={list.ok ? null : list.message}
    />
  );
}

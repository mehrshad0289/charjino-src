import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getMyOrder } from '@/actions/checkout.actions';
import { ProfileOrderDetail } from '@/components/profile';
import { requireSessionUser } from '@/lib/require-session';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

type ProfileOrderPageProps = {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ from?: string; error?: string }>;
};

export async function generateMetadata({ params }: ProfileOrderPageProps): Promise<Metadata> {
  const { id } = await params;
  return buildMetadata({
    title: `سفارش ${id}`,
    description: PAGE_DESCRIPTIONS.profileOrder,
    path: ROUTES.profileOrder(id),
    noindex: true,
  });
}

export default async function ProfileOrderPage({ params, searchParams }: ProfileOrderPageProps) {
  const { id } = await params;
  const query = await searchParams;
  await requireSessionUser(ROUTES.profileOrder(id));
  const result = await getMyOrder(id);
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

  return (
    <ProfileOrderDetail
      order={result.data}
      fromPsp={query.from === 'psp'}
      errorCode={query.error ?? null}
    />
  );
}

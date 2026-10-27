import type { Metadata } from 'next';
import { listMyFavorites } from '@/actions/favorites.actions';
import { ProfileFavoritesGrid } from '@/components/profile';
import { requireSessionUser } from '@/lib/require-session';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'علاقه‌مندی‌ها',
  description: PAGE_DESCRIPTIONS.profileFavorites,
  path: ROUTES.profileFavorites,
  noindex: true,
});

type ProfileFavoritesPageProps = {
  searchParams: Promise<{ page?: string }>;
};

export default async function ProfileFavoritesPage({ searchParams }: ProfileFavoritesPageProps) {
  await requireSessionUser(ROUTES.profileFavorites);
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const list = await listMyFavorites(page);

  return (
    <ProfileFavoritesGrid
      items={list.ok ? list.data.items : []}
      page={page}
      totalPages={list.ok ? list.data.meta.totalPages : 1}
      listError={list.ok ? null : list.message}
    />
  );
}

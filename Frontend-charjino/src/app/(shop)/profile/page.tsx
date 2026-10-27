import type { Metadata } from 'next';
import { ProfileHub } from '@/components/profile';
import { requireSessionUser } from '@/lib/require-session';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'حساب من',
  description: PAGE_DESCRIPTIONS.profile,
  path: ROUTES.profile,
  noindex: true,
});

export default async function ProfilePage() {
  const user = await requireSessionUser(ROUTES.profile);
  return <ProfileHub phoneNumber={user.phoneNumber} name={user.name} />;
}

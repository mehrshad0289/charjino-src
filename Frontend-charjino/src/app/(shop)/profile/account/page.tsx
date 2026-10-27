import type { Metadata } from 'next';
import { AccountForm } from '@/components/profile';
import { requireSessionUser } from '@/lib/require-session';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'اطلاعات حساب',
  description: PAGE_DESCRIPTIONS.profileAccount,
  path: ROUTES.profileAccount,
  noindex: true,
});

export default async function ProfileAccountPage() {
  const user = await requireSessionUser(ROUTES.profileAccount);
  return <AccountForm phoneNumber={user.phoneNumber} name={user.name} />;
}

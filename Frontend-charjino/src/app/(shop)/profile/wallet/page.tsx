import type { Metadata } from 'next';
import { getMyWallet } from '@/actions/checkout.actions';
import { ProfileWallet } from '@/components/profile/profile-wallet';
import { requireSessionUser } from '@/lib/require-session';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'کیف پول',
  description: PAGE_DESCRIPTIONS.profileWallet,
  path: ROUTES.profileWallet,
  noindex: true,
});

export default async function ProfileWalletPage() {
  await requireSessionUser(ROUTES.profileWallet);
  const result = await getMyWallet();
  if (!result.ok) {
    return (
      <div className="flex flex-col gap-3">
        <h1 className="text-2xl font-bold">کیف پول</h1>
        <p className="text-sm text-text/60">{result.message}</p>
      </div>
    );
  }

  return <ProfileWallet wallet={result.data} />;
}

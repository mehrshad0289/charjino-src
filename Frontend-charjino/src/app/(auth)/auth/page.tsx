import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { getSessionUser } from '@/actions/auth.actions';
import { AuthScreen } from '@/components/auth';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';
import { safeNextPath } from '@/lib/safe-path';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'ورود و عضویت',
  description: PAGE_DESCRIPTIONS.auth,
  path: ROUTES.auth,
  noindex: true,
});

type AuthPageProps = {
  searchParams: Promise<{ next?: string; phone?: string }>;
};

export default async function AuthPage({ searchParams }: AuthPageProps) {
  const { next, phone } = await searchParams;
  const nextPath = safeNextPath(next);
  const user = await getSessionUser();
  if (user) {
    redirect(nextPath);
  }

  return <AuthScreen nextPath={nextPath} initialPhone={phone} />;
}

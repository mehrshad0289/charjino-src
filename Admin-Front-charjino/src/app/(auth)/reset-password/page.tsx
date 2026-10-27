import { getSessionUser } from '@/actions/auth.actions';
import { ResetPasswordScreen } from '@/components/auth/reset-password-screen';
import { AUTH_COPY } from '@/data/auth';
import type { Metadata } from 'next';
import { redirect } from 'next/navigation';

export const metadata: Metadata = {
  title: AUTH_COPY.resetTitle
};

type PageProps = {
  searchParams: Promise<{ token?: string; email?: string }>;
};

export default async function ResetPasswordPage({ searchParams }: PageProps) {
  const user = await getSessionUser();
  if (user) redirect('/');
  const params = await searchParams;

  return <ResetPasswordScreen email={params.email ?? ''} token={params.token ?? ''} />;
}

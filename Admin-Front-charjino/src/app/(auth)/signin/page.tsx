import { getSessionUser } from '@/actions/auth.actions';
import { SignInScreen } from '@/components/auth';
import { AUTH_COPY } from '@/data/auth';
import type { Metadata } from 'next';
import { redirect } from 'next/navigation';

export const metadata: Metadata = {
  title: AUTH_COPY.title
};

export default async function SignInPage() {
  const user = await getSessionUser();
  if (user) redirect('/');

  return <SignInScreen />;
}

import { getSessionUser } from '@/actions/auth.actions';
import { ForgotPasswordScreen } from '@/components/auth/forgot-password-screen';
import { AUTH_COPY } from '@/data/auth';
import type { Metadata } from 'next';
import { redirect } from 'next/navigation';

export const metadata: Metadata = {
  title: AUTH_COPY.forgotTitle
};

export default async function ForgotPasswordPage() {
  const user = await getSessionUser();
  if (user) redirect('/');

  return <ForgotPasswordScreen />;
}

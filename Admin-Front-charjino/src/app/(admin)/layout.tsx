import { getSessionUser } from '@/actions/auth.actions';
import { AdminShell } from '@/components/ui';
import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';

export default async function AdminLayout({ children }: { children: ReactNode }) {
  const user = await getSessionUser();
  if (!user) redirect('/signin');

  return <AdminShell>{children}</AdminShell>;
}

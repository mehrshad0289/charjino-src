import { redirect } from 'next/navigation';
import { getSessionUser } from '@/actions/auth.actions';
import { authHref } from '@/lib/safe-path';
import type { AuthUser } from '@/types/auth';

export async function requireSessionUser(nextPath: string): Promise<AuthUser> {
  const user = await getSessionUser();
  if (!user) {
    redirect(authHref(nextPath));
  }
  return user;
}

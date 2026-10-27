import { AuthLayout } from '@/components/auth';
import type { ReactNode } from 'react';

export default function AuthGroupLayout({ children }: { children: ReactNode }) {
  return <AuthLayout>{children}</AuthLayout>;
}

import { AdminDashboardScreen } from '@/components/admin/dashboard-screen';
import { PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'پیشخوان'
};

export default function Home() {
  return (
    <div>
      <PageBreadcrumb pageTitle='پیشخوان' />
      <AdminDashboardScreen />
    </div>
  );
}

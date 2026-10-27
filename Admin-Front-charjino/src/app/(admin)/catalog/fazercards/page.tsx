import { getFazerCardsStatus, getFazerCardsSyncStatus } from '@/actions/admin.actions';
import { FazerCardsOverviewScreen } from '@/components/admin/fazer-cards/fazer-cards-overview-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'نمای کلی FazerCards'
};

export default async function FazerCardsOverviewPage() {
  const [result, lastSync] = await Promise.all([getFazerCardsStatus(), getFazerCardsSyncStatus()]);

  return (
    <div>
      <PageBreadcrumb pageTitle='نمای کلی FazerCards' />
      <ComponentCard
        title='تأمین‌کننده FazerCards'
        desc='کلید فقط روی بک‌اند است. فروشگاه هرگز FazerCards را مستقیم صدا نمی‌زند.'
      >
        <FazerCardsOverviewScreen
          status={result.ok ? result.data : undefined}
          lastSync={lastSync.ok ? lastSync.data : undefined}
          error={result.ok ? undefined : result.message}
        />
      </ComponentCard>
    </div>
  );
}

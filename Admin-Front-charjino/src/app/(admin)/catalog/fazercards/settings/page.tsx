import { getFazerCardsAudits, getFazerCardsStatus } from '@/actions/admin.actions';
import { FazerCardsSettingsScreen } from '@/components/admin/fazer-cards/fazer-cards-settings-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'تنظیمات FazerCards'
};

export default async function FazerCardsSettingsPage() {
  const [result, audits] = await Promise.all([getFazerCardsStatus(), getFazerCardsAudits()]);

  return (
    <div>
      <PageBreadcrumb pageTitle='تنظیمات FazerCards' />
      <ComponentCard
        title='کلید و سفارش زنده'
        desc='مقدار کامل کلید برنمی‌گردد. پرچم زنده جدا از Shop2 است.'
      >
        <FazerCardsSettingsScreen
          status={result.ok ? result.data : undefined}
          audits={audits.ok ? audits.items : []}
          error={result.ok ? undefined : result.message}
        />
      </ComponentCard>
    </div>
  );
}

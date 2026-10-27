import { listBuyGuideCoverage } from '@/actions/buy-guide.actions';
import { BuyGuideCoverageScreen } from '@/components/admin/buy-guide/coverage-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'راهنمای خرید'
};

export const dynamic = 'force-dynamic';

export default async function BuyGuidesPage() {
  const result = await listBuyGuideCoverage();

  return (
    <div>
      <PageBreadcrumb pageTitle='راهنمای خرید' />
      <ComponentCard
        title='پوشش راهنماها'
        desc='برای هر دسته یک پایه و برای هر زیردسته متن اختصاصی بگذار. محصول خاص فقط در صورت نیاز override می‌شود.'
      >
        <BuyGuideCoverageScreen items={result.ok ? result.items : []} error={result.ok ? undefined : result.message} />
      </ComponentCard>
    </div>
  );
}

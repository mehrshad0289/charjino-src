import { getPricing } from '@/actions/admin.actions';
import { PricingScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'نرخ دلار و سود'
};

export default async function PricingPage() {
  const result = await getPricing();

  return (
    <div>
      <PageBreadcrumb pageTitle='نرخ دلار و سود' />
      <ComponentCard
        title='قیمت‌گذاری تومان'
        desc='نرخ خودکار (auto) از بازار آزاد. «گرفتن نرخ روز» حالت را auto می‌کند. حاشیه سود روی قیمت فروش همهٔ بسته‌ها اعمال می‌شود.'
      >
        <PricingScreen snapshot={result.ok ? result.data : undefined} error={result.ok ? undefined : result.message} />
      </ComponentCard>
    </div>
  );
}

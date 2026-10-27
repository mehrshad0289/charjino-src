import { getSupplierStatus } from '@/actions/admin.actions';
import { SupplierScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'وضعیت تأمین Shop2'
};

export default async function SupplierPage() {
  const result = await getSupplierStatus();

  return (
    <div>
      <PageBreadcrumb pageTitle='وضعیت تأمین' />
      <ComponentCard
        title='پشتیبان Shop2TopUp'
        desc='کاتالوگ دیگر از Shop2 همگام نمی‌شود. این صفحه فقط کیف و کلید پشتیبان سفارش است. مقدار S2T_KEY نمایش داده نمی‌شود.'
      >
        <SupplierScreen status={result.ok ? result.data : undefined} error={result.ok ? undefined : result.message} />
      </ComponentCard>
    </div>
  );
}

import { CouponEditorScreen } from '@/components/admin/coupon-editor-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'کد تخفیف جدید'
};

export const dynamic = 'force-dynamic';

export default function AdminCouponNewPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle='کد تخفیف جدید' />
      <ComponentCard
        title='ثبت کد'
        desc='درصدی: درصد + سقف تومان. مبلغی: مبلغ + حداقل خرید که باید حداقل برابر تخفیف باشد.'
      >
        <CouponEditorScreen />
      </ComponentCard>
    </div>
  );
}

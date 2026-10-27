import { getCoupon } from '@/actions/coupons.actions';
import { CouponEditorScreen } from '@/components/admin/coupon-editor-screen';
import { Alert, ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'ویرایش کد تخفیف'
};

export const dynamic = 'force-dynamic';

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function AdminCouponEditPage({ params }: PageProps) {
  const { id } = await params;
  const couponId = Number(id);
  const result = Number.isInteger(couponId) && couponId > 0 ? await getCoupon(couponId) : { ok: false as const, message: 'کد پیدا نشد.' };

  return (
    <div>
      <PageBreadcrumb pageTitle='ویرایش کد تخفیف' />
      <ComponentCard title='ویرایش'>
        {result.ok ? (
          <CouponEditorScreen coupon={result.item} />
        ) : (
          <Alert variant='error' title='خوانده نشد' message={result.message} />
        )}
      </ComponentCard>
    </div>
  );
}

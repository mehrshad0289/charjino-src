import { listCoupons } from '@/actions/coupons.actions';
import { CouponsScreen } from '@/components/admin/coupons-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { parseTablePage, parseTablePerPage } from '@/lib/table-page';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'کد تخفیف'
};

export const dynamic = 'force-dynamic';

type PageProps = {
  searchParams: Promise<{ q?: string; status?: string; page?: string; perPage?: string }>;
};

export default async function AdminCouponsPage({ searchParams }: PageProps) {
  const params = await searchParams;
  const page = parseTablePage(params.page);
  const perPage = parseTablePerPage(params.perPage);
  const result = await listCoupons({ q: params.q, status: params.status, page, perPage });

  return (
    <div>
      <PageBreadcrumb pageTitle='کد تخفیف' />
      <ComponentCard
        title='کدهای تخفیف'
        desc='درصدی با سقف تومان، یا مبلغی با حداقل خرید. تا قبل از انقضا در فروشگاه قابل اعمال است.'
      >
        <CouponsScreen
          items={result.ok ? result.items : []}
          error={result.ok ? undefined : result.message}
          q={params.q}
          status={params.status}
          page={result.ok ? result.meta.page : page}
          perPage={result.ok ? result.meta.perPage : perPage}
          totalPages={result.ok ? result.meta.totalPages : 1}
          total={result.ok ? result.meta.total : 0}
        />
      </ComponentCard>
    </div>
  );
}

import { listOrders } from '@/actions/admin.actions';
import { OrdersScreen } from '@/components/admin/orders-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { parseTablePage, parseTablePerPage } from '@/lib/table-page';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'سفارش‌ها'
};

type OrdersPageProps = {
  searchParams: Promise<{ status?: string; q?: string; page?: string; perPage?: string; unknown?: string; hold?: string }>;
};

export default async function AdminOrdersPage({ searchParams }: OrdersPageProps) {
  const params = await searchParams;
  const page = parseTablePage(params.page);
  const perPage = parseTablePerPage(params.perPage);
  const result = await listOrders({
    status: params.status,
    q: params.q,
    page,
    perPage,
    unknown: params.unknown === '1',
    hold: params.hold === '1'
  });

  return (
    <div>
      <PageBreadcrumb pageTitle='سفارش‌ها' />
      <ComponentCard title='سفارش‌های فروشگاه' desc='از ایجاد تا تحویل. فیلتر وضعیت و جستجو از API است. بازگشت وجه کش‌اوت کوین نیست.'>
        <OrdersScreen
          items={result.ok ? result.items : []}
          error={result.ok ? undefined : result.message}
          status={params.status}
          q={params.q}
          page={result.ok ? result.meta.page : page}
          perPage={result.ok ? result.meta.perPage : perPage}
          totalPages={result.ok ? result.meta.totalPages : 1}
          total={result.ok ? result.meta.total : undefined}
        />
      </ComponentCard>
    </div>
  );
}

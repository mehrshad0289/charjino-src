import { listUsers } from '@/actions/admin.actions';
import { UsersScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { parseTablePage, parseTablePerPage } from '@/lib/table-page';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'کاربران'
};

type UsersPageProps = {
  searchParams: Promise<{ page?: string; perPage?: string }>;
};

export default async function UsersPage({ searchParams }: UsersPageProps) {
  const params = await searchParams;
  const page = parseTablePage(params.page);
  const perPage = parseTablePerPage(params.perPage);
  const result = await listUsers({ page, perPage });

  return (
    <div>
      <PageBreadcrumb pageTitle='کاربران' />
      <ComponentCard title='لیست کاربران فروشگاه' desc='فقط خواندنی. ویرایش کاربر در API نیست.'>
        <UsersScreen
          items={result.ok ? result.items : []}
          error={result.ok ? undefined : result.message}
          page={result.ok ? result.meta?.page ?? page : page}
          perPage={result.ok ? result.meta?.perPage ?? perPage : perPage}
          totalPages={result.ok ? result.meta?.totalPages ?? 1 : 1}
          total={result.ok ? result.meta?.total : undefined}
        />
      </ComponentCard>
    </div>
  );
}

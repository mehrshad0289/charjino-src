import { listProductComments } from '@/actions/product-comments.actions';
import { ProductCommentsScreen } from '@/components/admin/product-comments-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { parseTablePage, parseTablePerPage } from '@/lib/table-page';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'نظرهای محصولات'
};

export const dynamic = 'force-dynamic';

type PageProps = {
  searchParams: Promise<{ status?: string; page?: string; perPage?: string }>;
};

export default async function AdminProductCommentsPage({ searchParams }: PageProps) {
  const params = await searchParams;
  const page = parseTablePage(params.page);
  const perPage = parseTablePerPage(params.perPage);
  const status = params.status ?? 'pending';
  const result = await listProductComments({
    status: status === 'all' ? undefined : status,
    page,
    perPage
  });

  return (
    <div>
      <PageBreadcrumb pageTitle='نظرهای محصولات' />
      <ComponentCard title='نظرها و پاسخ‌ها' desc='نظر کاربر تا تأیید تو در فروشگاه دیده نمی‌شود. نامناسب را پنهان کن.'>
        <ProductCommentsScreen
          items={result.ok ? result.items : []}
          error={result.ok ? undefined : result.message}
          status={status}
          page={result.ok ? result.meta.page : page}
          perPage={result.ok ? result.meta.perPage : perPage}
          totalPages={result.ok ? result.meta.totalPages : 1}
          total={result.ok ? result.meta.total : 0}
        />
      </ComponentCard>
    </div>
  );
}

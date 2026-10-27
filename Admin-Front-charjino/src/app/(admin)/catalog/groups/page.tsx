import { listGroups } from '@/actions/admin.actions';
import { GroupsScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { parseTablePage, parseTablePerPage } from '@/lib/table-page';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'دسته‌بندی‌ها'
};

type CatalogGroupsPageProps = {
  searchParams: Promise<{ source?: string; page?: string; perPage?: string }>;
};

export default async function CatalogGroupsPage({ searchParams }: CatalogGroupsPageProps) {
  const params = await searchParams;
  const page = parseTablePage(params.page);
  const perPage = parseTablePerPage(params.perPage);
  const result = await listGroups({ source: params.source, page, perPage });

  return (
    <div>
      <PageBreadcrumb pageTitle='دسته‌بندی‌ها' />
      <ComponentCard
        title='دسته‌های کاتالوگ'
        desc='منبع ساخته‌شده را از تأمین‌کننده جدا کن. کاتالوگ Shop2 را می‌توان از دیتابیس حذف کرد.'
      >
        <GroupsScreen
          items={result.ok ? result.items : []}
          error={result.ok ? undefined : result.message}
          source={params.source}
          page={result.ok ? result.meta?.page ?? page : page}
          perPage={result.ok ? result.meta?.perPage ?? perPage : perPage}
          totalPages={result.ok ? result.meta?.totalPages ?? 1 : 1}
          total={result.ok ? result.meta?.total ?? result.items.length : 0}
        />
      </ComponentCard>
    </div>
  );
}

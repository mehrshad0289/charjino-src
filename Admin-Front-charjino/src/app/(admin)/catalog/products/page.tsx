import { listGroups, listProducts } from '@/actions/admin.actions';
import { ProductsScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { parseTablePage, parseTablePerPage } from '@/lib/table-page';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'محصولات'
};

type CatalogProductsPageProps = {
  searchParams: Promise<{
    category?: string;
    status?: string;
    q?: string;
    visible?: string;
    page?: string;
    source?: string;
    platform?: string;
    region?: string;
    perPage?: string;
  }>;
};

export default async function CatalogProductsPage({ searchParams }: CatalogProductsPageProps) {
  const params = await searchParams;
  const page = parseTablePage(params.page);
  const perPage = parseTablePerPage(params.perPage);
  const [products, groups] = await Promise.all([
    listProducts({
      category: params.category,
      status: params.status,
      q: params.q,
      visible: params.visible,
      source: params.source,
      platform: params.platform,
      region: params.region,
      page,
      perPage
    }),
    listGroups()
  ]);

  return (
    <div>
      <PageBreadcrumb pageTitle='محصولات' />
      <ComponentCard
        title='لیست محصولات'
        desc='فیلتر منبع ساخته‌شده را از تأمین‌کننده جدا می‌کند. همگام FazerCards بسته‌های حذف‌شده را از فروشگاه برمی‌دارد.'
      >
        <ProductsScreen
          items={products.ok ? products.items : []}
          groups={groups.ok ? groups.items : []}
          error={products.ok ? undefined : products.message}
          q={params.q}
          category={params.category}
          status={params.status}
          visible={params.visible === '1' ? 'visible' : params.visible === '0' ? 'hidden' : ''}
          source={params.source}
          platform={params.platform}
          region={params.region}
          platforms={products.ok ? products.meta.platforms ?? [] : []}
          regions={products.ok ? products.meta.regions ?? [] : []}
          page={products.ok ? products.meta.page : page}
          perPage={products.ok ? products.meta.perPage : perPage}
          totalPages={products.ok ? products.meta.totalPages : 1}
          total={products.ok ? products.meta.total : undefined}
        />
      </ComponentCard>
    </div>
  );
}

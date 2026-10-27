import { getGroup, listProducts } from '@/actions/admin.actions';
import { ProductsScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { parseTablePage, parseTablePerPage } from '@/lib/table-page';
import Link from 'next/link';
import type { Metadata } from 'next';

type GroupProductsPageProps = {
  params: Promise<{ id: string }>;
  searchParams: Promise<{
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

export const metadata: Metadata = {
  title: 'محصولات دسته'
};

export default async function GroupProductsPage({ params, searchParams }: GroupProductsPageProps) {
  const { id } = await params;
  const query = await searchParams;
  const groupId = Number(id);
  const page = parseTablePage(query.page);
  const perPage = parseTablePerPage(query.perPage);
  const [groupResult, products] = await Promise.all([
    getGroup(groupId),
    listProducts({
      category: groupId,
      status: query.status,
      q: query.q,
      visible: query.visible,
      source: query.source,
      platform: query.platform,
      region: query.region,
      page,
      perPage
    })
  ]);

  if (!groupResult.ok) {
    return (
      <div>
        <PageBreadcrumb pageTitle='محصولات دسته' />
        <ComponentCard title='لیست محصولات'>
          <p className='text-sm text-gray-500'>{groupResult.message}</p>
        </ComponentCard>
      </div>
    );
  }

  const group = groupResult.item;

  return (
    <div>
      <PageBreadcrumb pageTitle={`محصولات ${group.title}`} />
      <div className='mb-4 flex flex-wrap items-center gap-3 text-sm'>
        <Link href='/catalog/groups' className='font-medium text-brand-500'>
          همه دسته‌ها
        </Link>
        <span className='text-gray-300 dark:text-gray-700'>|</span>
        <Link href={`/catalog/groups/${group.id}`} className='font-medium text-brand-500'>
          ویرایش دسته
        </Link>
      </div>
      <ComponentCard title='لیست محصولات' desc='لیست این دسته از API فیلتر می‌شود. محصول را مخفی کن؛ حذف فیزیکی نیست.'>
        <ProductsScreen
          items={products.ok ? products.items : []}
          groups={[group]}
          lockedGroupId={group.id}
          error={products.ok ? undefined : products.message}
          q={query.q}
          status={query.status}
          visible={query.visible === '1' ? 'visible' : query.visible === '0' ? 'hidden' : ''}
          source={query.source}
          platform={query.platform}
          region={query.region}
          platforms={products.ok ? products.meta.platforms ?? [] : []}
          regions={products.ok ? products.meta.regions ?? [] : []}
          page={products.ok ? products.meta.page : page}
          perPage={products.ok ? products.meta.perPage : perPage}
          totalPages={products.ok ? products.meta.totalPages : 1}
          total={products.ok ? products.meta.total : undefined}
          basePath={`/catalog/groups/${group.id}/products`}
        />
      </ComponentCard>
    </div>
  );
}

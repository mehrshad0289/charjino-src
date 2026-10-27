import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import { getCatalogCategories, getSmartSearchResults } from '@/actions/catalog.actions';
import { CatalogProductCard } from '@/components/catalog/catalog-product-card';
import { catalogBrowseGridClass } from '@/components/catalog/catalog-browse-skeleton';
import { Container } from '@/components/ui';
import { isBrowsePageOutOfRange } from '@/lib/catalog-browse';
import { ROUTES } from '@/lib/routes';
import { buildMetadata, searchDescription } from '@/lib/seo';
import { canonicalPath } from '@/lib/site';
import { toPublicProductFromSmartSearch } from '@/lib/smart-search-products';
import { isSmartSearchProduct } from '@/types/smart-search';
import type { CatalogProduct } from '@/types/catalog';

export const dynamic = 'force-dynamic';

type SearchPageProps = {
  searchParams: Promise<{
    q?: string;
    page?: string;
    group?: string;
    platform?: string;
    region?: string;
  }>;
};

export async function generateMetadata({ searchParams }: SearchPageProps): Promise<Metadata> {
  const params = await searchParams;
  const q = (params.q ?? '').trim().slice(0, 100);
  return buildMetadata({
    title: q ? `جستجو: ${q}` : 'جستجو',
    description: q ? searchDescription(q) : 'جستجوی شارژ بازی و گیفت کارت در شارژینو.',
    path: canonicalPath(ROUTES.search, q ? { q } : undefined),
    noindex: true,
    follow: true,
  });
}

export default async function SearchPage({ searchParams }: SearchPageProps) {
  const params = await searchParams;
  const q = (params.q ?? '').trim().slice(0, 100);
  const page = Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1);
  const group = params.group?.trim() || undefined;
  const platform = params.platform?.trim() || undefined;
  const region = params.region?.trim() || undefined;
  const [categoriesResult, searchResult] = await Promise.all([
    getCatalogCategories(),
    getSmartSearchResults({ q, page, group, platform, region }),
  ]);
  const categories = categoriesResult.ok ? categoriesResult.items : [];
  const items = searchResult.ok ? searchResult.data.items.filter(isSmartSearchProduct) : [];
  const pagination = searchResult.ok
    ? searchResult.data.pagination
    : { current_page: page, last_page: 1, has_more_pages: false };
  const products: CatalogProduct[] = items.map(toPublicProductFromSmartSearch);
  if (searchResult.ok && q.length >= 2 && isBrowsePageOutOfRange(page, pagination.last_page)) {
    notFound();
  }

  return (
    <div className="bg-surface">
      <Container className="flex flex-col gap-6 py-8 sm:gap-8 sm:py-12">
        <header className="flex flex-col gap-2">
          <h1 className="text-xl font-semibold text-text sm:text-2xl">
            {q ? `نتایج «${q}»` : 'جستجو'}
          </h1>
          {searchResult.ok ? null : (
            <p className="text-sm text-text/70">{searchResult.message}</p>
          )}
        </header>
        {q.length < 2 ? (
          <p className="text-sm text-text/70">برای جستجو حداقل دو حرف بنویسید.</p>
        ) : products.length === 0 ? (
          <div className="flex flex-col gap-4">
            <p className="text-sm text-text/70">چیزی با این عبارت پیدا نشد. یکی از دسته‌ها را امتحان کنید.</p>
            <ul className="flex flex-wrap gap-2">
              {categories.map((category) => (
                <li key={category.slug}>
                  <Link
                    href={ROUTES.category(category.slug)}
                    className="inline-flex rounded-lg bg-card px-3 py-2 text-sm shadow-card ring-1 ring-border hover:bg-text/5"
                  >
                    {category.title}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ) : (
          <>
            <div className={catalogBrowseGridClass()}>
              {products.map((product) => (
                <CatalogProductCard key={product.handle} product={product} />
              ))}
            </div>
            <nav className="flex items-center justify-center gap-3 text-sm">
              {page > 1 ? (
                <Link
                  href={`${ROUTES.search}?${new URLSearchParams({
                    q,
                    page: String(page - 1),
                    ...(group ? { group } : {}),
                    ...(platform ? { platform } : {}),
                    ...(region ? { region } : {}),
                  }).toString()}`}
                  className="rounded-lg bg-card px-3 py-2 ring-1 ring-border"
                >
                  قبلی
                </Link>
              ) : null}
              {pagination.has_more_pages ? (
                <Link
                  href={`${ROUTES.search}?${new URLSearchParams({
                    q,
                    page: String(page + 1),
                    ...(group ? { group } : {}),
                    ...(platform ? { platform } : {}),
                    ...(region ? { region } : {}),
                  }).toString()}`}
                  className="rounded-lg bg-card px-3 py-2 ring-1 ring-border"
                >
                  بعدی
                </Link>
              ) : null}
            </nav>
          </>
        )}
      </Container>
    </div>
  );
}

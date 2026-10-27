import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getCatalogCategories, listShopBrowseProducts } from '@/actions/catalog.actions';
import { CatalogBrowsePanel } from '@/components/catalog/catalog-browse-panel';
import { CategoryLanding } from '@/components/catalog/category-landing';
import { JsonLd } from '@/components/seo/json-ld';
import { Container, WaveDivider } from '@/components/ui';
import { isBrowsePageOutOfRange } from '@/lib/catalog-browse';
import { BROWSE_PER_PAGE, gameKeyPlatformLabel, resolveGameKeyBrowsePlatform } from '@/lib/catalog-family';
import { toFaDigits } from '@/lib/fa';
import { browseBreadcrumbItems, breadcrumbListJsonLd, collectionPageJsonLd } from '@/lib/json-ld';
import { ROUTES, catalogBrowseHref } from '@/lib/routes';
import { storefrontCardProduct, storefrontNavCategories } from '@/lib/catalog-page-data';
import { SHOP_LISTING_HEADING, categoryHeading } from '@/lib/seo';
import type { PageMeta } from '@/types/catalog';

export { isFacetedBrowse, parseBrowseQuery } from '@/lib/catalog-browse';

export async function CatalogBrowseScreen({
  category,
  q,
  platform,
  region,
  page,
  sort,
}: {
  category: string;
  q: string;
  platform: string;
  region: string;
  page: number;
  sort: 'listed' | 'new' | 'price';
}) {
  const listPlatform = resolveGameKeyBrowsePlatform(category, platform);
  const [categoriesResult, productsResult] = await Promise.all([
    getCatalogCategories(),
    listShopBrowseProducts({
      category,
      q,
      page,
      perPage: BROWSE_PER_PAGE,
      platform: listPlatform || undefined,
      region: region || undefined,
      sort,
    }),
  ]);

  const categories = storefrontNavCategories(
    (categoriesResult.ok ? categoriesResult.items : []).filter((item) => item.slug && item.title),
  );
  const selected = categories.find((item) => item.slug === category);
  if (category && categoriesResult.ok && !selected) {
    notFound();
  }

  const facetLabel = listPlatform ? gameKeyPlatformLabel(listPlatform) : '';
  const heading = q ? `نتایج «${q}»` : selected?.title || SHOP_LISTING_HEADING;
  const pageHeading = q
    ? heading
    : selected
      ? categoryHeading(selected.title, selected.slug, selected.seoTitle)
      : SHOP_LISTING_HEADING;
  const items = productsResult.ok ? productsResult.data.items.map(storefrontCardProduct) : [];
  const meta: PageMeta = productsResult.ok
    ? productsResult.data.meta
    : { page, perPage: BROWSE_PER_PAGE, total: 0, totalPages: 1, platforms: [], regions: [] };
  if (productsResult.ok && isBrowsePageOutOfRange(page, meta.totalPages)) {
    notFound();
  }
  const browsePath = catalogBrowseHref({
    category: selected?.slug,
    q,
    platform,
    region,
    page,
    sort,
  });

  return (
    <>
      <JsonLd
        data={breadcrumbListJsonLd(
          browseBreadcrumbItems({
            categoryTitle: selected?.title,
            categorySlug: selected?.slug,
            heading,
            path: browsePath,
          }),
        )}
      />
      {selected && !q && page <= 1 ? (
        <JsonLd
          data={collectionPageJsonLd({
            name: pageHeading,
            description: selected.seoDescription || undefined,
            path: ROUTES.category(selected.slug),
            items: items.map((item) => ({
              name: item.title,
              path: ROUTES.catalog(item.handle),
            })),
          })}
        />
      ) : null}
      <div className="">
        <Container className="flex flex-col gap-6 py-8 sm:gap-8 sm:py-12">
          <div className="flex flex-col gap-3">
            <nav className="flex flex-wrap items-center gap-2 text-sm text-text-muted">
              <Link href={ROUTES.home} className="hover:text-primary-600">
                خانه
              </Link>
              <span className="text-text/25">/</span>
              <Link href={ROUTES.categories} className="hover:text-primary-600">
                فروشگاه
              </Link>
              {selected ? (
                <>
                  <span className="text-text/25">/</span>
                  <Link href={ROUTES.category(selected.slug)} className="hover:text-primary-600">
                    {selected.title}
                  </Link>
                </>
              ) : null}
              {facetLabel ? (
                <>
                  <span className="text-text/25">/</span>
                  <span className="text-text">{facetLabel}</span>
                </>
              ) : null}
            </nav>
            <div className="flex flex-wrap items-baseline gap-3">
              <h1 className="text-xl font-bold text-text sm:text-3xl">{pageHeading}</h1>
              {productsResult.ok ? (
                <p className="text-sm text-text-muted">{toFaDigits(meta.total)} محصول</p>
              ) : null}
            </div>
          </div>
          <CatalogBrowsePanel
            categories={categories}
            category={category}
            q={q}
            platform={platform}
            region={region}
            sort={sort}
            page={page}
            items={items}
            meta={meta}
            listOk={productsResult.ok}
            listMessage={productsResult.ok ? undefined : productsResult.message}
          />
          <CategoryLanding
            slug={category}
            q={q}
            page={page}
            platform={platform}
            region={region}
            sort={sort}
            products={items}
          />
        </Container>
      </div>
      <WaveDivider />
    </>
  );
}

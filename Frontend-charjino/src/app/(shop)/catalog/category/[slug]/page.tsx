import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getCatalogCategories, listCatalogProducts } from '@/actions/catalog.actions';
import { CatalogBrowseScreen } from '@/components/catalog/catalog-browse-screen';
import { brandHubBySlug } from '@/data/brand-hubs';
import { getCategoryLanding } from '@/data/category-landing';
import { browseCanonicalPath } from '@/lib/catalog-browse';
import { BROWSE_PER_PAGE } from '@/lib/catalog-family';
import { storefrontCardProduct } from '@/lib/catalog-page-data';
import { buildBrandHubLanding, buildBrandHubSeoDescription } from '@/lib/brand-hub-landing';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata, categoryDescription, categoryDocumentTitle } from '@/lib/seo';

export const revalidate = 3600;
export const dynamic = 'force-static';
export const dynamicParams = true;

type CategoryPageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateStaticParams() {
  const result = await getCatalogCategories();
  if (!result.ok) {
    return [];
  }
  return result.items
    .map((item) => item.slug?.trim())
    .filter((slug): slug is string => Boolean(slug))
    .map((slug) => ({ slug }));
}

export async function generateMetadata({ params }: CategoryPageProps): Promise<Metadata> {
  const { slug } = await params;
  const result = await getCatalogCategories();
  const found = result.ok ? result.items.find((item) => item.slug === slug) : undefined;
  if (result.ok && !found) {
    notFound();
  }
  const hub = brandHubBySlug(slug);
  const listed = hub
    ? await listCatalogProducts({ category: slug, page: 1, perPage: BROWSE_PER_PAGE, sort: 'listed' })
    : null;
  const products = listed?.ok ? listed.data.items.map(storefrontCardProduct) : [];
  const hubLanding = hub ? buildBrandHubLanding(slug, products) : null;
  const landing = hubLanding || getCategoryLanding(slug);
  const hubDescription = hub ? buildBrandHubSeoDescription(hub, products) : '';

  return buildMetadata({
    title: categoryDocumentTitle(found?.title, found?.slug, found?.seoTitle || landing?.seoTitle),
    description: hubDescription
      || landing?.seoDescription
      || found?.seoDescription
      || (found?.title ? categoryDescription(found.title, undefined, found?.slug, found?.seoDescription) : PAGE_DESCRIPTIONS.shop),
    path: browseCanonicalPath(ROUTES.category(slug)),
  });
}

export default async function CategoryPage({ params }: CategoryPageProps) {
  const { slug } = await params;

  return (
    <CatalogBrowseScreen
      category={slug}
      q=""
      platform=""
      region=""
      page={1}
      sort="listed"
    />
  );
}

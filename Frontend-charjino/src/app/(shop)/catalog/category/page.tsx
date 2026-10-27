import type { Metadata } from 'next';
import { notFound, permanentRedirect } from 'next/navigation';
import { getCatalogCategories } from '@/actions/catalog.actions';
import { CatalogBrowseScreen } from '@/components/catalog/catalog-browse-screen';
import { browseCanonicalPath, browseShouldNoindex, parseBrowseQuery } from '@/lib/catalog-browse';
import { GAME_KEY_PLATFORM_ALL, gameKeyPlatformLabel } from '@/lib/catalog-family';
import { ROUTES, catalogBrowseHref } from '@/lib/routes';
import { getCategoryLanding } from '@/data/category-landing';
import { PAGE_DESCRIPTIONS, SHOP_LISTING_TITLE, buildMetadata, categoryDescription, categoryDocumentTitle, searchDescription } from '@/lib/seo';

export const revalidate = 3600;

type BrowsePageProps = {
  searchParams: Promise<{
    category?: string;
    q?: string;
    platform?: string;
    region?: string;
    page?: string;
    sort?: string;
  }>;
};

export async function generateMetadata({ searchParams }: BrowsePageProps): Promise<Metadata> {
  const parsed = parseBrowseQuery(await searchParams);
  const noindex = browseShouldNoindex(parsed);

  if (parsed.category) {
    const result = await getCatalogCategories();
    const found = result.ok ? result.items.find((item) => item.slug === parsed.category) : undefined;
    if (result.ok && !found) {
      notFound();
    }
    const path = browseCanonicalPath(ROUTES.category(parsed.category), parsed);
    const facet = parsed.platform && parsed.platform !== GAME_KEY_PLATFORM_ALL ? gameKeyPlatformLabel(parsed.platform) : '';
    const landing = !parsed.q && !facet ? getCategoryLanding(parsed.category) : null;
    return buildMetadata({
      title: parsed.q
        ? `جستجو: ${parsed.q}`
        : categoryDocumentTitle(found?.title, found?.slug, found?.seoTitle || landing?.seoTitle),
      description: parsed.q
        ? searchDescription(parsed.q)
        : found?.seoDescription
          || landing?.seoDescription
          || (found?.title
            ? categoryDescription(found.title, facet || undefined, found?.slug, found?.seoDescription)
            : PAGE_DESCRIPTIONS.shop),
      path,
      noindex,
      follow: noindex ? true : undefined,
    });
  }

  const path = browseCanonicalPath(ROUTES.categories, parsed);
  return buildMetadata({
    title: parsed.q ? `جستجو: ${parsed.q}` : SHOP_LISTING_TITLE,
    description: parsed.q ? searchDescription(parsed.q) : PAGE_DESCRIPTIONS.shop,
    path,
    noindex,
    follow: noindex ? true : undefined,
  });
}

export default async function CatalogBrowsePage({ searchParams }: BrowsePageProps) {
  const parsed = parseBrowseQuery(await searchParams);

  if (parsed.category) {
    const result = await getCatalogCategories();
    if (result.ok && !result.items.some((item) => item.slug === parsed.category)) {
      notFound();
    }
    if (result.ok) {
      permanentRedirect(
        catalogBrowseHref({
          category: parsed.category,
          q: parsed.q,
          platform: parsed.platform,
          region: parsed.region,
          page: parsed.page,
          sort: parsed.sort,
        }),
      );
    }
  }

  return (
    <CatalogBrowseScreen
      category=""
      q={parsed.q}
      platform={parsed.platform}
      region={parsed.region}
      page={parsed.page}
      sort={parsed.sort}
    />
  );
}

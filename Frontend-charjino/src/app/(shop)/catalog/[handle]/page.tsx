import type { Metadata } from 'next';
import { getCatalogProduct, listCatalogProducts } from '@/actions/catalog.actions';
import { BuyGuideSection } from '@/components/catalog/buy-guide/buy-guide-section';
import { CatalogProductScreen } from '@/components/catalog/catalog-product-screen';
import { ProductLanding } from '@/components/catalog/product-landing';
import { JsonLd } from '@/components/seo/json-ld';
import { Container } from '@/components/ui';
import { faqItems, hasStructuredBuyGuide, howToSteps, presentBuyGuide } from '@/lib/buy-guide';
import { productLandingCopy } from '@/lib/product-landing';
import { PRERENDER_PRODUCT_LIMIT } from '@/lib/catalog-cache';
import { unwrapCatalogRecord } from '@/lib/catalog-lookup';
import { brandHubByHandle } from '@/data/brand-hubs';
import { breadcrumbListJsonLd, collectionPageJsonLd, faqPageJsonLd, howToJsonLd, productBreadcrumbItems, productJsonLd } from '@/lib/json-ld';
import { ROUTES } from '@/lib/routes';
import { initialFacets, packFiltersOf, visibleSkus } from '@/lib/sku-pack-filters';
import { isIndexableProduct } from '@/lib/seo-rules';
import { buildMetadata, productDescription, productDocumentTitle, productHeading } from '@/lib/seo';

export const revalidate = 3600;
export const dynamic = 'force-static';
export const dynamicParams = true;

type CatalogPageProps = {
  params: Promise<{ handle: string }>;
};

export async function generateStaticParams() {
  const handles: { handle: string }[] = [];
  const seen = new Set<string>();

  const take = (items: { handle?: string | null; title?: string | null }[]) => {
    for (const product of items) {
      if (handles.length >= PRERENDER_PRODUCT_LIMIT) {
        return;
      }
      const handle = product.handle?.trim();
      if (!handle || seen.has(handle) || !isIndexableProduct(product)) {
        continue;
      }
      seen.add(handle);
      handles.push({ handle });
    }
  };

  try {
    const popular = await listCatalogProducts({ popular: true, page: 1, perPage: 100 });
    if (popular.ok) {
      take(popular.data.items);
    }
    if (handles.length < PRERENDER_PRODUCT_LIMIT) {
      const morePopular = await listCatalogProducts({ popular: true, page: 2, perPage: 100 });
      if (morePopular.ok) {
        take(morePopular.data.items);
      }
    }
    for (let page = 1; handles.length < PRERENDER_PRODUCT_LIMIT && page <= 2; page += 1) {
      const listing = await listCatalogProducts({ page, perPage: 100 });
      if (!listing.ok) {
        break;
      }
      take(listing.data.items);
    }
  } catch {
    return handles;
  }

  return handles;
}

export async function generateMetadata({ params }: CatalogPageProps): Promise<Metadata> {
  const { handle } = await params;
  const product = unwrapCatalogRecord(await getCatalogProduct(handle));
  const indexable = isIndexableProduct(product);
  const title = productDocumentTitle(product);
  const description = productDescription(product);
  const metadata = buildMetadata({
    title,
    description,
    path: ROUTES.catalog(product.handle),
    image: product.thumbnail,
    noindex: !indexable,
    follow: true,
  });
  return {
    ...metadata,
    title: { absolute: title },
    openGraph: metadata.openGraph ? { ...metadata.openGraph, title } : metadata.openGraph,
    twitter: metadata.twitter ? { ...metadata.twitter, title } : metadata.twitter,
  };
}

export default async function CatalogPage({ params }: CatalogPageProps) {
  const { handle } = await params;
  const product = unwrapCatalogRecord(await getCatalogProduct(handle));
  const indexable = isIndexableProduct(product);
  const guide = presentBuyGuide(product.buyGuide);
  const howto = howToSteps(guide);
  const faqs = hasStructuredBuyGuide(guide) ? faqItems(guide) : productLandingCopy(product).faqs;
  const howtoLd = howto
    ? howToJsonLd({
        name: guide?.title || `راهنمای خرید ${product.title}`,
        description: guide?.summary,
        steps: howto.steps,
      })
    : null;
  const listedSkus = visibleSkus(product.skus ?? [], initialFacets(packFiltersOf(product)));
  const productHub = brandHubByHandle(product.handle);

  return (
    <>
      <JsonLd data={breadcrumbListJsonLd(productBreadcrumbItems(product))} />
      <JsonLd data={productJsonLd(product, listedSkus)} />
      {indexable && productHub ? (
        <JsonLd
          data={collectionPageJsonLd({
            name: productHeading(product),
            description: productDescription(product),
            path: ROUTES.catalog(product.handle),
            items: (product.similar ?? []).slice(0, 20).map((item) => ({
              name: item.title,
              path: ROUTES.catalog(item.handle),
            })),
          })}
        />
      ) : null}
      {indexable && howtoLd ? <JsonLd data={howtoLd} /> : null}
      {indexable && faqs.length > 0 ? (
        <JsonLd data={faqPageJsonLd(faqs.map((item) => ({ name: item.q, text: item.a })))} />
      ) : null}
      <CatalogProductScreen
        product={product}
        skus={product.skus ?? []}
        returnTo={ROUTES.catalog(product.handle)}
      />
      {hasStructuredBuyGuide(guide) ? (
        <Container className="min-w-0 pb-10">
          <BuyGuideSection guide={guide} product={product} />
        </Container>
      ) : (
        <Container className="min-w-0 pb-10">
          <ProductLanding product={product} />
        </Container>
      )}
    </>
  );
}

import Link from 'next/link';
import { CatalogCover } from '@/components/catalog/catalog-cover';
import { CatalogProductCard } from '@/components/catalog/catalog-product-card';
import { LiveMinPrice } from '@/components/catalog/live-min-price';
import { FavoriteToggle } from '@/components/profile/favorite-toggle';
import { ProductBuySession } from '@/components/catalog/product-buy-session';
import { ProductComments } from '@/components/catalog/product-comments';
import { ProductDescription } from '@/components/catalog/product-description';
import { Container, LocationIcon, MonitorIcon, WaveDivider } from '@/components/ui';
import { gameKeyCardPlatformLabel, gameKeyPlatformLabel, gameKeyStorefrontId, isGameKeyProduct } from '@/lib/catalog-family';
import { isOrderable, lastKnownFromPriceLabel } from '@/lib/catalog-orderable';
import { formatFromToman } from '@/lib/price';
import { ROUTES, catalogBrowseHref } from '@/lib/routes';
import { isSteamHeader } from '@/lib/catalog-image';
import { catalogRegionLabel } from '@/lib/catalog-region';
import { productImageAlt } from '@/lib/image-alt';
import { storefrontBuyProduct, storefrontSimilarProducts } from '@/lib/catalog-page-data';
import { hasStructuredBuyGuide } from '@/lib/buy-guide';
import { productHeading } from '@/lib/seo';
import type { CatalogProduct, CatalogSku, PublicCategory } from '@/types/catalog';

type CatalogProductScreenProps = {
  product: CatalogProduct;
  skus: CatalogSku[];
  returnTo: string;
  category?: Pick<PublicCategory, 'slug' | 'title'> | null;
  initialSkuFilters?: Record<string, string | undefined>;
};

export function CatalogProductScreen({
  product,
  skus,
  returnTo,
  category = product.category ?? null,
  initialSkuFilters,
}: CatalogProductScreenProps) {
  const fromPrice = isOrderable(product) ? formatFromToman(product.minPrice) : null;
  const lastPrice = lastKnownFromPriceLabel(product, skus);

  const info = (
    <>
      <nav className="flex flex-wrap items-center gap-2 text-sm text-text-muted">
        <Link href={ROUTES.home} className="hover:text-primary-600">
          خانه
        </Link>
        <span className="text-text/25">/</span>
        <Link href={ROUTES.categories} className="hover:text-primary-600">
          فروشگاه
        </Link>
        {category?.slug ? (
          <>
            <span className="text-text/25">/</span>
            <Link href={catalogBrowseHref({ category: category.slug })} className="text-primary-600">
              {category.title}
            </Link>
            {isGameKeyProduct(product) && product.platform ? (
              <>
                <span className="text-text/25">/</span>
                <Link
                  href={catalogBrowseHref({
                    category: category.slug,
                    platform: gameKeyStorefrontId(product.platform) || product.platform,
                  })}
                  className="text-primary-600"
                >
                  {gameKeyPlatformLabel(gameKeyStorefrontId(product.platform) || product.platform)}
                </Link>
              </>
            ) : null}
          </>
        ) : product.categoryName ? (
          <>
            <span className="text-text/25">/</span>
            <span>{product.categoryName}</span>
          </>
        ) : null}
      </nav>
      <div className="flex items-start justify-between gap-3">
        <h1 className="break-words text-xl font-bold leading-8 text-text sm:text-3xl sm:leading-tight">{productHeading(product)}</h1>
        <FavoriteToggle productId={product.id} returnTo={ROUTES.catalog(product.handle)} />
      </div>
      {product.publisher ? <p className="text-sm text-text-muted">{product.publisher}</p> : null}
      {product.platform || product.region ? (
        <div className="flex flex-wrap items-center gap-2">
          {product.platform ? (
            <span className="inline-flex items-center gap-1 rounded-lg bg-card px-2.5 py-1 text-xs text-text shadow-card ring-1 ring-border">
              <MonitorIcon size={14} />
              {isGameKeyProduct(product)
                ? gameKeyCardPlatformLabel(product.platform) || product.platform
                : gameKeyPlatformLabel(product.platform) || product.platform}
            </span>
          ) : null}
          {product.region ? (
            <span className="inline-flex items-center gap-1 rounded-lg bg-card px-2.5 py-1 text-xs text-text shadow-card ring-1 ring-border">
              <LocationIcon size={14} />
              {product.region}
            </span>
          ) : null}
          {product.regionRestriction ? (
            <span className="inline-flex items-center rounded-lg bg-card px-2.5 py-1 text-xs text-text shadow-card ring-1 ring-border">
              محدودیت کشور
            </span>
          ) : null}
        </div>
      ) : null}
      {product.giftProxy ? (
        <p className="rounded-2xl bg-card px-4 py-3 text-sm leading-7 text-text-muted ring-1 ring-border">
          این محصول مستقیم از تأمین‌کننده فروخته نمی‌شود. با خرید هر بسته، گیفت‌کارت همان پلتفرم و ریجن، هم‌اندازهٔ همان
          بسته، تحویل می‌شود.
        </p>
      ) : null}
      {fromPrice ? (
        <p className="text-lg font-bold ">
          <LiveMinPrice handle={product.handle} initialLabel={fromPrice} initialMinPrice={product.minPrice} />
        </p>
      ) : null}
      {lastPrice ? <p className="text-sm text-text-muted">{lastPrice}</p> : null}
      <ProductDescription html={product.descriptionHtml} text={product.description || product.subtitle} />
    </>
  );

  const wide = isSteamHeader(product.thumbnail);

  return (
    <>
      <div className="bg-surface">
        <Container className="py-6 lg:py-0">
          <div className="flex flex-col gap-6 lg:grid lg:grid-cols-12 lg:items-start lg:gap-6">
            <div className="lg:sticky lg:top-(--shop-chrome-top) lg:z-10 lg:col-span-3 lg:self-start lg:py-8">
              <div
                className={`relative w-full overflow-hidden rounded-2xl bg-card shadow-card ring-1 ring-border ${
                  wide ? 'aspect-[460/215]' : 'aspect-[4/6]'
                }`}
              >
                <CatalogCover
                  src={product.thumbnail}
                  alt={productImageAlt(product.title, catalogRegionLabel(product.region))}
                  fill
                  fit="cover"
                  priority
                  sizes="(min-width: 1024px) 40vw, 100vw"
                />
              </div>
            </div>
            <ProductBuySession
              product={storefrontBuyProduct(product)}
              skus={skus}
              returnTo={returnTo}
              info={info}
              initialSkuFilters={initialSkuFilters}
              hasStructuredGuide={hasStructuredBuyGuide(product.buyGuide)}
            />
          </div>
          <div className="border-t border-border/70 pb-10 pt-8 lg:pb-12">
            <ProductComments handle={product.handle} />
          </div>
        </Container>
      </div>
      <WaveDivider />
      {product.similar && product.similar.length > 0 ? (
        <Container className="min-w-0 pb-10">
          <h2 className="mb-4 text-lg font-semibold text-text">محصولات مشابه</h2>
          <ul className="grid grid-cols-2 items-stretch gap-4 sm:grid-cols-4 sm:gap-x-6 sm:gap-y-8">
            {storefrontSimilarProducts(product.similar).map((item) => (
              <li key={item.handle} className="min-w-0">
                <CatalogProductCard product={item} />
              </li>
            ))}
          </ul>
        </Container>
      ) : null}
    </>
  );
}

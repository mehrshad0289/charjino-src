import {
  storefrontFlashSaleItem,
  storefrontHomeListingProduct,
} from '@/lib/home-listing-card';
import type { CatalogHome, CatalogProduct, PublicCategory, PublicProduct } from '@/types/catalog';

export function storefrontHomeCatalog(catalog: CatalogHome): CatalogHome {
  return {
    ...catalog,
    flashSale: catalog.flashSale.map((item) => storefrontFlashSaleItem(item)),
    groups: catalog.groups.map((group) => ({
      id: group.id,
      slug: group.slug,
      name: group.name,
      products: [],
    })),
    popular: catalog.popular.map((item) =>
      storefrontCardProduct(storefrontHomeListingProduct(item)),
    ),
  };
}

export function storefrontCardProduct(product: PublicProduct): CatalogProduct {
  const item = product as CatalogProduct;
  return {
    id: item.id,
    handle: item.handle,
    title: item.title,
    titleEn: item.titleEn ?? null,
    thumbnail: item.thumbnail,
    publisher: item.publisher ?? '',
    minPrice: item.minPrice,
    platform: item.platform,
    region: item.region,
    categoryName: item.categoryName ?? '',
    isPopular: Boolean(item.isPopular),
    fulfillmentType: item.fulfillmentType,
    giftProxy: item.giftProxy,
    regionRestriction: item.regionRestriction,
    available: item.available,
    isOrderable: item.isOrderable,
    code: item.code ?? '',
    inputs: [],
    subtitle: item.subtitle ?? '',
    shortDescription: item.shortDescription ?? item.subtitle ?? '',
  };
}

export function storefrontNavCategories(items: PublicCategory[]): PublicCategory[] {
  return items.map((item) => ({
    id: item.id,
    slug: item.slug,
    title: item.title,
    titleEn: item.titleEn ?? null,
    description: null,
    imageUrl: item.imageUrl ?? null,
    productsCount: item.productsCount,
    seoTitle: item.seoTitle,
    seoDescription: item.seoDescription,
  }));
}

export function storefrontBuyProduct(product: CatalogProduct): CatalogProduct {
  return {
    ...product,
    description: undefined,
    descriptionHtml: undefined,
    seoTitle: undefined,
    seoDescription: undefined,
    searchTags: [],
    similar: storefrontSimilarProducts(product.similar),
    alternate: product.alternate ? storefrontCardProduct(product.alternate) : product.alternate,
    buyGuide: product.buyGuide?.legacyItems?.length
      ? { legacyItems: product.buyGuide.legacyItems }
      : null,
    category: product.category
      ? {
          id: product.category.id,
          slug: product.category.slug,
          title: product.category.title,
          purchaseGuide: product.category.purchaseGuide,
          purchaseGuideItems: product.category.purchaseGuideItems,
        }
      : product.category,
  };
}

export function storefrontSimilarProducts(items: PublicProduct[] | undefined) {
  return (items ?? []).map(storefrontCardProduct);
}

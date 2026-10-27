'use client';

import dynamic from 'next/dynamic';
import { useEffect, useState, type ReactNode } from 'react';
import { getSessionUser } from '@/actions/auth.actions';
import { mergeLiveCatalogPrices } from '@/lib/catalog-live-price';
import { parseSkuIdParam } from '@/lib/catalog-sku-param';
import type { CatalogProduct, CatalogSku } from '@/types/catalog';

const ProductBuyForm = dynamic(
  () => import('@/components/catalog/product-buy-form').then((mod) => mod.ProductBuyForm),
  { ssr: true },
);

type ProductBuySessionProps = {
  product: CatalogProduct;
  skus: CatalogSku[];
  returnTo: string;
  info?: ReactNode;
  initialSkuFilters?: Record<string, string | undefined>;
  hasStructuredGuide?: boolean;
};

export function ProductBuySession({
  product,
  skus,
  returnTo,
  info,
  initialSkuFilters,
  hasStructuredGuide,
}: ProductBuySessionProps) {
  const [signedIn, setSignedIn] = useState(false);
  const [liveSkus, setLiveSkus] = useState(skus);
  const [liveProduct, setLiveProduct] = useState(product);
  const [skuFilters, setSkuFilters] = useState(initialSkuFilters);
  const [urlSkuId, setUrlSkuId] = useState<number | null>(null);

  useEffect(() => {
    let cancelled = false;
    void getSessionUser().then((user) => {
      if (cancelled) {
        return;
      }
      setSignedIn(Boolean(user));
    });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    setLiveSkus(skus);
    setLiveProduct(product);
    let cancelled = false;
    void mergeLiveCatalogPrices(product.handle, product, skus).then((next) => {
      if (cancelled) return;
      setLiveProduct(next.product);
      setLiveSkus(next.skus);
    });
    return () => {
      cancelled = true;
    };
  }, [product, skus]);

  useEffect(() => {
    const query = new URLSearchParams(window.location.search);
    setUrlSkuId(parseSkuIdParam(query.get('sku')));
    const fromUrl = {
      region: query.get('region')?.trim() || initialSkuFilters?.region,
      edition: query.get('edition')?.trim() || initialSkuFilters?.edition,
      denomination: query.get('pack')?.trim() || initialSkuFilters?.denomination,
      duration: query.get('duration')?.trim() || initialSkuFilters?.duration,
    };
    if (fromUrl.region || fromUrl.edition || fromUrl.denomination || fromUrl.duration) {
      setSkuFilters(fromUrl);
    }
  }, [initialSkuFilters]);

  return (
    <ProductBuyForm
      key={urlSkuId ?? 'auto'}
      product={liveProduct}
      skus={liveSkus}
      signedIn={signedIn}
      returnTo={returnTo}
      info={info}
      initialSkuFilters={skuFilters}
      initialSkuId={urlSkuId}
      hasStructuredGuide={hasStructuredGuide}
    />
  );
}

'use client';

import { HomeProductCard } from '@/components/home/home-product-card';
import { Container } from '@/components/ui';
import { DynamicSwiper } from '@/components/ui/swiper/dynamic-swiper';
import type { CatalogProduct } from '@/types/catalog';

type HomePopularProps = {
  products: CatalogProduct[];
  title?: string;
  subtitle?: string;
};

export function HomePopular({ products, title, subtitle }: HomePopularProps) {
  if (products.length === 0) return null;

  const canSlide = products.length > 1;

  return (
    <Container className="flex min-w-0 flex-col gap-5">
      <div className="flex flex-col">
        <h2 className="text-lg font-semibold tracking-wider uppercase leading-relaxed">
          🔥 {title ?? 'محبوب‌های امروز'}
        </h2>
        <p className="text-xs">{subtitle ?? 'چند محصول که همین حالا بیشترین تقاضا را دارند.'}</p>
      </div>
      <div className="min-w-0 w-full">
        <DynamicSwiper
          slidesPerView={1}
          spaceBetween={16}
          freeMode
          grabCursor={canSlide}
          allowTouchMove={canSlide}
          rewind={canSlide}
          autoHeight
          speed={700}
          autoplay={
            canSlide
              ? { delay: 2600, disableOnInteraction: false, pauseOnMouseEnter: true }
              : false
          }
          className="w-full max-w-full"
          slideClassName="!h-auto  py-1"
        >
          {products.map((product) => (
            <HomeProductCard key={product.id} product={product} />
          ))}
        </DynamicSwiper>
      </div>
    </Container>
  );
}

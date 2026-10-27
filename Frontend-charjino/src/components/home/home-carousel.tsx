'use client';

import Image from 'next/image';
import Link from 'next/link';
import { Autoplay, EffectCoverflow, Pagination } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/react';
import 'swiper/css';
import 'swiper/css/effect-coverflow';
import 'swiper/css/pagination';
import { catalogImageSrc } from '@/lib/catalog-image';
import { bannerImageAlt } from '@/lib/image-alt';
import { hideDuplicateSlideMedia } from '@/lib/swiper-a11y';
import type { HomeBanner } from '@/types/catalog';
import styles from './home-carousel.module.css';

type HomeCarouselProps = {
  banners: HomeBanner[];
};

function mediaKind(banner: HomeBanner) {
  if (banner.mediaType) return banner.mediaType;
  const src = (banner.src ?? '').toLowerCase();
  if (src.endsWith('.mp4') || src.endsWith('.webm') || src.endsWith('.mov')) return 'video';
  if (src.endsWith('.gif')) return 'gif';
  return 'image';
}

function BannerMedia({
  banner,
  priority,
  decorative = false,
}: {
  banner: HomeBanner;
  priority: boolean;
  decorative?: boolean;
}) {
  const kind = mediaKind(banner);
  const src = catalogImageSrc(banner.src);
  const frame =
    'relative aspect-[2/2.5]  overflow-hidden rounded-3xl bg-card-muted shadow-lg';

  const alt = decorative ? '' : bannerImageAlt(banner);

  if (kind === 'video') {
    return (
      <div className={frame}>
        <video
          src={src}
          className="absolute inset-0 h-full w-full object-cover"
          autoPlay
          muted
          loop
          playsInline
          preload={priority ? 'auto' : 'metadata'}
          aria-label={decorative ? undefined : alt}
          aria-hidden={decorative ? true : undefined}
        />
      </div>
    );
  }

  return (
    <div className={frame}>
      <Image
        src={src}
        alt={alt}
        fill
        sizes="(min-width: 768px) 300px, 63vw"
        priority={priority}
        fetchPriority={priority ? 'high' : 'low'}
        loading={priority ? 'eager' : 'lazy'}
        aria-hidden={decorative ? true : undefined}
        className="object-cover"
      />
    </div>
  );
}

export function HomeCarousel({ banners }: HomeCarouselProps) {
  if (banners.length === 0) return null;

  const first = (
    <Link href={banners[0].href || '#'} className="block w-full">
      <BannerMedia banner={banners[0]} priority />
    </Link>
  );

  if (banners.length === 1) {
    return (
      <div className="flex w-full justify-center overflow-x-hidden px-4 py-7">
        <div className="w-[280px] md:w-[300px]">{first}</div>
      </div>
    );
  }

  const enableLoop = banners.length >= 3;

  return (
    <div className="w-full overflow-x-hidden">
      <Swiper
        className={styles.swiper}
        effect="coverflow"
        grabCursor
        centeredSlides
        slidesPerView="auto"
        loop={enableLoop}
        rewind={!enableLoop}
        onSwiper={(swiper) => hideDuplicateSlideMedia(swiper.el)}
        onResize={(swiper) => hideDuplicateSlideMedia(swiper.el)}
        onLoopFix={(swiper) => hideDuplicateSlideMedia(swiper.el)}
        onUpdate={(swiper) => hideDuplicateSlideMedia(swiper.el)}
        autoplay={{ delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true }}
        coverflowEffect={{
          rotate: 50,
          stretch: 0,
          depth: 0,
          modifier: 1,
          slideShadows: false,
        }}
        pagination={{
          el: '.js-home-carousel-pagination',
          clickable: true,
        }}
        modules={[EffectCoverflow, Pagination, Autoplay]}
      >
        {banners.map((banner, index) => (
          <SwiperSlide key={banner.id}>
            <Link href={banner.href || '#'} className="block w-full pb-6">
              <BannerMedia banner={banner} priority={index === 0} />
            </Link>
          </SwiperSlide>
        ))}
      </Swiper>
      <div className={`js-home-carousel-pagination ${styles.pagination}`} />
    </div>
  );
}

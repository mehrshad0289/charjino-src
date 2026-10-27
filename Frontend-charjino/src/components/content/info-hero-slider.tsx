'use client';

import Image from 'next/image';
import { BaseSwiper } from '@/components/ui/swiper';

import styles from './info-page.module.css';

export type InfoHeroSlide = {
  src: string;
  alt: string;
};

type InfoHeroSliderProps = {
  slides: InfoHeroSlide[];
};

export function InfoHeroSlider({ slides }: InfoHeroSliderProps) {
  if (slides.length === 0) {
    return null;
  }

  const frame = (slide: InfoHeroSlide, priority: boolean) => (
    <div className={styles.slide}>
      <Image
        src={slide.src}
        alt={slide.alt}
        fill
        priority={priority}
        sizes="(max-width: 768px) 100vw, 760px"
        className={styles.cover}
      />
    </div>
  );

  if (slides.length === 1) {
    return <div className={styles.visual}>{frame(slides[0], true)}</div>;
  }

  return (
    <div className={`${styles.visual} ${styles.slider}`}>
      <BaseSwiper
        loop
        autoplay={{ delay: 4200, disableOnInteraction: false, pauseOnMouseEnter: true }}
        speed={800}
        freeMode={false}
        slidesPerView={1}
        spaceBetween={0}
        slideClassName="!h-auto"
        className="overflow-hidden"
      >
        {slides.map((slide, index) => frame(slide, index === 0))}
      </BaseSwiper>
    </div>
  );
}

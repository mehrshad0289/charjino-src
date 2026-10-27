'use client';

import { Autoplay, FreeMode } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/react';
import 'swiper/css';
import 'swiper/css/free-mode';
import { hideDuplicateSlideMedia } from '@/lib/swiper-a11y';
import type { BaseSwiperProps } from '@/types/swiper';

export function BaseSwiper({
  children,
  breakpoints,
  spaceBetween = 20,
  slidesPerView = 1,
  centeredSlides = false,
  className = '',
  slideClassName = '!h-auto pb-2',
  dir = 'rtl',
  direction = 'horizontal',
  loop = false,
  speed,
  autoplay = false,
  freeMode = true,
  grabCursor = true,
  allowTouchMove = true,
  rewind = false,
  autoHeight = false,
  watchOverflow = true,
  onSwiper,
}: BaseSwiperProps) {
  return (
    <Swiper
      className={className}
      wrapperClass="items-stretch"
      modules={[FreeMode, Autoplay]}
      dir={dir}
      direction={direction}
      grabCursor={grabCursor}
      allowTouchMove={allowTouchMove}
      freeMode={{
        enabled: freeMode,
        momentum: true,
        momentumRatio: 0.9,
        momentumVelocityRatio: 0.9,
        momentumBounce: false,
      }}
      resistanceRatio={freeMode ? 0.72 : 0.85}
      observer
      observeParents
      loop={loop}
      rewind={rewind}
      autoHeight={autoHeight}
      watchOverflow={watchOverflow}
      onSwiper={(swiper) => {
        hideDuplicateSlideMedia(swiper.el);
        onSwiper?.(swiper);
        if (autoplay) {
          swiper.autoplay?.start();
        }
      }}
      onLoopFix={(swiper) => hideDuplicateSlideMedia(swiper.el)}
      onUpdate={(swiper) => hideDuplicateSlideMedia(swiper.el)}
      speed={speed}
      autoplay={
        autoplay === true
          ? { delay: 2500, disableOnInteraction: false }
          : autoplay
      }
      spaceBetween={spaceBetween}
      slidesPerView={slidesPerView}
      centeredSlides={centeredSlides}
      breakpoints={breakpoints}
    >
      {children.map((slide, index) => (
        <SwiperSlide className={slideClassName} key={index}>
          {slide}
        </SwiperSlide>
      ))}
    </Swiper>
  );
}

import type { ReactNode } from 'react';
import type { Swiper as SwiperInstance } from 'swiper';
import type { AutoplayOptions, SwiperOptions } from 'swiper/types';

export interface BaseSwiperProps {
  children: ReactNode[];
  breakpoints?: SwiperOptions['breakpoints'];
  spaceBetween?: number;
  slidesPerView?: number | 'auto';
  centeredSlides?: boolean;
  className?: string;
  slideClassName?: string;
  dir?: 'rtl' | 'ltr';
  direction?: SwiperOptions['direction'];
  loop?: boolean;
  speed?: number;
  autoplay?: boolean | AutoplayOptions;
  freeMode?: boolean;
  grabCursor?: boolean;
  allowTouchMove?: boolean;
  rewind?: boolean;
  autoHeight?: boolean;
  watchOverflow?: boolean;
  onSwiper?: (swiper: SwiperInstance) => void;
}

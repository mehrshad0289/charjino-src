'use client';

import dynamic from 'next/dynamic';
import type { BaseSwiperProps } from '@/types/swiper';

const LoadedSwiper = dynamic(() => import('./base-swiper').then((mod) => mod.BaseSwiper), {
  ssr: true,
});

export function DynamicSwiper(props: BaseSwiperProps) {
  return <LoadedSwiper {...props} />;
}

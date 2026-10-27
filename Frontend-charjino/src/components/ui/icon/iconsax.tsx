import { ArrowLeft, ArrowLeft2, ArrowRight, Calendar, Discover } from 'iconsax-react';
import { linear, type IconProps } from './types';

export function CalendarIcon({ size = 24 }: IconProps) {
  return <Calendar size={size} {...linear} />;
}

export function ArrowLeftIcon2({ size = 24 }: IconProps) {
  return <ArrowLeft2 size={size} {...linear} />;
}
export function ArrowLeftIcon({ size = 24 }: IconProps) {
  return <ArrowLeft size={size} {...linear} />;
}
export function ArrowRightIcon({ size = 24 }: IconProps) {
  return <ArrowRight size={size} {...linear} />;
}

export function ButtonGlyphIcon({ size = 20 }: IconProps) {
  return <Discover size={size} {...linear} />;
}

import { ROUTES } from '@/lib/routes';

export const INFO_HERO_SLIDES = [
  {
    src: '/image/pages/about.jpg',
    alt: 'درباره شارژینو؛ فروشگاه شارژ بازی و گیفت کارت',
    href: ROUTES.about,
  },
  {
    src: '/image/pages/contact.jpg',
    alt: 'تماس با پشتیبانی شارژینو',
    href: ROUTES.contact,
  },
  {
    src: '/image/pages/terms.jpg',
    alt: 'قوانین و مقررات خرید در شارژینو',
    href: ROUTES.terms,
  },
  {
    src: '/image/pages/rules.svg',
    alt: 'سیاست حریم خصوصی شارژینو',
    href: ROUTES.privacy,
  },
] as const;

export const INFO_TOPIC_CARDS = [
  { href: ROUTES.about, title: 'درباره ما', text: 'شارژ بازی ریالی' },
  { href: ROUTES.contact, title: 'تماس با ما', text: 'تلفن و پشتیبانی' },
  { href: ROUTES.terms, title: 'قوانین', text: 'بدون رمز اکانت' },
  { href: ROUTES.privacy, title: 'حریم خصوصی', text: 'داده و کوکی' },
] as const;

export function infoSlidesFor(href: string) {
  const slides = [...INFO_HERO_SLIDES];
  const index = slides.findIndex((slide) => slide.href === href);
  if (index <= 0) {
    return slides;
  }
  return [...slides.slice(index), ...slides.slice(0, index)];
}

import { SOCIAL } from '@/lib/social';

export const NAV_ITEMS = [
  { title: 'خانه', href: '/' },
  { title: 'فروشگاه', href: '/catalog/category' },
  { title: 'وبلاگ', href: '/blog' },
  { title: 'درباره ما', href: '/about' },
  { title: 'تماس با ما', href: '/contact' },
  { title: 'قوانین', href: '/terms' },
] as const;

export const FOOTER_COLUMNS = [
  {
    title: 'نقشه سایت',
    items: [
      { title: 'خانه', href: '/' },
      { title: 'فروشگاه', href: '/catalog/category' },
      { title: 'ورود', href: '/auth' },
      { title: 'ثبت‌نام', href: '/auth' },
      { title: 'حساب من', href: '/profile' },
      { title: 'پیگیری سفارش', href: '/invoices' },
      { title: 'درباره ما', href: '/about' },
      { title: 'تماس با ما', href: '/contact' },
      { title: 'جدول برترین‌ها (به‌زودی)', href: '/leaderboard' },
    ],
  },
  {
    title: 'پشتیبانی',
    items: [
      { title: 'تماس با ما', href: '/contact' },
      { title: 'پرداخت شده، شارژ نشد', href: '/uncredited' },
      { title: SOCIAL.instagram.title, href: SOCIAL.instagram.href },
      { title: SOCIAL.telegram.title, href: SOCIAL.telegram.href },
    ],
  },
  {
    title: 'قوانین',
    items: [
      { title: 'حریم خصوصی', href: '/privacy' },
      { title: 'قوانین و مقررات', href: '/terms' },
    ],
  },
] as const;

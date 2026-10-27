import type { SidebarNavItem } from '@/types/sidebar';
import { FAZER_CARDS_NAV } from '@/lib/fazer-cards-nav';

export const SIDEBAR_GROUPS = {
  menu: 'منو',
  support: 'پشتیبانی',
  others: 'سایر / نمونه‌ها'
} as const;

export const SIDEBAR_NAV_ITEMS: SidebarNavItem[] = [
  {
    key: 'dashboard',
    label: 'پیشخوان',
    icon: 'grid',
    subItems: [{ key: 'home', label: 'فروشگاه', path: '/' }]
  },
  {
    key: 'homeCms',
    label: 'صفحه اصلی',
    icon: 'page',
    subItems: [
      { key: 'homeBanners', label: 'اسلایدر بنر', path: '/home/banners' },
      { key: 'homeSections', label: 'دسته‌های هوم', path: '/home/sections' },
      { key: 'homeFlash', label: 'فروش ویژه', path: '/home/flash-sale' },
      { key: 'homePopular', label: 'محبوب‌ها', path: '/home/popular' },
      { key: 'purchaseGuides', label: 'راهنمای خرید', path: '/content/buy-guides' },
      { key: 'blogLibrary', label: 'وبلاگ', path: '/content/blog' },
      { key: 'blogCategories', label: 'دسته‌های وبلاگ', path: '/content/blog/categories' },
      { key: 'blogTags', label: 'تگ‌های وبلاگ', path: '/content/blog/tags' },
      { key: 'blogComments', label: 'نظرهای وبلاگ', path: '/content/blog/comments' }
    ]
  },
  {
    key: 'catalog',
    label: 'کاتالوگ',
    icon: 'table',
    subItems: [
      { key: 'groups', label: 'دسته‌بندی‌ها', path: '/catalog/groups' },
      { key: 'products', label: 'محصولات', path: '/catalog/products' },
      { key: 'productComments', label: 'نظرهای محصولات', path: '/catalog/comments' },
      { key: 'excluded', label: 'مستثناها', path: '/catalog/excluded' },
      { key: 'search', label: 'جستجو و مترادف', path: '/catalog/search' }
    ]
  },
  {
    key: 'fazercards',
    label: 'تأمین‌کننده / FazerCards',
    icon: 'table',
    subItems: FAZER_CARDS_NAV.map((item) => ({
      key: `fc-${item.key}`,
      label: item.label,
      path: item.href
    }))
  },
  { key: 'orders', label: 'سفارش‌ها', icon: 'list', path: '/orders' },
  { key: 'supportTickets', label: 'تیکت‌های پشتیبانی', icon: 'chat', path: '/support' },
  { key: 'coupons', label: 'کد تخفیف', icon: 'list', path: '/coupons' },
  { key: 'users', label: 'کاربران', icon: 'user', path: '/users' },
  { key: 'pricing', label: 'نرخ دلار و سود', icon: 'list', path: '/pricing' }
];

export const SIDEBAR_SUPPORT_ITEMS: SidebarNavItem[] = [];

/** صفحات نمونه TailAdmin از منوی اصلی مخفی‌اند؛ فایل‌ها حذف نشده‌اند. */
export const SIDEBAR_OTHER_ITEMS: SidebarNavItem[] = [];

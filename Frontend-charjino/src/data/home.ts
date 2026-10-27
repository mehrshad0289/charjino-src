import type { FlashSaleItem, HomeBanner } from '@/types/catalog';

type FigmaDemoProduct = {
  title: string;
  publisher: string;
  handle: string;
  code: string;
  categoryName: string;
  subtitle: string;
  thumbnail: string;
  isPopular: boolean;
  inputs: string[];
};

function cover(name: string) {
  return `/image/catalog/${name}.svg`;
}

export const CATALOG_PRODUCTS: FigmaDemoProduct[] = [
  {
    title: 'پابجی موبایل',
    publisher: 'Tencent',
    handle: 'pubg-mobile',
    code: 'pubg-mobile',
    categoryName: 'شارژ بازی',
    subtitle: 'یوسی رسمی',
    thumbnail: cover('pubg-mobile'),
    isPopular: false,
    inputs: ['playerId'],
  },
  {
    title: 'فری فایر',
    publisher: 'Garena',
    handle: 'free-fire',
    code: 'free-fire',
    categoryName: 'شارژ بازی',
    subtitle: 'الماس',
    thumbnail: cover('free-fire'),
    isPopular: false,
    inputs: ['playerId'],
  },
  {
    title: 'کال آو دیوتی موبایل',
    publisher: 'Activision',
    handle: 'cod-mobile',
    code: 'cod-mobile',
    categoryName: 'شارژ بازی',
    subtitle: 'CP',
    thumbnail: cover('cod-mobile'),
    isPopular: false,
    inputs: ['playerId'],
  },
  {
    title: 'موبایل لجندز',
    publisher: 'Moonton',
    handle: 'mobile-legends',
    code: 'mobile-legends',
    categoryName: 'شارژ بازی',
    subtitle: 'الماس',
    thumbnail: cover('mobile-legends'),
    isPopular: false,
    inputs: ['playerId', 'zoneId'],
  },
  {
    title: 'کلش آف کلنز',
    publisher: 'Supercell',
    handle: 'clash-of-clans',
    code: 'clash-of-clans',
    categoryName: 'شارژ بازی',
    subtitle: 'جم',
    thumbnail: cover('clash-of-clans'),
    isPopular: false,
    inputs: ['playerTag'],
  },
  {
    title: 'گنشین ایمپکت',
    publisher: 'HoYoverse',
    handle: 'genshin-impact',
    code: 'genshin-impact',
    categoryName: 'شارژ بازی',
    subtitle: 'کریستال',
    thumbnail: cover('genshin-impact'),
    isPopular: false,
    inputs: ['uid', 'server'],
  },
  {
    title: 'هانکای استار ریل',
    publisher: 'HoYoverse',
    handle: 'honkai-star-rail',
    code: 'honkai-star-rail',
    categoryName: 'شارژ بازی',
    subtitle: 'واندار',
    thumbnail: cover('honkai-star-rail'),
    isPopular: false,
    inputs: ['uid', 'server'],
  },
  {
    title: 'روبلاکس',
    publisher: 'Roblox',
    handle: 'roblox',
    code: 'roblox',
    categoryName: 'شارژ بازی',
    subtitle: 'روبکس',
    thumbnail: cover('roblox'),
    isPopular: false,
    inputs: ['username'],
  },
  {
    title: 'فورتنایت',
    publisher: 'Epic Games',
    handle: 'fortnite',
    code: 'fortnite',
    categoryName: 'شارژ بازی',
    subtitle: 'ویباکس',
    thumbnail: cover('fortnite'),
    isPopular: false,
    inputs: ['epicId'],
  },
  {
    title: 'ولورانت',
    publisher: 'Riot Games',
    handle: 'valorant',
    code: 'valorant',
    categoryName: 'شارژ بازی',
    subtitle: 'VP',
    thumbnail: cover('valorant'),
    isPopular: false,
    inputs: ['riotId'],
  },
  {
    title: 'استیم والت',
    publisher: 'Valve',
    handle: 'steam',
    code: 'steam',
    categoryName: 'شارژ بازی',
    subtitle: 'کیف پول',
    thumbnail: cover('steam'),
    isPopular: false,
    inputs: ['email'],
  },
  {
    title: 'پلی‌استیشن',
    publisher: 'Sony',
    handle: 'playstation',
    code: 'playstation',
    categoryName: 'شارژ بازی',
    subtitle: 'کیف پول',
    thumbnail: cover('playstation'),
    isPopular: false,
    inputs: ['email'],
  },
];

export const CATALOG_VOUCHERS: FigmaDemoProduct[] = [
  {
    title: 'گوگل پلی',
    publisher: 'Google',
    handle: 'google-play',
    code: 'google-play',
    categoryName: 'گیفت‌کارت',
    subtitle: 'گیفت‌کارت',
    thumbnail: cover('google-play'),
    isPopular: false,
    inputs: ['email'],
  },
  {
    title: 'اپل گیفت',
    publisher: 'Apple',
    handle: 'apple-gift',
    code: 'apple-gift',
    categoryName: 'گیفت‌کارت',
    subtitle: 'گیفت‌کارت',
    thumbnail: cover('apple-gift'),
    isPopular: false,
    inputs: ['email'],
  },
  {
    title: 'ایکس‌باکس',
    publisher: 'Microsoft',
    handle: 'xbox',
    code: 'xbox',
    categoryName: 'گیفت‌کارت',
    subtitle: 'گیفت‌کارت',
    thumbnail: cover('xbox'),
    isPopular: false,
    inputs: ['email'],
  },
  {
    title: 'نینتندو',
    publisher: 'Nintendo',
    handle: 'nintendo',
    code: 'nintendo',
    categoryName: 'گیفت‌کارت',
    subtitle: 'گیفت‌کارت',
    thumbnail: cover('nintendo'),
    isPopular: false,
    inputs: ['email'],
  },
  {
    title: 'اسپاتیفای',
    publisher: 'Spotify',
    handle: 'spotify',
    code: 'spotify',
    categoryName: 'گیفت‌کارت',
    subtitle: 'گیفت‌کارت',
    thumbnail: cover('spotify'),
    isPopular: false,
    inputs: ['email'],
  },
  {
    title: 'پلی‌استیشن استور',
    publisher: 'Sony',
    handle: 'psn-store',
    code: 'psn-store',
    categoryName: 'گیفت‌کارت',
    subtitle: 'گیفت‌کارت',
    thumbnail: cover('playstation'),
    isPopular: false,
    inputs: ['email'],
  },
];

export const CATALOG_GROUPS: { name: string; products: FigmaDemoProduct[] }[] = [
  { name: 'شارژ بازی', products: CATALOG_PRODUCTS },
  { name: 'گیفت‌کارت', products: CATALOG_VOUCHERS },
];

export const POPULAR_PRODUCTS = CATALOG_PRODUCTS.filter((item) => item.isPopular).slice(0, 8);

export const HOME_BANNERS: HomeBanner[] = [
  {
    id: 1,
    src: '/image/banners/banner-1.svg',
    title: 'شارژ سریع و شفاف',
    href: '/',
    description: 'قیمت نهایی، رهگیری مرحله‌ای و بدون رمز حساب',
  },
  {
    id: 2,
    src: '/image/banners/banner-2.svg',
    title: 'فروش ویژه امروز',
    href: '/',
    description: 'بسته‌های منتخب با موجودی محدود',
  },
  {
    id: 3,
    src: '/image/banners/banner-3.svg',
    title: 'جدول برترین‌ها',
    href: '/leaderboard',
    description: 'افتخار از تلاش، نه از نتیجه مالی',
  },
];

export const FLASH_SALE_ITEMS: FlashSaleItem[] = [];

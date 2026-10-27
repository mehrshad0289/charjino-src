export type FcOpsSection =
  | 'wallet'
  | 'subscription'
  | 'orders'
  | 'ledger'
  | 'topups'
  | 'giftcards'
  | 'gamekeys'
  | 'steam-topup'
  | 'steam-gifts'
  | 'telegram'
  | 'manual'
  | 'funpay'
  | 'webhooks';

export type FcNavItem = {
  key: string;
  href: string;
  label: string;
  title: string;
  desc: string;
  section?: FcOpsSection | 'settings' | 'mapping';
};

export const FAZER_CARDS_NAV: FcNavItem[] = [
  {
    key: 'overview',
    href: '/catalog/fazercards',
    label: 'نمای کلی',
    title: 'نمای کلی FazerCards',
    desc: 'سلامت اتصال، پلن، والت USD و وضعیت سفارش زنده. کلید هرگز در مرورگر ذخیره نمی‌شود.'
  },
  {
    key: 'settings',
    href: '/catalog/fazercards/settings',
    label: 'تنظیمات',
    title: 'تنظیمات FazerCards',
    desc: 'کلید را فقط بک‌اند رمز می‌کند. سفارش زنده جدا از Shop2 است.',
    section: 'settings'
  },
  {
    key: 'wallet',
    href: '/catalog/fazercards/wallet',
    label: 'والت و پرداخت',
    title: 'والت و پرداخت',
    desc: 'روش‌های شارژ والت، حساب/موجودی، فاکتور و تأیید Binance Pay. فروشگاه این صفحه را ندارد.',
    section: 'wallet'
  },
  {
    key: 'subscription',
    href: '/catalog/fazercards/subscription',
    label: 'اشتراک',
    title: 'اشتراک تأمین‌کننده',
    desc: 'پلن bronze/silver/gold برای ۳۰ روز. فعال‌سازی فقط با تأیید؛ promo فقط خرید اول.',
    section: 'subscription'
  },
  {
    key: 'orders',
    href: '/catalog/fazercards/orders',
    label: 'سفارش‌ها',
    title: 'سفارش‌های FazerCards',
    desc: 'لیست صفحه‌بندی‌شده و جزئیات با شناسهٔ عمومی ord-…. سفارش فروشگاه جداست.',
    section: 'orders'
  },
  {
    key: 'ledger',
    href: '/catalog/fazercards/ledger',
    label: 'دفترکل',
    title: 'تراکنش‌های والت',
    desc: 'لیست و جزئیات تراکنش دلاری حساب Reseller.',
    section: 'ledger'
  },
  {
    key: 'topups',
    href: '/catalog/fazercards/topups',
    label: 'شارژ بازی',
    title: 'شارژ بازی',
    desc: 'دسته‌ها، آفرها، اعتبارسنجی شناسه (soft-skip) و سفارش تست در صورت باز بودن لایو.',
    section: 'topups'
  },
  {
    key: 'giftcards',
    href: '/catalog/fazercards/giftcards',
    label: 'گیفت کارت',
    title: 'گیفت کارت',
    desc: 'دسته‌ها، کارت‌ها و سفارش گیفت. بعد از همگام، محصولات در فروشگاه دیده می‌شوند.',
    section: 'giftcards'
  },
  {
    key: 'gamekeys',
    href: '/catalog/fazercards/gamekeys',
    label: 'کلید بازی',
    title: 'کلید بازی',
    desc: 'جستجو و افزودن کلید به فروشگاه. کاتالوگ کلید و محدودیت منطقه.',
    section: 'gamekeys'
  },
  {
    key: 'steam-topup',
    href: '/catalog/fazercards/steam-topup',
    label: 'شارژ استیم',
    title: 'شارژ استیم',
    desc: 'نرخ، بررسی لاگین استیم و سفارش شارژ کیف استیم.',
    section: 'steam-topup'
  },
  {
    key: 'steam-gifts',
    href: '/catalog/fazercards/steam-gifts',
    label: 'گیفت استیم',
    title: 'گیفت استیم',
    desc: 'جستجوی نام بازی (مثلاً FC 27) و افزودن به فروشگاه. همگام کل فهرست را وارد نمی‌کند.',
    section: 'steam-gifts'
  },
  {
    key: 'telegram',
    href: '/catalog/fazercards/telegram',
    label: 'تلگرام',
    title: 'ستاره و پرمیوم تلگرام',
    desc: 'استعلام قیمت و خرید ستاره یا پرمیوم. رمز تلگرام پرسیده نمی‌شود.',
    section: 'telegram'
  },
  {
    key: 'manual',
    href: '/catalog/fazercards/manual',
    label: 'سرویس دستی',
    title: 'سرویس دستی',
    desc: 'لیست سرویس، آفر، سفارش و گفتگو با اپراتور تأمین‌کننده.',
    section: 'manual'
  },
  {
    key: 'funpay',
    href: '/catalog/fazercards/funpay',
    label: 'FunPay',
    title: 'FunPay',
    desc: 'ورود FunPay فقط با اشتراک و ادون فعال. کلید پنل ذخیره و نمایش کامل نمی‌شود.',
    section: 'funpay'
  },
  {
    key: 'webhooks',
    href: '/catalog/fazercards/webhooks',
    label: 'وب‌هوک',
    title: 'وب‌هوک',
    desc: 'آدرس گیرنده چارژینو: /api/webhooks/fazercards. بازتولید secret و حذف وب‌هوک تأیید می‌خواهد.',
    section: 'webhooks'
  },
  {
    key: 'mapping',
    href: '/catalog/fazercards/mapping',
    label: 'نگاشت SKU',
    title: 'نگاشت SKU فروشگاه',
    desc: 'همگام جداگانهٔ هر دستهٔ FazerCards با فروشگاه. بستهٔ حذف‌شده از همان دسته دیگر قابل خرید نیست.',
    section: 'mapping'
  }
];

export function fazerCardsSectionMeta(section: string): FcNavItem | undefined {
  return FAZER_CARDS_NAV.find((item) => item.href === `/catalog/fazercards/${section}`);
}

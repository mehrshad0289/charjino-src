import type { ProfilePersonal, ProfileUser } from '@/types/profile';

export const PROFILE_COPY = {
  title: 'پروفایل',
  edit: 'ویرایش',
  firstName: 'نام',
  lastName: 'نام خانوادگی',
  email: 'ایمیل',
  phone: 'تلفن',
  bio: 'بیو',
  social: 'شبکه‌های اجتماعی',
  facebook: 'فیسبوک',
  twitter: 'X',
  linkedin: 'لینکدین',
  instagram: 'اینستاگرام',
  address: 'نشانی',
  country: 'کشور',
  city: 'شهر / استان',
  postalCode: 'کد پستی',
  taxId: 'شناسه مالیاتی',
  editPersonalTitle: 'ویرایش اطلاعات شخصی',
  editAddressTitle: 'ویرایش نشانی',
  editHint: 'جزئیات را به‌روز کنید تا پروفایل کامل بماند.',
  changePhoto: 'تغییر تصویر پروفایل',
  photoHint: 'یک تصویر مربعی (۲۰۰×۲۰۰ پیکسل) با فرمت JPEG یا PNG بارگذاری کنید.',
  personalInfo: 'اطلاعات شخصی',
  security: 'امنیت',
  changePassword: 'تغییر رمز عبور',
  changePasswordHint: 'اعلان‌های لحظه‌ای و هشدارهای تیم را دریافت کنید.',
  twoFactor: 'احراز هویت دو مرحله‌ای (۲FA)',
  twoFactorHint: 'با فعال‌کردن ۲FA حساب را امن نگه دارید.',
  danger: 'منطقه خطر',
  logoutAll: 'خروج از همه دستگاه‌ها',
  logoutAllHint: 'از تمام نشست‌های فعال خارج شوید.',
  logout: 'خروج',
  deleteAccount: 'حذف حساب',
  deleteAccountHint: 'پس از حذف حساب بازگشتی نیست. مطمئن شوید.',
  close: 'بستن',
  save: 'ذخیره تغییرات'
} as const;

export const PROFILE_USER: ProfileUser = {
  personal: {
    firstName: 'مدیر',
    lastName: 'سیستم',
    role: 'مدیر پنل',
    location: 'تهران، ایران',
    email: 'admin@boomobana.ir',
    phone: '۰۲۱-۹۱۰۰۰۰۰۰',
    bio: 'مدیر پنل',
    avatar: '/images/user/owner.png',
    facebook: '#',
    twitter: '#',
    linkedin: '#',
    instagram: '#'
  },
  address: {
    country: 'ایران',
    city: 'تهران، ایران',
    postalCode: '۱۹۱۵۷۱۳۴۱۱',
    taxId: '۱۴۰۰۴۳۳۹۵۵۷'
  }
};

export const PROFILE_EDIT_BUTTON_CLASS =
  'flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 lg:inline-flex lg:w-auto dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3 dark:hover:text-gray-200';

export const PROFILE_ADDRESS_EDIT_BUTTON_CLASS =
  'flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 lg:inline-flex lg:w-auto dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3 dark:hover:text-gray-200';

export const PROFILE_ACTION_BUTTON_CLASS =
  'flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white py-2.5 pe-4 ps-3.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3 dark:hover:text-gray-200';

export const PROFILE_DELETE_BUTTON_CLASS =
  'inline-flex h-10 cursor-pointer items-center justify-center gap-2 rounded-lg border border-error-500 py-2.5 pe-4 ps-3.5 text-sm font-medium text-error-500 transition-all hover:bg-error-100 dark:border-error-500/15 dark:hover:bg-red-500/15';

export function profileDisplayName(personal: ProfilePersonal) {
  return `${personal.firstName} ${personal.lastName}`.trim();
}

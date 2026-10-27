import type { HeaderNotification, HeaderUser } from '@/types/header';

export const HEADER_COPY = {
  searchPlaceholder: 'جستجو یا دستور را بنویسید...',
  toggleSidebar: 'باز و بسته کردن منو',
  notificationsTitle: 'اعلان‌ها',
  viewAllNotifications: 'مشاهده همه اعلان‌ها',
  editProfile: 'ویرایش پروفایل',
  accountSettings: 'تنظیمات حساب',
  support: 'پشتیبانی',
  signOut: 'خروج',
  requestsPermission: 'درخواست تغییر دسترسی برای',
  project: 'پروژه'
} as const;

export const HEADER_USER: HeaderUser = {
  displayName: 'مدیر',
  fullName: 'مدیر سیستم',
  email: 'admin@charjino.ir',
  avatar: '/images/user/owner.png'
};

export const HEADER_NOTIFICATIONS: HeaderNotification[] = [
  {
    key: 'n1',
    name: 'تری فرانسی',
    avatar: '/images/user/user-02.jpg',
    status: 'success',
    action: 'درخواست تغییر دسترسی برای',
    target: 'پروژه اپلیکیشن نگانتر',
    category: 'پروژه',
    time: '۵ دقیقه پیش'
  },
  {
    key: 'n2',
    name: 'آلینا فرانسی',
    avatar: '/images/user/user-03.jpg',
    status: 'success',
    action: 'درخواست تغییر دسترسی برای',
    target: 'پروژه اپلیکیشن نگانتر',
    category: 'پروژه',
    time: '۸ دقیقه پیش'
  },
  {
    key: 'n3',
    name: 'جاسولین کنتر',
    avatar: '/images/user/user-04.jpg',
    status: 'success',
    action: 'درخواست تغییر دسترسی برای',
    target: 'پروژه اپلیکیشن نگانتر',
    category: 'پروژه',
    time: '۱۵ دقیقه پیش'
  },
  {
    key: 'n4',
    name: 'برندون فیلیپس',
    avatar: '/images/user/user-05.jpg',
    status: 'error',
    action: 'درخواست تغییر دسترسی برای',
    target: 'پروژه اپلیکیشن نگانتر',
    category: 'پروژه',
    time: '۱ ساعت پیش'
  },
  {
    key: 'n5',
    name: 'تری فرانسی',
    avatar: '/images/user/user-02.jpg',
    status: 'success',
    action: 'درخواست تغییر دسترسی برای',
    target: 'پروژه اپلیکیشن نگانتر',
    category: 'پروژه',
    time: '۵ دقیقه پیش'
  },
  {
    key: 'n6',
    name: 'آلینا فرانسی',
    avatar: '/images/user/user-03.jpg',
    status: 'success',
    action: 'درخواست تغییر دسترسی برای',
    target: 'پروژه اپلیکیشن نگانتر',
    category: 'پروژه',
    time: '۸ دقیقه پیش'
  },
  {
    key: 'n7',
    name: 'جاسولین کنتر',
    avatar: '/images/user/user-04.jpg',
    status: 'success',
    action: 'درخواست تغییر دسترسی برای',
    target: 'پروژه اپلیکیشن نگانتر',
    category: 'پروژه',
    time: '۱۵ دقیقه پیش'
  },
  {
    key: 'n8',
    name: 'برندون فیلیپس',
    avatar: '/images/user/user-05.jpg',
    status: 'error',
    action: 'درخواست تغییر دسترسی برای',
    target: 'پروژه اپلیکیشن نگانتر',
    category: 'پروژه',
    time: '۱ ساعت پیش'
  }
];

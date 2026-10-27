import type { BasicTableRow } from '@/types/basic-table';

export const BASIC_TABLE_COPY = {
  pageTitle: 'جدول پایه',
  cardTitle: 'جدول پایه ۱',
  user: 'کاربر',
  project: 'نام پروژه',
  team: 'تیم',
  status: 'وضعیت',
  budget: 'بودجه',
  statusLabel: {
    active: 'فعال',
    pending: 'در انتظار',
    cancel: 'لغو شده'
  }
} as const;

export const BASIC_TABLE_ROWS: BasicTableRow[] = [
  {
    id: 1,
    user: { image: '/images/user/user-01.jpg', name: 'لینزی کورتیس', role: 'طراح وب' },
    projectName: 'وب‌سایت آژانس',
    team: { images: ['/images/user/user-02.jpg', '/images/user/user-03.jpg', '/images/user/user-04.jpg'] },
    budget: '۳٫۹ هزار',
    status: 'active'
  },
  {
    id: 2,
    user: { image: '/images/user/user-02.jpg', name: 'کایا جورج', role: 'مدیر پروژه' },
    projectName: 'فناوری',
    team: { images: ['/images/user/user-05.jpg', '/images/user/user-06.jpg'] },
    budget: '۲۴٫۹ هزار',
    status: 'pending'
  },
  {
    id: 3,
    user: { image: '/images/user/user-03.jpg', name: 'زین گایدت', role: 'تولید محتوا' },
    projectName: 'نوشتن بلاگ',
    team: { images: ['/images/user/user-01.jpg'] },
    budget: '۱۲٫۷ هزار',
    status: 'active'
  },
  {
    id: 4,
    user: { image: '/images/user/user-04.jpg', name: 'ابرام شلایفر', role: 'بازاریاب دیجیتال' },
    projectName: 'شبکه اجتماعی',
    team: { images: ['/images/user/user-02.jpg', '/images/user/user-05.jpg', '/images/user/user-06.jpg'] },
    budget: '۲٫۸ هزار',
    status: 'cancel'
  },
  {
    id: 5,
    user: { image: '/images/user/user-05.jpg', name: 'کارلا جورج', role: 'توسعه‌دهنده فرانت' },
    projectName: 'وب‌سایت',
    team: { images: ['/images/user/user-01.jpg', '/images/user/user-03.jpg', '/images/user/user-04.jpg'] },
    budget: '۴٫۵ هزار',
    status: 'active'
  }
];

export const COMMON_COPY = {
  home: 'خانه',
  viewMore: 'مشاهده بیشتر',
  delete: 'حذف',
  filter: 'فیلتر',
  seeAll: 'مشاهده همه',
  close: 'بستن',
  saveChanges: 'ذخیره تغییرات',
  openModal: 'باز کردن مودال',
  previous: 'قبلی',
  next: 'بعدی',
  pagination: 'صفحه‌بندی',
  tabs: 'زبانه‌ها',
  dropdown: 'دراپ‌داون'
} as const;

export const CHART_FONT = 'Yekan Bakh, Tahoma, sans-serif';

export const ECOMMERCE_MONTHS = [
  'ژانویه',
  'فوریه',
  'مارس',
  'آوریل',
  'مه',
  'ژوئن',
  'ژوئیه',
  'اوت',
  'سپتامبر',
  'اکتبر',
  'نوامبر',
  'دسامبر'
] as const;

export const ECOMMERCE_COPY = {
  customers: 'مشتریان',
  orders: 'سفارش‌ها',
  monthlySales: 'فروش ماهانه',
  sales: 'فروش',
  monthlyTarget: 'هدف ماهانه',
  monthlyTargetSubtitle: 'هدف تعیین‌شده برای هر ماه',
  earned: 'امروز ۳۲۸۷ دلار درآمد داشتید؛ بالاتر از ماه قبل است. همین روند را ادامه دهید.',
  target: 'هدف',
  revenue: 'درآمد',
  today: 'امروز',
  statistics: 'آمار',
  statisticsSubtitle: 'هدف تعیین‌شده برای هر ماه',
  monthly: 'ماهانه',
  quarterly: 'فصلی',
  annually: 'سالانه',
  selectDateRange: 'بازه تاریخ را انتخاب کنید',
  demographic: 'پراکندگی مشتریان',
  demographicSubtitle: 'تعداد مشتری بر اساس استان',
  tehran: 'تهران',
  isfahan: 'اصفهان',
  customersCount: (count: string) => `${count} مشتری`,
  recentOrders: 'سفارش‌های اخیر',
  products: 'محصولات',
  category: 'دسته‌بندی',
  price: 'قیمت',
  status: 'وضعیت',
  variants: (count: number) => (count === 1 ? '۱ نوع' : `${count} نوع`),
  categories: {
    laptop: 'لپ‌تاپ',
    watch: 'ساعت',
    smartphone: 'گوشی',
    electronics: 'الکترونیک',
    accessories: 'لوازم جانبی'
  },
  statuses: {
    delivered: 'تحویل‌شده',
    pending: 'در انتظار',
    canceled: 'لغوشده'
  }
} as const;

export const DEMOGRAPHIC_REGIONS = [
  { id: '17', name: ECOMMERCE_COPY.tehran, count: '۲٬۳۷۹', percent: '۷۹٪', barClass: 'w-[79%]' },
  { id: '24', name: ECOMMERCE_COPY.isfahan, count: '۵۸۹', percent: '۲۳٪', barClass: 'w-[23%]' }
] as const;

export type OrderCategory = keyof typeof ECOMMERCE_COPY.categories;
export type OrderStatus = keyof typeof ECOMMERCE_COPY.statuses;

export type RecentOrder = {
  id: number;
  name: string;
  variants: number;
  category: OrderCategory;
  price: string;
  image: string;
  status: OrderStatus;
};

export const RECENT_ORDERS: RecentOrder[] = [];

export const ECOMMERCE_METRICS = {
  customers: '۰',
  customersChange: '۰٪',
  orders: '۰',
  ordersChange: '۰٪'
} as const;

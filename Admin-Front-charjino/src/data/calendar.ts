import type { CalendarEvent, CalendarEventLevel, CalendarViewOption } from '@/types/calendar';

export const CALENDAR_COPY = {
  pageTitle: 'تقویم',
  addEventPlus: 'افزودن رویداد +',
  addEvent: 'افزودن رویداد',
  editEvent: 'ویرایش رویداد',
  event: 'رویداد',
  newEvent: 'رویداد جدید',
  modalDesc: 'رویداد بعدی را زمان‌بندی کنید یا رویداد موجود را ویرایش کنید تا برنامه منظم بماند.',
  eventTitle: 'عنوان رویداد',
  eventColor: 'رنگ رویداد',
  startDate: 'تاریخ شروع',
  endDate: 'تاریخ پایان',
  close: 'بستن',
  update: 'ذخیره تغییرات',
  more: 'مورد دیگر',
  conference: 'همایش',
  meeting: 'جلسه',
  workshop: 'کارگاه'
} as const;

export const CALENDAR_EVENT_LEVELS: Record<CalendarEventLevel, string> = {
  Danger: 'danger',
  Success: 'success',
  Primary: 'primary',
  Warning: 'warning'
};

export const CALENDAR_EVENT_LEVEL_LABELS: Record<CalendarEventLevel, string> = {
  Danger: 'خطر',
  Success: 'موفقیت',
  Primary: 'اصلی',
  Warning: 'هشدار'
};

export const CALENDAR_VIEW_OPTIONS: CalendarViewOption[] = [
  { key: 'multiMonthYear', label: 'سال' },
  { key: 'dayGridMonth', label: 'ماه' },
  { key: 'timeGridWeek', label: 'هفته' },
  { key: 'timeGridDay', label: 'روز' }
];

function isoDay(offset = 0) {
  return new Date(Date.now() + offset * 86400000).toISOString().split('T')[0];
}

export function getInitialCalendarEvents(): CalendarEvent[] {
  return [
    {
      id: '1',
      title: CALENDAR_COPY.conference,
      start: isoDay(0),
      extendedProps: { calendar: 'Danger' }
    },
    {
      id: '2',
      title: CALENDAR_COPY.meeting,
      start: isoDay(1),
      extendedProps: { calendar: 'Success' }
    },
    {
      id: '3',
      title: CALENDAR_COPY.workshop,
      start: isoDay(2),
      end: isoDay(3),
      extendedProps: { calendar: 'Primary' }
    }
  ];
}

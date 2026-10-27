export type CalendarEventLevel = 'Danger' | 'Success' | 'Primary' | 'Warning';

export type CalendarEvent = {
  id: string;
  title: string;
  start: string;
  end?: string;
  allDay?: boolean;
  extendedProps: {
    calendar: string;
  };
};

export type CalendarViewOption = {
  key: string;
  label: string;
};

export type EventFormData = {
  title: string;
  start: string;
  end: string;
  level: string;
};

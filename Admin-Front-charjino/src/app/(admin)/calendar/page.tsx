import '@fullcalendar/react/skeleton.css';
import '@fullcalendar/react/themes/classic/palette.css';
import '@fullcalendar/react/themes/classic/theme.css';

import { CalendarBoard } from '@/components/calendar';
import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';
import { CALENDAR_COPY } from '@/data/calendar';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: CALENDAR_COPY.pageTitle
};

export default function CalendarPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle={CALENDAR_COPY.pageTitle} />
      <CalendarBoard />
    </div>
  );
}

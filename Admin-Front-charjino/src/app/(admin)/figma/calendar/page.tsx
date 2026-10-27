import { DateFieldBoard } from '@/components/ui/field';
import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';

export default function CalendarPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle='تقویم' />
      <DateFieldBoard />
    </div>
  );
}

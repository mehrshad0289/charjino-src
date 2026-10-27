import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';
import { PaginationBoard } from '@/components/ui/pagination';

export default function PaginationPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle='صفحه‌بندی' />
      <PaginationBoard />
    </div>
  );
}

import { DefaultModalBoard } from '@/components/ui/modal';
import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';

export default function ModalsPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle='مودال‌ها' />
      <div className='grid grid-cols-1 gap-5 xl:grid-cols-2 xl:gap-6'>
        <DefaultModalBoard />
      </div>
    </div>
  );
}

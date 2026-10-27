import { ComponentCard } from '@/components/ui/component-card';
import { ResponsiveImage, ThreeColumnImageGrid, TwoColumnImageGrid } from '@/components/ui/images';
import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';

export default function ImagesPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle='تصاویر' />
      <div className='space-y-5 sm:space-y-6'>
        <ComponentCard title='تصویر واکنش‌گرا'>
          <ResponsiveImage />
        </ComponentCard>
        <ComponentCard title='تصویر در شبکه دو ستونه'>
          <TwoColumnImageGrid />
        </ComponentCard>
        <ComponentCard title='تصویر در شبکه سه ستونه'>
          <ThreeColumnImageGrid />
        </ComponentCard>
      </div>
    </div>
  );
}

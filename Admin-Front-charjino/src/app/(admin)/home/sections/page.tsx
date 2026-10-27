import { getHomeCms } from '@/actions/admin.actions';
import { HomeSectionsScreen } from '@/components/admin/home-sections-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'دسته‌های صفحه اصلی'
};

export default async function HomeSectionsPage() {
  const home = await getHomeCms();

  return (
    <div>
      <PageBreadcrumb pageTitle='دسته‌های صفحه اصلی' />
      <ComponentCard
        title='دسته‌های هوم'
        desc='روشن یا خاموش کردن دسته، تعداد محصول، و انتخاب دستی. مشاهده همه به فهرست همان دسته در فروشگاه می‌رود.'
      >
        <HomeSectionsScreen sections={home.ok ? home.data.sections ?? [] : []} error={home.ok ? undefined : home.message} />
      </ComponentCard>
    </div>
  );
}
